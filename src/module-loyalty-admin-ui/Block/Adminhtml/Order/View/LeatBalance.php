<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\Order\View;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Block to display prepaid balance information in admin order view
 */
class LeatBalance extends Template
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param Config $config
     * @param OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository
     * @param PricingHelper $pricingHelper
     * @param array $data
     */
    public function __construct(
        Context                                              $context,
        private readonly Registry                            $registry,
        private readonly Config                              $config,
        private readonly OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository,
        private readonly PricingHelper                       $pricingHelper,
        array                                                $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check if block should be displayed
     *
     * @return bool
     */
    public function canDisplay(): bool
    {
        if (!$this->config->isPrepaidBalanceEnabled()) {
            return false;
        }

        $order = $this->getOrder();
        if (!$order) {
            return false;
        }

        $balanceAmount = $this->orderLeatBalanceRepository->getLeatBalanceAmount($order);
        return $balanceAmount > 0;
    }

    /**
     * Get current order
     *
     * @return OrderInterface|null
     */
    public function getOrder(): ?OrderInterface
    {
        return $this->registry->registry('current_order');
    }

    /**
     * Get prepaid balance amount
     *
     * @return float
     */
    public function getPrepaidBalanceAmount(): float
    {
        $order = $this->getOrder();
        if (!$order) {
            return 0;
        }

        // First try to get directly from order data
        $balanceAmount = 0.0;
        if ($order->getData('leat_loyalty_balance_amount') !== null) {
            $balanceAmount = (float)$order->getData('leat_loyalty_balance_amount');
        }

        // If not found, try repository
        if ($balanceAmount <= 0) {
            $balanceAmount = $this->orderLeatBalanceRepository->getLeatBalanceAmount($order);
        }

        return $balanceAmount;
    }

    /**
     * Get original grand total (before prepaid balance)
     *
     * @return float
     */
    public function getOriginalGrandTotal(): float
    {
        $order = $this->getOrder();
        if (!$order) {
            return 0;
        }

        $grandTotal = (float)$order->getGrandTotal();
        $balanceAmount = $this->getPrepaidBalanceAmount();

        return $grandTotal + $balanceAmount;
    }

    /**
     * Format price
     *
     * @param float $amount
     * @return string
     */
    public function formatPrice(float $amount): string
    {
        return $this->pricingHelper->currency($amount, true, false);
    }
}
