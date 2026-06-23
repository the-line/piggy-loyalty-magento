<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Cron\Order;

use Leat\AsyncQueue\Model\Builder\JobBuilder;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\LoyaltyAsync\Cron\AbstractCron;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\CustomerContactLink;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\ContactBuilder;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\OrderApiBuilder;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\OrderBuilder as LegacyOrderBuilder;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Flag;
use Magento\Framework\FlagFactory;
use Magento\Framework\FlagManager;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Cron job responsible for exporting Magento orders to Leat.
 */
class OrderExport extends AbstractCron
{
    /**
     * Orders created within this time window are considered for export.
     *
     * The window is intentionally wider than the cron runtime so that temporary
     * scheduling delays or slow processing do not cause missed exports.
     */
    public const string ORDER_RETRIEVAL_CUTOFF = '-6 hours';

    /**
     * Order statuses that are allowed to be exported to Leat.
     *
     * @var string[]
     */
    public const array ALLOWED_ORDER_STATUS = ['pending', 'processing', 'complete'];

    /**
     * Flag code used to perform a one-time export starting from a specific date.
     *
     * The actual flag code is store-specific and is generated from this base value.
     */
    private const string EXPORT_ORDERS_SINCE_FLAG = 'leat_export_orders_since';

    /**
     * The store ID currently being processed.
     *
     * @var int|null
     */
    private ?int $currentStoreId = null;

    public function __construct(
        protected OrderItemRepositoryInterface $orderItemInterface,
        protected OrderRepositoryInterface     $orderRepository,
        protected SearchCriteriaBuilder        $searchCriteriaBuilder,
        protected DateTime                     $dateTime,
        protected ContactBuilder               $contactBuilder,
        protected LegacyOrderBuilder           $legacyOrderBuilder,
        protected CustomerContactLink          $contact,
        protected FlagManager                  $flagManager,
        protected FlagFactory                  $flagFactory,
        protected StoreManagerInterface        $storeManager,
        protected OrderApiBuilder              $orderApiBuilder,
        ResourceConnection                     $resourceConnection,
        CustomerCollectionFactory              $customerCollectionFactory,
        JobBuilder                             $jobBuilder,
        Connector                              $leatConnector,
        RequestTypePool                        $leatRequestTypePool,
    ) {
        parent::__construct(
            $customerCollectionFactory,
            $jobBuilder,
            $leatConnector,
            $leatRequestTypePool,
            $resourceConnection
        );
    }

    /**
     * Collects missed orders and creates jobs to sync them to Leat.
     *
     * The method iterates through all stores and delegates store-specific
     * processing to {@see processOrders()}.
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function run(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();
            $this->processOrders($storeId);
        }
    }

    /**
     * Processes all exportable orders for a single store.
     *
     * Steps performed for each order:
     * - determine whether the order status and payment method are eligible,
     * - ensure the customer exists or queue a contact creation job,
     * - create a transaction job using either the legacy or API builder,
     * - mark the order as exported,
     * - commit the transaction or roll back on failure.
     *
     * @param int $storeId Store identifier to process.
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function processOrders(int $storeId): void
    {
        $this->currentStoreId = $storeId;
        $orders = $this->getContactOrders();

        foreach ($orders as $order) {
            $paymentMethod = $order->getPayment()->getMethod();
            $orderStatus = $order->getStatus();
            $allowedPending = $this->leatConnector->getConfig()->getPendingPaymentOrderExport($storeId);

            // Skip pending orders that are not part of the allowed pending payment methods.
            if ($orderStatus == 'pending' && !in_array($paymentMethod, $allowedPending)) {
                continue;
            } else {
                $hasUuid = $this->contact->getContactUuid($order->getCustomerId());
                if (!$hasUuid && !$this->contact->hasCreateJob($order->getCustomerId())) {
                    $this->contactBuilder->addNewContact(
                        $this->contact->getCustomer($order->getCustomerId())
                    );
                }

                if (!$this->leatConnector->getConfig()->getIsLegacyOrderExportEnabled($storeId)
                    && $this->orderApiBuilder->hasUnprocessedGiftcardItems($order)
                ) {
                    continue;
                }

                if ($this->leatConnector->getConfig()->getIsOrderExportEnabled($storeId)) {
                    try {
                        $this->connection->beginTransaction();

                        if ($this->leatConnector->getConfig()->getIsLegacyOrderExportEnabled($storeId)) {
                            $this->legacyOrderBuilder->addTransactionJob($order);
                        } else {
                            $this->orderApiBuilder->addTransactionJob($order);
                        }
                        $this->markOrder($order);

                        $this->connection->commit();
                    } catch (\Throwable $e) {
                        $this->connection->rollBack();
                        $this->leatConnector->getLogger()->debug(sprintf(
                            '%s threw an error: %s %s%s',
                            get_class($this),
                            $e->getMessage(),
                            "\n",
                            $e->getTraceAsString()
                        ));
                    }
                }
            }
        }
    }

    /**
     * Marks an order as exported to Leat and persists the change.
     *
     * This updates the export flag and records the export timestamp.
     *
     * @param OrderInterface $order Order to mark as exported.
     * @return void
     */
    protected function markOrder(OrderInterface $order): void
    {
        $order->setExportedToLeat(true);
        $order->setExportedToLeatAt(new \DateTime());

        $this->orderRepository->save($order);
    }

    /**
     * Returns the orders that are eligible for export.
     *
     * Eligible orders must:
     * - be created after the configured cutoff date,
     * - not already be exported to Leat,
     * - belong to one of the known customer IDs for the current store,
     * - have an allowed order status.
     *
     * If a one-time export date flag is present, it overrides the normal cutoff date.
     *
     * @return OrderInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getContactOrders(): array
    {
        $customers = $this->getCustomerContactCollection($this->currentStoreId, false)->getItems();
        $customerIds = array_keys($customers);

        $timeFrom = strtotime(self::ORDER_RETRIEVAL_CUTOFF, time());
        if ($oneTimeExportDate = $this->getOneTimeOrderExportDate()) {
            $timeFrom = strtotime($oneTimeExportDate);
        }

        $dateFrom = $this->dateTime->gmtDate(null, $timeFrom);

        return $this->orderRepository->getList(
            $this->searchCriteriaBuilder->addFilter(
                'created_at',
                $dateFrom,
                'gteq'
            )->addFilter(
                'exported_to_leat',
                false
            )->addFilter(
                'customer_id',
                $customerIds,
                'in'
            )->addFilter(
                'status',
                self::ALLOWED_ORDER_STATUS,
                'in'
            )->create()
        )->getItems();
    }

    /**
     * Returns the one-time export start date for the current store, if configured.
     *
     * This is primarily used for initial integration setup or when additional groups
     * are added and a historical export is needed.
     *
     * When a date is found, the corresponding flag is deleted so it is only used once.
     *
     * @return string|null The export start date, or null when no one-time export is configured.
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getOneTimeOrderExportDate(): ?string
    {
        $flag = $this->getFlag()->loadSelf();
        $date = (string) $flag->getData('flag_data');
        if ($date) {
            $this->flagManager->deleteFlag($this->getExportSinceFlagCode());
        }

        return $date ?? null;
    }

    /**
     * Creates the Magento flag instance for the current store-specific export flag.
     *
     * @return Flag
     */
    private function getFlag(): Flag
    {
        return $this->flagFactory->create(['data' => ['flag_code' => $this->getExportSinceFlagCode()]]);
    }

    /**
     * Builds the store-specific flag code used for one-time export dates.
     *
     * @return string
     */
    private function getExportSinceFlagCode(): string
    {
        return sprintf('%s_%s', self::EXPORT_ORDERS_SINCE_FLAG, $this->currentStoreId);
    }
}
