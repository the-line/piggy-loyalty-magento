<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Plugin\Order\Grid;

use Leat\Loyalty\Model\Config;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;

/**
 * Plugin to add prepaid balance column to order grid collection
 */
class Collection
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Add leat balance data to the order grid collection
     *
     * @param OrderGridCollection $subject
     * @return void
     */
    public function beforeLoad(OrderGridCollection $subject): void
    {
        if (!$this->config->isPrepaidBalanceEnabled()) {
            return;
        }

        if ($subject->isLoaded()) {
            return;
        }

        $tableName = $subject->getResource()->getTable('sales_order');

        // Check if the join already exists to avoid duplicate joins
        $fromPart = $subject->getSelect()->getPart(\Zend_Db_Select::FROM);
        if (!isset($fromPart['leat_order'])) {
            $subject->getSelect()->joinLeft(
                ['leat_order' => $tableName],
                'main_table.entity_id = leat_order.entity_id',
                ['leat_loyalty_balance_amount' => 'leat_order.leat_loyalty_balance_amount']
            );
        }
    }
}
