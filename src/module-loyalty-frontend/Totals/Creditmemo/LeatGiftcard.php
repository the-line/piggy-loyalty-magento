<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Totals\Creditmemo;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

/**
 * Add leat balance to creditmemo totals
 */
class LeatGiftcard extends AbstractTotal
{
    /**
     * @param AppliedGiftCardRepositoryInterface $appliedGiftCardRepository
     * @param array $data
     */
    public function __construct(
        private readonly AppliedGiftCardRepositoryInterface  $appliedGiftCardRepository,
        array                                                $data = []
    ) {
        parent::__construct($data);
    }

    /**
     * @inheritDoc
     */
    public function collect(CreditmemoInterface $creditmemo): LeatGiftcard
    {
        $order = $creditmemo->getOrder();

        if (!$order) {
            return $this;
        }

        $giftcardAmount = 0.0;
        $alreadyRefundedAmount = 0.0;

        $cards = $this->appliedGiftCardRepository->getByOrderId((int) $order->getId());

        if (empty($cards)) {
            return $this;
        }

        foreach ($cards as $card) {
            $giftcardAmount += $card->getBaseAppliedAmount();
            $alreadyRefundedAmount += $card->getBaseRefundedAmount();
        }

        if ($giftcardAmount <= 0) {
            return $this;
        }


        // If it's a full creditmemo, set the full amount, otherwise calculate proportional amount
        if ($this->isFullRefund($creditmemo, $order)) {
            $creditmemo->setLeatLoyaltyGiftcardRefunded($giftcardAmount - $alreadyRefundedAmount);
            $creditmemo->setGrandTotal($creditmemo->getGrandTotal() - ($giftcardAmount - $alreadyRefundedAmount));
            $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() - ($giftcardAmount - $alreadyRefundedAmount));
        } else {
            // Calculate proportional amount based on grand total ratio
            $ratio = $creditmemo->getGrandTotal() / ($order->getGrandTotal() + $giftcardAmount);
            $proportionalAmount = round($giftcardAmount * $ratio, 2);
            $creditmemo->setLeatLoyaltyGiftcardRefunded($proportionalAmount);
            $creditmemo->setGrandTotal($creditmemo->getGrandTotal() - ($proportionalAmount - $alreadyRefundedAmount));
            $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() - ($proportionalAmount - $alreadyRefundedAmount));
        }

        return $this;
    }

    /**
     * Check if this is a full refund
     *
     * @param CreditmemoInterface $creditmemo
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return bool
     */
    private function isFullRefund(CreditmemoInterface $creditmemo, $order): bool
    {
        // Get the total qty of items being refunded
        $refundQty = 0;
        foreach ($creditmemo->getItems() as $item) {
            $refundQty += $item->getQty();
        }

        // Get the total qty of items on the order
        $orderQty = 0;
        foreach ($order->getItems() as $item) {
            $orderQty += $item->getQtyOrdered() - $item->getQtyRefunded();
        }

        // If all remaining items are being refunded, this is a full refund
        return abs($refundQty - $orderQty) < 0.0001;
    }
}
