<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Cron\Order;

use Leat\AsyncQueue\Model\Builder\JobBuilder;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Helper\GiftcardHelper;
use Leat\LoyaltyAsync\Cron\AbstractCron;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\CustomerContactLink;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\ContactBuilder;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\GiftcardPurchaseBuilder;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\OrderBuilder;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Flag;
use Magento\Framework\FlagFactory;
use Magento\Framework\FlagManager;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class GiftcardProductExport extends AbstractCron
{
    /**
     * Made the cutoff time longer than the cron runtime due to fluctuations in cron runtime and
     * order processing time.
     */
    public const string ORDER_RETRIEVAL_CUTOFF = '-6 hours';

    /**
     * Allowed order statuses to export to Leat
     *
     * @var array
     */
    public const array ALLOWED_ORDER_STATUS = ['pending', 'processing', 'complete'];

    /**
     * This flag can be used to export orders since a given date.
     * To do this, add a new record to the flag table with the flag_code 'leat_export_orders_since'
     * and the flag_data as the date to export since.
     *
     * The flag will be deleted after the date has been used.
     *
     * @var string
     */
    private const string EXPORT_ORDERS_SINCE_FLAG = 'leat_export_giftcard_orders_since';

    /**
     * The store id we are currently processing orders for.
     * @var int|null $currentStoreId
     */
    private ?int $currentStoreId = null;

    public function __construct(
        protected OrderItemRepositoryInterface $orderItemRepository,
        protected OrderRepositoryInterface     $orderRepository,
        protected SearchCriteriaBuilder        $searchCriteriaBuilder,
        protected DateTime                     $dateTime,
        protected GiftcardPurchaseBuilder      $giftcardPurchaseBuilder,
        protected GiftcardHelper               $giftcardHelper,
        protected CustomerContactLink          $contact,
        protected FlagManager                  $flagManager,
        protected FlagFactory                  $flagFactory,
        protected StoreManagerInterface        $storeManager,
        ResourceConnection                     $resourceConnection,
        CustomerCollectionFactory              $customerCollectionFactory,
        JobBuilder                      $jobBuilder,
        Connector                       $leatConnector,
        RequestTypePool                 $leatRequestTypePool,
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
     * Collect any missed orders and create jobs to sync these to leat.
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function run(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();
            $this->processGiftcardPurchases($storeId);
        }
    }

    protected function processGiftcardPurchases(int $storeId): void
    {
        $this->currentStoreId = $storeId;
        $orderItems = $this->getGiftcardOrderItems();

        foreach ($orderItems as $orderItem) {
            $order = $orderItem->getOrder();
            $paymentMethod = $order->getPayment()->getMethod();
            $orderStatus = $order->getStatus();
            $allowedPending = $this->leatConnector->getConfig()->getPendingPaymentOrderExport($storeId);

            // Skip pending orders that are not belongs to the pending payment methods config
            if ($orderStatus == 'pending' && !in_array($paymentMethod, $allowedPending)) {
                continue;
            } else {
                if ($this->leatConnector->getConfig()->isGiftcardEnabled($storeId)) {
                    try {
                        $this->connection->beginTransaction();

                        $this->giftcardPurchaseBuilder->buildGiftcardPurchaseByOrderItem($order, $orderItem);
                        $this->markOrderItem($orderItem);

                        $this->connection->commit();
                    } catch (\Throwable $e) {
                        $this->connection->rollBack();
                        $this->leatConnector->getLogger()->debug(sprintf(
                            "%s threw an error: %s \n %s",
                            get_class($this),
                            $e->getMessage(),
                            $e->getTraceAsString()
                        ));
                    }
                }
            }
        }
    }

    /**
     * Mark order as exported to Leat
     *
     * @param Order $order
     * @return void
     */
    protected function markOrderItem(OrderItemInterface $orderItem): void
    {
        if ($orderItem->getParentItemId()) {
            $parentOrderItem = $this->orderItemRepository->get($orderItem->getParentItemId());
            $this->markOrderItem($parentOrderItem);
        }

        $orderItem->setGiftcardExportedToLeat(true);
        $orderItem->setGiftcardExportedToLeatAt(new \DateTime());

        $this->orderItemRepository->save($orderItem);
    }

    /**
     * Get orders to export. Only orders that are already exported to King will be taken into
     * consideration to fix the edge case where a leat update would overwrite a king update if they
     * happend at the same time.
     *
     * @return OrderInterface[]
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getGiftcardOrderItems(): array
    {
        $productIds = $this->giftcardHelper->getGiftcardProducts($this->currentStoreId, true, true, true);
        return $this->orderItemRepository->getList(
            $this->searchCriteriaBuilder->addFilter(
                'giftcard_exported_to_leat',
                false
            )->addFilter(
                'product_id',
                $productIds,
                'in'
            )->create()
        )->getItems();
    }

    /**
     * Used when a one time export since a given date is needed.
     * - This is used when the Leat integration is first installed.
     * - Or when additional groups are added to the Leat integration.
     *
     * @return string|null
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
     * @return Flag
     */
    private function getFlag(): Flag
    {
        return $this->flagFactory->create(['data' => ['flag_code' => $this->getExportSinceFlagCode()]]);
    }

    /**
     * @return string
     */
    private function getExportSinceFlagCode(): string
    {
        return sprintf("%s_%s", self::EXPORT_ORDERS_SINCE_FLAG, $this->currentStoreId);
    }
}
