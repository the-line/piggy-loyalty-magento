<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Cron;

use Leat\AsyncQueue\Model\Builder\JobBuilder;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Setup\Patch\Data\AddContactUuidCustomerAttribute;
use Magento\Customer\Model\ResourceModel\Customer\Collection;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;

abstract class AbstractCron
{
    /**
     * @var AdapterInterface
     */
    protected AdapterInterface $connection;

    public function __construct(
        protected CustomerCollectionFactory $customerCollectionFactory,
        protected JobBuilder $jobBuilder,
        protected Connector $leatConnector,
        protected RequestTypePool $leatRequestTypePool,
        protected ResourceConnection $resourceConnection,
    ) {
        $this->connection = $this->resourceConnection->getConnection();
    }

    /**
     * Ensure Leat Cron gets handled securely with logging.
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->run();
        } catch (\Throwable $e) {
            $this->leatConnector->getLogger()->debug(sprintf(
                "%s threw an error: %s \n %s",
                get_class($this),
                $e->getMessage(),
                $e->getTraceAsString()
            ));
        }
    }

    /**
     * @return void
     */
    abstract public function run(): void;

    /**
     * @param int $storeId
     * @param bool $hasExistingContactUuid
     * @return Collection
     * @throws LocalizedException
     */
    public function getCustomerContactCollection(int $storeId, bool $hasExistingContactUuid = true): Collection
    {
        $leatIntegrationGroups = $this->leatConnector->getConfig()->getCustomerGroupMapping();
        $collection = $this->customerCollectionFactory->create();
        $collection->addAttributeToFilter('group_id', ['in' => $leatIntegrationGroups]);
        $collection->addAttributeToFilter('store_id', $storeId);
        if ($hasExistingContactUuid) {
            $collection->addAttributeToFilter(AddContactUuidCustomerAttribute::ATTRIBUTE_CODE, ['neq' => null]);
        }

        return $collection;
    }

    /**
     * Split a dataset into JSON payload batches that stay under a byte limit.
     * Accepts both arrays and Generators, yielding batches dynamically.
     *
     * @param iterable $dataset Array or Generator containing the items
     * @param int $maxItems Maximum items per batch.
     * @param int $maxBytes Maximum allowed JSON payload size in bytes.
     *
     * @return \Generator<int, array> Yields individual calculated batches
     *
     * @throws \InvalidArgumentException When a single item exceeds the limit.
     * @throws \RuntimeException When json_encode() fails.
     */
    function buildBatches(iterable $dataset, int $maxItems = 250, int $maxBytes = 972800): \Generator
    {
        $batch = [];
        $batchBytes = 2; // Account for structural brackets "[]"

        foreach ($dataset as $item) {
            $json = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new \RuntimeException('Failed to encode item as JSON: ' . json_last_error_msg());
            }

            $itemBytes = strlen($json);

            if ($itemBytes > $maxBytes) {
                throw new \InvalidArgumentException(
                    "A single item exceeds the maximum allowed payload size of {$maxBytes} bytes."
                );
            }

            $separatorBytes = empty($batch) ? 0 : 1; // comma between elements
            $nextBytes = $batchBytes + $separatorBytes + $itemBytes;

            if (!empty($batch) && (count($batch) >= $maxItems || $nextBytes > $maxBytes)) {
                yield $batch;

                // Reset for the next batch
                $batch = [];
                $batchBytes = 2;
                $separatorBytes = 0;
                $nextBytes = $batchBytes + $itemBytes;
            }

            $batch[] = $item;
            $batchBytes = $nextBytes;
        }

        // Don't forget the leftover items at the end
        if (!empty($batch)) {
            yield $batch;
        }
    }
}
