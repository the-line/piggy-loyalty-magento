<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Totals\Invoice;

use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Model\Config;
use Magento\Framework\DataObject;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

/**
 * Add leat balance to invoice totals
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
    public function collect(InvoiceInterface $invoice): LeatBalance
    {
        $order = $invoice->getOrder();
        if (!$order) {
            return $this;
        }

        if (!$this->config->isPrepaidBalanceEnabled((int) $order->getStoreId())) {
            return $this;
        }

        // Get balance amount from order
        $balanceAmount = 0.0;

        // First try from direct data
        if ($order->getData('leat_loyalty_balance_amount') !== null) {
            $balanceAmount = (float)$order->getData('leat_loyalty_balance_amount');
        }

        // Then try from repository
        if ($balanceAmount <= 0) {
            $balanceAmount = $this->orderLeatBalanceRepository->getLeatBalanceAmount($order);
        }

        if ($balanceAmount <= 0) {
            return $this;
        }

        // Set the balance amount on invoice for display
        $grandTotal = abs($invoice->getGrandTotal() - $balanceAmount) < 0.0001
            ? 0 : $invoice->getGrandTotal() - $balanceAmount;
        $baseGrandTotal = abs($invoice->getBaseGrandTotal() - $balanceAmount) < 0.0001
            ? 0 : $invoice->getBaseGrandTotal() - $balanceAmount;
        $invoice->setGrandTotal($grandTotal);
        $invoice->setBaseGrandTotal($baseGrandTotal);

        return $this;
    }
}
