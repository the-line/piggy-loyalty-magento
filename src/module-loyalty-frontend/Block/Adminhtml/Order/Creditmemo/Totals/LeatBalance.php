<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Adminhtml\Order\Creditmemo\Totals;

use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Model\Config;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * Adminhtml credit memo totals prepaid balance block
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
     * Initialize creditmemo adjustment totals
     *
     * @return $this
     */
    public function initTotals()
    {
        $parent = $this->getParentBlock();

        $value = $this->getCurrentRefundAmount();
        $total = new DataObject([
            'code' => 'leat_loyalty_balance',
            'strong' => false,
            'value' => -$value,
            'base_value' => -$value,
            'label' => __('Prepaid Balance'),
            'sort_order' => 750
        ]);

        $parent->addTotalBefore($total, 'grand_total');

        if ($this->getSource()->getId()) {
            $label = __('Prepaid balance Refunded');
        } else {
            $label = __('Prepaid balance to Refund');
        }

        $refundTotal = new DataObject([
            'code' => 'leat_loyalty_balance_refund',
            'strong' => true,
            'value' => $value,
            'base_value' => $value,
            'label' => $label,
            'area' => 'footer',
            'sort_order' => 750
        ]);

        $parent->addTotal($refundTotal, 'grand_total');
        return $this;
    }

    /**
     * Get source credit memo
     *
     * @return Creditmemo
     */
    public function getSource(): ?Creditmemo
    {
        return $this->getParentBlock()->getSource();
    }

    /**
     * Get order from creditmemo
     *
     * @return \Magento\Sales\Api\Data\OrderInterface|null
     */
    public function getOrder()
    {
        $source = $this->getSource();
        return $source ? $source->getOrder() : null;
    }

    /**
     * Get original prepaid balance amount used on the order
     *
     * @return float
     */
    public function getOriginalBalanceAmount(): float
    {
        $order = $this->getOrder();
        if (!$order) {
            return 0.0;
        }

        return $this->orderLeatBalanceRepository->getLeatBalanceAmount($order);
    }

    /**
     * Get previously refunded prepaid balance amount
     *
     * @return float
     */
    public function getPreviouslyRefundedAmount(): float
    {
        $order = $this->getOrder();
        if (!$order) {
            return 0.0;
        }

        return $this->orderLeatBalanceRepository->getLeatRefundedAmount($order);
    }

    /**
     * Get current prepaid balance amount to refund
     *
     * @return float
     */
    public function getCurrentRefundAmount(): float
    {
        $source = $this->getSource();
        if (!$source) {
            return 0.0;
        }

        if ($source->getLeatLoyaltyBalanceAmount() !== null) {
            return (float)$source->getLeatLoyaltyBalanceAmount();
        }

        return 0.0;
    }

    /**
     * Get maximum prepaid balance amount that can be refunded
     *
     * @return float
     */
    public function getMaxRefundAmount(): float
    {
        return $this->getOriginalBalanceAmount() - $this->getPreviouslyRefundedAmount();
    }

    /**
     * Check if prepaid balance should be displayed for refund
     *
     * @return bool
     */
    public function canDisplayLeatBalance(): bool
    {
        if (!$this->config->isPrepaidBalanceEnabled()) {
            return false;
        }

        $maxAmount = $this->getMaxRefundAmount();
        return $maxAmount > 0.0001;
    }

    /**
     * Format amount with currency
     *
     * @param float $amount
     * @return string
     */
    public function formatAmount(float $amount): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return (string)$amount;
        }

        return $order->formatPrice($amount);
    }
}
