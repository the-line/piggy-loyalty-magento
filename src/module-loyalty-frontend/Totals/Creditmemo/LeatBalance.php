<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Totals\Creditmemo;

use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Model\Config;
use Magento\Framework\DataObject;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

/**
 * Add leat balance to creditmemo totals
 */
class LeatBalance extends AbstractTotal
{
    /**
     * @param Config $config
     * @param OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository
     * @param array $data
     */
    public function __construct(
        private readonly Config                              $config,
        private readonly OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository,
        array                                                $data = []
    ) {
        parent::__construct($data);
    }

    /**
     * @inheritDoc
     */
    public function collect(CreditmemoInterface $creditmemo): LeatBalance
    {
        if (!$this->config->isPrepaidBalanceEnabled()) {
            return $this;
        }

        $order = $creditmemo->getOrder();
        if (!$order) {
            return $this;
        }

        // Get balance amount from order
        $balanceAmount = $this->orderLeatBalanceRepository->getLeatBalanceAmount($order);

        if ($balanceAmount <= 0) {
            return $this;
        }

        $alreadyRefundedAmount = $this->orderLeatBalanceRepository->getLeatRefundedAmount($order);

        // Check for custom refund amount from form
        if ($creditmemo->getLeatBalanceRefundAmount() !== null) {
            $refundAmount = (float)$creditmemo->getLeatBalanceRefundAmount();

            // Validate refund amount isn't greater than original balance amount
            if ($refundAmount > $balanceAmount) {
                $refundAmount = $balanceAmount;
            }

            // Use validated amount
            $creditmemo->setLeatLoyaltyBalanceAmount($refundAmount);
        } else {
            // If it's a full creditmemo, set the full amount, otherwise calculate proportional amount
            if ($this->isFullRefund($creditmemo, $order)) {
                $creditmemo->setLeatLoyaltyBalanceAmount($balanceAmount - $alreadyRefundedAmount);
                $creditmemo->setGrandTotal($creditmemo->getGrandTotal() - ($balanceAmount - $alreadyRefundedAmount));
                $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() - ($balanceAmount - $alreadyRefundedAmount));
            } else {
                // Calculate proportional amount based on grand total ratio
                $ratio = $creditmemo->getGrandTotal() / ($order->getGrandTotal() + $balanceAmount);
                $proportionalAmount = round($balanceAmount * $ratio, 2);
                $creditmemo->setLeatLoyaltyBalanceAmount($proportionalAmount);
                $creditmemo->setGrandTotal($creditmemo->getGrandTotal() - ($proportionalAmount - $alreadyRefundedAmount));
                $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() - ($proportionalAmount - $alreadyRefundedAmount));
            }
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
