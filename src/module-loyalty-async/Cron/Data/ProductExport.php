<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Cron\Data;

use Leat\AsyncQueue\Model\Builder\JobBuilder;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Model\Connector;
use Leat\LoyaltyAsync\Cron\AbstractCron;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\FlagManager;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Exceptions\PiggyRequestException;

class ProductExport extends AbstractCron
{
    /**
     * Max number of product rows per API batch request.
     */
    public const BATCH_SIZE = 100;

    /**
     * Flag key used by `FlagManager` to store the Unix timestamp of the most recent
     * successful product export cron execution.
     */
    public const LAST_RUN_FLAG = 'leat_product_export_last_run_%s';

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
     * @param ProductCollectionFactory $productCollectionFactory
     * @param FlagManager $flagManager
     */
    public function __construct(
        CustomerCollectionFactory $customerCollectionFactory,
        JobBuilder $jobBuilder,
        Connector $leatConnector,
        RequestTypePool $leatRequestTypePool,
        ResourceConnection $resourceConnection,
        protected StoreManagerInterface $storeManager,
        protected ProductCollectionFactory $productCollectionFactory,
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
     * Execute the product export cron flow.
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
                        'Error during product export for store ID %d (shop UUID: %s): %s',
                        $storeId,
                        $shopUUID,
                        $e->getMessage()
                    );
                } catch (PiggyRequestException $e) {
                    $error = sprintf(
                        'Leat API error during product export for store ID %d (shop UUID: %s): %s',
                        $storeId,
                        $shopUUID,
                        $e->getMessage()
                    );
                } catch(\Throwable $e) {
                    $error = sprintf(
                        'Unexpected error during product export for store ID %d (shop UUID: %s): %s',
                        $storeId,
                        $shopUUID,
                        $e->getMessage()
                    );
                } finally{
                    $this->flagManager->saveFlag(sprintf(self::LAST_RUN_FLAG, $shopUUID), [
                        'time' => time(),
                        'success' => isset($message) && !isset($error),
                        'message' => $message ?? ($error ?? null)
                    ]);
                    if ($error ?? false) {
                        $this->leatConnector->getLogger('product_export')->log($error);
                        throw new \Exception($error);
                    }
                }
            }
        }
    }

    /**
     * Build product payloads for a store and send them to Leat in batches.
     *
     * Steps:
     * - Create a store-scoped API connection.
     * - Load and normalize product data.
     * - Split payload into chunked batch arrays.
     * - Log batch/export size.
     * - Send each batch to the categories endpoint.
     *
     * @param int $storeId Magento store ID used for connection and product scope
     * @return string
     * @throws AuthenticationException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws PiggyRequestException
     */
    protected function processExport(int $storeId): string
    {
        $connection = $this->leatConnector->getConnection($storeId);

        $totalProducts = $this->getProductCount($storeId);
        $this->leatConnector->getLogger('product_export')->debug(sprintf(
            'Starting export for store ID %d. Total products to process: %d.',
            $storeId,
            $totalProducts
        ));

        $productGenerator = $this->getProductGenerator($storeId);
        $batchGenerator = $this->buildBatches($productGenerator, self::BATCH_SIZE);

        $batchCounter = 0;
        $processedProductsCounter = 0;

        foreach ($batchGenerator as $batch) {
            $batchCounter++;
            $batchItemCount = count($batch);
            $processedProductsCounter += $batchItemCount;

            $connection->products->batch($batch);

            $this->leatConnector->getLogger('product_export')->debug(sprintf(
                'Store ID %d: Sent batch #%d containing %d products. (%d/%d total products completed)',
                $storeId,
                $batchCounter,
                $batchItemCount,
                $processedProductsCounter,
                $totalProducts
            ));
        }

        $this->leatConnector->getLogger('product_export')->debug(sprintf(
            'Successfully finished product export for store ID %d. Total batches sent: %d.',
            $storeId,
            $batchCounter
        ));

        return sprintf("Successfully exported %d products in %d %s",
            $totalProducts,
            $batchCounter,
            $batchCounter === 1 ? 'batch' : 'batches'
        );
    }

    /**
     * Retrieve and normalize product data for Leat SDK batch insertion using chunked pagination.
     *
     * API reference:
     * - https://docs.piggy.eu/v3/oauth/products
     * Required fields:
     * - external_identifier
     * - name
     *
     * Optional fields:
     * - description
     * - categories, list of external_identifier
     *
     * @param int $storeId Magento store ID used to scope product retrieval
     * @param int $pageSize Number of products to load per page to prevent memory spikes
     * @return \Generator Yields normalized product arrays
     */
    protected function getProductGenerator(int $storeId, int $pageSize = 500): \Generator
    {
        $currentPage = 1;

        while (true) {
            $collection = $this->getProductCollection($storeId);
            $collection->setPageSize($pageSize);
            $collection->setCurPage($currentPage);

            // Break early if we've requested a page beyond what actually exists,
            if ($currentPage > $collection->getLastPageNumber()) {
                break;
            }

            $items = $collection->getItems();
            if (empty($items)) {
                break;
            }

            /** @var Product $product */
            foreach ($items as $product) {
                yield $this->getMappedProduct($product);
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
     *  - `external_identifier`: product SKU
     *  - `name`: product name
     *
     *  Conditionally includes:
     *  - `categories`: mapped category external_identifiers when the product has categories
     *  - `description`: product description when available and non-empty
     *
     * @param Product $product
     * @return array
     */
    public function getMappedProduct(Product $product): array
    {
        $leatProduct = [
            'external_identifier' => $product->getSku(),
            'name' => $product->getName(),
        ];

        $categories = $this->getCategoriesForProduct($product);
        if (!empty($categories)) {
            $leatProduct['categories'] = $categories;
        }

        $description = $this->getDescriptionForProduct($product);
        if (!empty($description)) {
            $leatProduct['description'] = $description;
        }

        return $leatProduct;
    }

    /**
     * @param Product $product
     * @return array
     */
    public function getCategoriesForProduct(Product $product): array
    {
        $categories = [];
        foreach ($product->getCategoryIds() as $categoryId) {
            $categories[] = ['external_identifier' => (int)$categoryId];
        }

        return $categories;
    }

    /**
     * @param Product $product
     * @return string|null
     */
    public function getDescriptionForProduct(Product $product): ?string
    {
        $description = $product->getDescription();
        if (is_string($description)) {
            return $description;
        }
        return null;
    }

    /**
     * Create the product collection used for export.
     *
     * Applied filters:
     * - Only products assigned to the current store (`store_id` filter).
     * - Select attributes for payload generation.
     *
     * @param int $storeId Magento store ID used to resolve root product
     * @return Collection
     */
    public function getProductCollection(int $storeId): Collection
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'description']);
        $collection->addStoreFilter($storeId);

        return $collection;
    }

    /**
     * Get the total count of products for the store without loading the models.
     *
     * @param int $storeId
     * @return int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getProductCount(int $storeId): int
    {
        return (int)$this->getProductCollection($storeId)->getSize();
    }
}
