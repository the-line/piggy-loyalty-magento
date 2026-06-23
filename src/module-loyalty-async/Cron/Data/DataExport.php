<?php
/**
 * DataExport
 *
 * @copyright Copyright © 2026 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\LoyaltyAsync\Cron\Data;

use Leat\AsyncQueue\Model\Builder\JobBuilder;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Model\Connector;
use Leat\LoyaltyAsync\Cron\AbstractCron;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Framework\App\ResourceConnection;

class DataExport extends AbstractCron
{
    public function __construct(
        protected CategoryExport $categoryExport,
        protected ProductExport $productExport,
        CustomerCollectionFactory $customerCollectionFactory,
        JobBuilder $jobBuilder,
        Connector $leatConnector,
        RequestTypePool $leatRequestTypePool,
        ResourceConnection $resourceConnection
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
     * Orchestrate the full catalog data export to Leat.
     *
     * Sequentially triggers the category and product export pipelines.
     * Each sub-export is governed by its own weekly throttle and per-shop-UUID
     * deduplication — no data is sent if the respective last-run flag indicates
     * an export already occurred within the past week.
     *
     * Execution order:
     * 1. {@see CategoryExport::execute()} — exports active Magento categories in batches of 250.
     * 2. {@see ProductExport::execute()} — exports products (with categories and descriptions) in batches of 100.
     *
     * Both steps run through {@see AbstractCron::execute()}, which wraps `run()` with
     * standardized logging. If either step throws, the exception propagates and
     * subsequent steps are not executed.
     *
     * @return void
     * @throws \Exception When category or product export encounters an API or localization error
     */
    public function run(): void
    {
        $this->categoryExport->execute();
        $this->productExport->execute();
    }
}
