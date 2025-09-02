<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Model\Order\Pdf\Total;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Magento\Sales\Model\Order\Pdf\Total\DefaultTotal;
use Magento\Tax\Helper\Data;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;

/**
 * Add leat balance to PDF invoice totals
 */
class LeatGiftcard extends DefaultTotal
{
    /**
     * @param AppliedGiftCardRepositoryInterface $appliedGiftCardRepository
     * @param Data $taxHelper
     * @param Calculation $taxCalculation
     * @param CollectionFactory $ordersFactory
     * @param array $data
     */
    public function __construct(
        private readonly AppliedGiftCardRepositoryInterface  $appliedGiftCardRepository,
        Data                                                 $taxHelper,
        Calculation                                          $taxCalculation,
        CollectionFactory                                    $ordersFactory,
        array                                                $data = []
    ) {
        parent::__construct($taxHelper, $taxCalculation, $ordersFactory, $data);
    }

    /**
     * Check if we can display total information in PDF
     *
     * @return bool
     */
    public function canDisplay(): bool
    {
        $order = $this->getOrder();
        if (!$order) {
            return false;
        }

        // Get balance amount from order
        $giftcardAmount = $this->getAmount();

        if ($giftcardAmount === null) {
            return false;
        }

        return true;
    }

    /**
     * Get LeatBalance amount from source
     *
     * @return float|null
     */
    public function getAmount()
    {
        $source = $this->getSource();
        $order = $this->getOrder();

        if (!$source || !$order) {
            return null;
        }

        $giftcardAmount = null;
        $cards = $this->appliedGiftCardRepository->getByOrderId((int) $order->getId());

        if (empty($cards)) {
            return null;
        }

        foreach ($cards as $card) {
            $giftcardAmount += $card->getBaseAppliedAmount();
        }

        if ($giftcardAmount <= 0) {
            return null;
        }

        return $giftcardAmount > 0 ? -$giftcardAmount : null;
    }

    /**
     * Get leat balance total amount for display in PDF
     *
     * @return array
     */
    public function getTotalsForDisplay(): array
    {
        if (!$this->canDisplay()) {
            return [];
        }

        $amount = $this->getOrder()->formatPriceTxt((float)$this->getAmount());
        $fontSize = $this->getFontSize() ?: 7;

        return [
            [
                'amount' => $amount,
                'label' => __($this->getTitle() ?: 'Leat Gift Card') . ':',
                'font_size' => $fontSize
            ]
        ];
    }
}
