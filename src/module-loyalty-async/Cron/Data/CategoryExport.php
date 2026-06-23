<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Cron\Data;

use Leat\AsyncQueue\Model\Builder\JobBuilder;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Model\Connector;
use Leat\LoyaltyAsync\Cron\AbstractCron;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\FlagManager;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Exceptions\PiggyRequestException;

class CategoryExport extends AbstractCron
{
    /**
     * Max number of category rows per API batch request.
     */
    public const BATCH_SIZE = 250;

    /**
     * Flag key used by `FlagManager` to store the Unix timestamp of the most recent
     * successful category export cron execution.
     */
    public const LAST_RUN_FLAG = 'leat_category_export_last_run_%s';

    /**
     * 1 week in seconds (60 seconds * 60 minutes * 24 hours * 7 days)
     */
    protected const WEEK_IN_SECONDS = ((60 * 60) * 24) * 7;

    /**
     * Registry of shop UUIDs already processed in this execution.
     *
     * Key: shop UUID string
     * Value: true
     *
     * @var array<string, bool>
     */
    private array $processedShopUUIDs = [];

    /**
     * @param CustomerCollectionFactory $customerCollectionFactory
     * @param JobBuilder $jobBuilder
     * @param Connector $leatConnector
     * @param RequestTypePool $leatRequestTypePool
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param CategoryCollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        CustomerCollectionFactory $customerCollectionFactory,
        JobBuilder $jobBuilder,
        Connector $leatConnector,
        RequestTypePool $leatRequestTypePool,
        ResourceConnection $resourceConnection,
        protected StoreManagerInterface $storeManager,
        protected CategoryCollectionFactory $categoryCollectionFactory,
        protected FlagManager $flagManager
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
     * Execute the category export cron flow.
     *
     * The method loops through all stores and resolves each store's shop UUID.
     * If a UUID has not yet been processed in this run, export is executed once
     * for that store context and UUID is marked as processed.
     *
     * @return void
     * @throws \Exception
     */
    public function run(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int)$store->getId();
            $shopUUID = $this->leatConnector->getConfig()->getShopUuid($storeId);
            $lastRunFlag = $this->flagManager->getFlagData(sprintf(self::LAST_RUN_FLAG, $shopUUID));
            $hasWeekPassed = !$lastRunFlag || (time() - $lastRunFlag['time'] >= self::WEEK_IN_SECONDS);

            if (!isset($this->processedShopUUIDs[$shopUUID]) && $hasWeekPassed) {
                $this->processedShopUUIDs[$shopUUID] = true;
                try {
                    $message = $this->processExport($storeId);
                } catch (LocalizedException $e) {
                    $error = sprintf(
                        'Error during category export for store ID %d (shop UUID: %s): %s',
                        $storeId,
                        $shopUUID,
                        $e->getMessage()
                    );
                } catch (PiggyRequestException $e) {
                    $error = sprintf(
                        'Leat API error during category export for store ID %d (shop UUID: %s): %s',
                        $storeId,
                        $shopUUID,
                        $e->getMessage()
                    );
                } finally {
                    $this->flagManager->saveFlag(sprintf(self::LAST_RUN_FLAG, $shopUUID), [
                        'time' => time(),
                        'success' => isset($message) && !isset($error),
                        'message' => $message ?? ($error ?? null)
                    ]);
                    if ($error ?? false) {
                        $this->leatConnector->getLogger('category_export')->log($error);
                        throw new \Exception($error);
                    }
                }
            }
        }
    }

    /**
     * Build category payloads for a store and send them to Leat in batches.
     *
     * Steps:
     * - Create a store-scoped API connection.
     * - Load and normalize category data via a memory-safe Generator.
     * - Stream payload into byte-calculated batch chunks.
     * - Log real-time batch processing progress.
     * - Send each batch to the categories endpoint.
     *
     * @param int $storeId Magento store ID used for connection and category scope
     * @return string
     * @throws AuthenticationException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws PiggyRequestException
     */
    protected function processExport(int $storeId): string
    {
        $connection = $this->leatConnector->getConnection($storeId);
        $logger = $this->leatConnector->getLogger('category_export');

        $totalCategories = $this->getCategoryCount($storeId);

        $logger->debug(sprintf(
            'Starting category export for store ID %d. Total categories to process: %d.',
            $storeId,
            $totalCategories
        ));

        $categoryGenerator = $this->getCategoryGenerator($storeId);
        $batchGenerator = $this->buildBatches($categoryGenerator, self::BATCH_SIZE);

        $batchCounter = 0;
        $processedCount = 0;

        foreach ($batchGenerator as $batch) {
            $batchCounter++;
            $batchItemCount = count($batch);
            $processedCount += $batchItemCount;

            $connection->categories->batch($batch);

            $logger->debug(sprintf(
                'Store ID %d: Sent batch #%d containing %d categories. (%d/%d completed)',
                $storeId,
                $batchCounter,
                $batchItemCount,
                $processedCount,
                $totalCategories
            ));
        }

        return sprintf(
            'Successfully exported %d categories in %d %s',
            $processedCount,
            $batchCounter,
            $batchCounter === 1 ? 'batch' : 'batches'
        );
    }

    /**
     * Retrieve and normalize category data for Leat SDK batch insertion using chunked pagination.
     *
     * API reference:
     * - https://docs.piggy.eu/v3/oauth/categories
     * Required fields:
     * - external_identifier
     * - name
     *
     * @param int $storeId Magento store ID used to scope category retrieval
     * @param int $pageSize Number of categories to load per page to prevent memory spikes
     * @return \Generator Yields normalized category arrays
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getCategoryGenerator(int $storeId, int $pageSize = 250): \Generator
    {
        $currentPage = 1;

        while (true) {
            $collection = $this->getCategoryCollection($storeId);
            $collection->setPageSize($pageSize);
            $collection->setCurPage($currentPage);

            // Break early if we've requested a page beyond what actually exists
            if ($currentPage > $collection->getLastPageNumber()) {
                break;
            }

            $items = $collection->getItems();

            if (empty($items)) {
                break;
            }

            /** @var Category $category */
            foreach ($items as $category) {
                yield $this->getMappedCategory($category);
            }

            $collection->clear();
            unset($items);

            $currentPage++;
        }
    }

    /**
     *  Map a Magento product entity to the payload format expected by the Leat Products API.
     *
     *  Always includes:
     *  - `external_identifier`: Category ID
     *  - `name`: Category name
     *
     * @param Category $product
     * @return array
     */
    public function getMappedCategory(Category $category): array
    {
        return [
            'external_identifier' => (string)$category->getId(),
            'name' => $category->getName(),
        ];
    }

    /**
     * Create the category collection used for export.
     *
     * Applied filters:
     * - Only categories below the current store root category path (`1/{rootId}/%`).
     * - Exclude global root levels (`level > 1`).
     * - Include only active categories (`is_active = 1`).
     * - Select `name` attribute for payload generation.
     *
     * @param int $storeId Magento store ID used to resolve root category
     * @return Collection
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCategoryCollection(int $storeId): Collection
    {
        $rootId = $this->storeManager->getStore($storeId)->getRootCategoryId();

        $collection = $this->categoryCollectionFactory->create();
        $collection
            ->addAttributeToSelect(['name'])
            ->addFieldToFilter('path', ['like' => "1/$rootId/%"])
            ->addAttributeToFilter('level', ['gt' => 1])
            ->addAttributeToFilter('is_active', 1);

        return $collection;
    }

    /**
     * Get the total count of filtered categories for the store without loading the models.
     *
     * @param int $storeId
     * @return int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getCategoryCount(int $storeId): int
    {
        return (int)$this->getCategoryCollection($storeId)->getSize();
    }
}
