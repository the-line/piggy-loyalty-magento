<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Adminhtml\Order\Invoice\Totals;

use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Model\Config;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;

/**
 * Admin invoice totals leat balance block
 */
class LeatBalance extends Template
{
    /**
     * @param Context $context
     * @param Config $config
     * @param OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository
     * @param array $data
     */
    public function __construct(
        Context                                              $context,
        private readonly Config                              $config,
        private readonly OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository,
        array                                                $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Initialize leat balance total
     *
     * @return $this
     */
    public function initTotals()
    {
        if (!$this->config->isPrepaidBalanceEnabled()) {
            return $this;
        }

        $parent = $this->getParentBlock();
        $source = $parent->getSource();

        // Try to get the order
        $order = null;
        if ($source && method_exists($source, 'getOrder')) {
            $order = $source->getOrder();
        }

        if (!$order instanceof Order) {
            return $this;
        }

        // First try get from order data
        $balanceAmount = 0.0;
        if ($order->getData('leat_loyalty_balance_amount') !== null) {
            $balanceAmount = (float)$order->getData('leat_loyalty_balance_amount');
        }

        // Then try from repository if we didn't get it from data
        if ($balanceAmount <= 0) {
            $balanceAmount = $this->orderLeatBalanceRepository->getLeatBalanceAmount($order);
        }

        if ($balanceAmount <= 0) {
            return $this;
        }

        $total = new DataObject([
            'code' => 'leat_loyalty_balance',
            'strong' => true,
            'value' => -$balanceAmount,
            'base_value' => -$balanceAmount,
            'label' => __('Prepaid Balance'),
            'sort_order' => 750
        ]);

        $parent->addTotalBefore($total, 'grand_total');

        return $this;
    }
}
