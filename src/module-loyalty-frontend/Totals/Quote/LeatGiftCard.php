<?php
declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Totals\Quote;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\GiftCard\ValidatorService;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class LeatGiftCard extends AbstractTotal
{
    /**
     * @param Config $config
     * @param CartExtensionFactory $cartExtensionFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param AppliedGiftCardRepositoryInterface $appliedGiftCardRepository
     * @param ValidatorService $validatorService
     */
    public function __construct(
        protected Config $config,
        protected CartExtensionFactory $cartExtensionFactory,
        protected PriceCurrencyInterface $priceCurrency,
        protected AppliedGiftCardRepositoryInterface $appliedGiftCardRepository,
        protected ValidatorService $validatorService
    ) {
        $this->setCode('leat_loyalty_giftcard');
    }

    /**
     * Collect prepaid balance total
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     * @throws CouldNotSaveException
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        // Skip if not enabled
        if (!$quote->getCustomerId()) {
            return $this;
        }

        if (empty($shippingAssignment->getItems())) {
            return $this;
        }

        // Get gift card amount from quote
        $totalGiftCardAmount = $this->getMaxGiftCardBalanceAmount($quote, $total);
        if ($totalGiftCardAmount <= 0) {
            return $this;
        }

        // Ensure gift card amount doesn't exceed grand total
        $baseGrandTotal = $total->getBaseGrandTotal();
        if ($totalGiftCardAmount > $baseGrandTotal) {
            $totalGiftCardAmount = $baseGrandTotal;
        }

        // Convert to quote currency
        $amount = $this->priceCurrency->convert($totalGiftCardAmount);

        // Add as a negative amount (like a discount)
        $total->setBaseGrandTotal($baseGrandTotal - $totalGiftCardAmount);
        $total->setGrandTotal($total->getGrandTotal() - $amount);

        // Add to totals
        $total->addTotalAmount($this->getCode(), -$amount);
        $total->addBaseTotalAmount($this->getCode(), -$totalGiftCardAmount);

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
        if (!$quote->getCustomerId()) {
            return null;
        }

        // Get balance amount from quote
        $totalGiftCardAmount = $this->getAppliedGftCardBalanceAmount($quote);
        if ($totalGiftCardAmount <= 0) {
            return null;
        }

        // Convert to quote currency
        $amount = $this->priceCurrency->convert($totalGiftCardAmount);

        return [
            'code' => $this->getCode(),
            'title' => __('Leat Gift Cards'),
            'value' => -$amount
        ];
    }

    /**
     * Get leat balance amount from quote
     *
     * @param Quote $quote
     * @param Total $total
     * @return float
     * @throws CouldNotSaveException
     */
    private function getMaxGiftCardBalanceAmount(Quote $quote, Total $total): float
    {
        $items = $this->appliedGiftCardRepository->getByQuoteId((int) $quote->getId());
        $totalBalance = 0;
        $grandTotal = $total->getBaseGrandTotal();
        $storeId = (int) $quote->getStoreId();
        foreach ($items as $item) {
            if ($this->validatorService->isValid($item->getGiftCardCode(), $storeId)) {
                $balance = $this->validatorService->getAvailableBalance($item->getGiftCardCode(), $storeId);

                $balance = min($balance, $grandTotal);
                $item->setAppliedAmount($balance);
                $item->setBaseAppliedAmount($balance);
                $this->appliedGiftCardRepository->save($item);

                $grandTotal -= $balance;
                $totalBalance += $balance;
            }
        }

        return $totalBalance;
    }

    /**
     * @param Quote $quote
     * @return float
     */
    private function getAppliedGftCardBalanceAmount(Quote $quote): float
    {
        $items = $this->appliedGiftCardRepository->getByQuoteId((int) $quote->getId());
        $totalBalance = 0;

        foreach ($items as $item) {
            $totalBalance += $item->getBaseAppliedAmount();
        }

        return $totalBalance;
    }
}
