<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Adminhtml\Order\Creditmemo\Totals;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order\Creditmemo;

/**
 * Adminhtml credit memo totals prepaid balance block
 */
class LeatGiftcard extends Template
{
    /**
     * @param Context $context
     * @param AppliedGiftCardRepositoryInterface $appliedGiftCardRepository
     * @param array $data
     */
    public function __construct(
        Context                                              $context,
        private readonly AppliedGiftCardRepositoryInterface  $appliedGiftCardRepository,
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
            'code' => 'leat_loyalty_giftcard',
            'strong' => false,
            'value' => -$value,
            'base_value' => -$value,
            'label' => __('Leat Gift Card'),
            'sort_order' => 750
        ]);

        $parent->addTotalBefore($total, 'grand_total');

        if ($this->getSource()->getId()) {
            $label = __('Leat Gift Card amount Refunded');
        } else {
            $label = __('Leat Gift Card amount to Refund');
        }

        $refundTotal = new DataObject([
            'code' => 'leat_loyalty_giftcard_refund',
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

        if ($source->getLeatLoyaltyGiftcardRefunded() !== null) {
            return (float)$source->getLeatLoyaltyGiftcardRefunded();
        }

        return 0.0;
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
