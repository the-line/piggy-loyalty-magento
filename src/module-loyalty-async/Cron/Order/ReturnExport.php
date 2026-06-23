<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Cron\Order;

use Leat\AsyncQueue\Model\Builder\JobBuilder;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\LoyaltyAsync\Cron\AbstractCron;
use Leat\Loyalty\Model\Connector;
use Leat\LoyaltyAsync\Model\Queue\Builder\Service\ReturnApiBuilder;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Store\Model\StoreManagerInterface;

/**
 *  Cron job responsible for exporting eligible Magento credit memos
 *  as return jobs to the Leat integration.
 */
class ReturnExport extends AbstractCron
{
    /**
     * Credit memos created within this time window are eligible for export.
     *
     * The window is intentionally larger than the cron interval to reduce the risk
     * of missing entries because of scheduling delays or runtime jitter.
     */
    public const string CREDITMEMO_RETRIEVAL_CUTOFF = '-6 hours';

    /**
     * Store ID currently being processed.
     *
     * This is updated during `run()` before each store is processed.
     *
     * @var int|null
     */
    private ?int $currentStoreId = null;


    public function __construct(
        protected CreditmemoRepositoryInterface $creditmemoRepository,
        protected OrderRepositoryInterface $orderRepository,
        protected SearchCriteriaBuilder $searchCriteriaBuilder,
        protected DateTime $dateTime,
        protected ReturnApiBuilder $returnApiBuilder,
        protected StoreManagerInterface $storeManager,
        CustomerCollectionFactory $customerCollectionFactory,
        JobBuilder $jobBuilder,
        Connector $leatConnector,
        RequestTypePool $leatRequestTypePool,
        ResourceConnection $resourceConnection,
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
     * Runs the export process for every available store.
     *
     * For each store, the method checks whether order export is enabled and,
     * if so, processes all eligible credit memos for that store.
     *
     * @return void
     */
    public function run(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $this->currentStoreId = (int) $store->getId();
            if (!$this->leatConnector->getConfig()->getIsOrderExportEnabled($this->currentStoreId)) {
                continue;
            }

            $this->processCreditmemos();
        }
    }

    /**
     * Processes all exportable credit memos for the current store.
     *
     * The method:
     * - loads the customer collection for the current store,
     * - converts the collection into a lookup array of customer IDs,
     * - loads all eligible credit memos,
     * - loads the related order for each credit memo,
     * - skips orders that were not exported to Leat,
     * - skips customers that do not exist in the local customer set,
     * - wraps job creation and credit memo marking in a database transaction,
     * - rolls back and logs any error that occurs during processing.
     *
     * @return void
     */
    protected function processCreditmemos(): void
    {
        $customers = $this->getCustomerContactCollection($this->currentStoreId, false)->getItems();
        $customerIds = array_flip(array_keys($customers));

        foreach ($this->getExportableCreditmemos() as $creditmemo) {
            /** @var Creditmemo $creditmemo */
            /** @var Order $order */
            $order = $this->orderRepository->get($creditmemo->getOrderId());

            if (!$order->getData('exported_to_leat')) {
                continue;
            }

            if ($order->getCustomerId() && !isset($customerIds[$order->getCustomerId()])) {
                continue;
            }

            try {
                $this->connection->beginTransaction();

                $this->returnApiBuilder->addReturnJob($order, $creditmemo);
                $this->markCreditmemo($creditmemo);

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

    /**
     * Returns credit memos created within the configured retrieval window.
     *
     * The query currently filters by:
     * - `created_at` greater than or equal to the computed cutoff timestamp,
     * - `exported_to_leat = false`, which indicates it has not yet been exported.
     *
     * @return CreditmemoInterface[] Eligible credit memos.
     */
    protected function getExportableCreditmemos(): array
    {
        $dateFrom = $this->dateTime->gmtDate(null, strtotime(self::CREDITMEMO_RETRIEVAL_CUTOFF, time()));

        return $this->creditmemoRepository->getList(
            $this->searchCriteriaBuilder
                ->addFilter('created_at', $dateFrom, 'gteq')
                ->addFilter('exported_to_leat', false)
                ->create()
        )->getItems();
    }

    /**
     * Marks the given credit memo as exported to Leat and persists the change.
     *
     * This updates:
     * - `exported_to_leat` to `true`,
     * - `exported_to_leat_at` to the current date/time.
     *
     * @param Creditmemo $creditmemo Credit memo to update.
     * @return void
     */
    protected function markCreditmemo(Creditmemo $creditmemo): void
    {
        $creditmemo->setData('exported_to_leat', true);
        $creditmemo->setData('exported_to_leat_at', new \DateTime());

        $this->creditmemoRepository->save($creditmemo);
    }
}
