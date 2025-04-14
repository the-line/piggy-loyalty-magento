<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Totals\Quote;

use Leat\Loyalty\Model\Config;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class LeatBalance extends AbstractTotal
{
    /**
     * @param Config $config
     * @param CartExtensionFactory $cartExtensionFactory
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        protected Config $config,
        protected CartExtensionFactory $cartExtensionFactory,
        protected PriceCurrencyInterface $priceCurrency
    ) {
        $this->setCode('leat_loyalty_balance');
    }

    /**
     * Collect prepaid balance total
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        // Skip if not enabled
        if (!$this->config->isPrepaidBalanceEnabled() || !$quote->getCustomerId()) {
            return $this;
        }

        if (empty($shippingAssignment->getItems())) {
            return $this;
        }

        // Get balance amount from quote
        $balanceAmount = $this->getLeatBalanceAmount($quote);
        if ($balanceAmount <= 0) {
            return $this;
        }

        // Ensure balance amount doesn't exceed grand total
        $baseGrandTotal = $total->getBaseGrandTotal();
        if ($balanceAmount > $baseGrandTotal) {
            $balanceAmount = $baseGrandTotal;
            $this->setLeatBalanceAmount($quote, $balanceAmount);
        }

        // Convert to quote currency
        $amount = $this->priceCurrency->convert($balanceAmount);

        // Add as a negative amount (like a discount)
        $total->setBaseGrandTotal($baseGrandTotal - $balanceAmount);
        $total->setGrandTotal($total->getGrandTotal() - $amount);

        // Add to totals
        $total->addTotalAmount($this->getCode(), -$amount);
        $total->addBaseTotalAmount($this->getCode(), -$balanceAmount);

        return $this;
    }

    /**
     * Fetch prepaid balance total
     *
     * @param Quote $quote
     * @param Total $total
     * @return array
     */
    public function fetch(Quote $quote, Total $total)
    {
        // Skip if not enabled
        if (!$this->config->isPrepaidBalanceEnabled() || !$quote->getCustomerId()) {
            return null;
        }

        // Get balance amount from quote
        $balanceAmount = $this->getLeatBalanceAmount($quote);
        if ($balanceAmount <= 0) {
            return null;
        }

        // Convert to quote currency
        $amount = $this->priceCurrency->convert($balanceAmount);

        return [
            'code' => $this->getCode(),
            'title' => __('Prepaid Balance'),
            'value' => -$amount
        ];
    }

    /**
     * Get leat balance amount from quote
     *
     * @param Quote $quote
     * @return float
     */
    private function getLeatBalanceAmount(Quote $quote): float
    {
        // First check direct field (more reliable for persistence)
        $directAmount = $quote->getData('leat_loyalty_balance_amount');
        if ($directAmount) {
            return (float)$directAmount;
        }

        // Fallback to extension attributes
        $extensionAttributes = $quote->getExtensionAttributes();
        if ($extensionAttributes && $extensionAttributes->getLeatLoyaltyBalanceAmount()) {
            return (float)$extensionAttributes->getLeatLoyaltyBalanceAmount();
        }

        return 0.0;
    }

    /**
     * Set leat balance amount on quote
     *
     * @param Quote $quote
     * @param float $amount
     * @return void
     */
    private function setLeatBalanceAmount(Quote $quote, float $amount): void
    {
        // Set direct field for persistence
        $quote->setData('leat_loyalty_balance_amount', $amount);

        // Also set extension attributes for API compatibility
        $extensionAttributes = $quote->getExtensionAttributes();
        if (!$extensionAttributes) {
            $extensionAttributes = $this->cartExtensionFactory->create();
        }

        $extensionAttributes->setLeatLoyaltyBalanceAmount($amount);
        $quote->setExtensionAttributes($extensionAttributes);
    }
}
