<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Totals\Invoice;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

/**
 * Add leat balance to invoice totals
 */
class LeatGiftCard extends AbstractTotal
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
    public function collect(InvoiceInterface $invoice): LeatGiftCard
    {
        $order = $invoice->getOrder();
        if (!$order) {
            return $this;
        }

        $giftcardAmount = 0.0;
        $cards = $this->appliedGiftCardRepository->getByOrderId((int) $order->getId());

        if (empty($cards)) {
            return $this;
        }

        foreach ($cards as $card) {
            $giftcardAmount += $card->getBaseAppliedAmount();
        }

        if ($giftcardAmount <= 0) {
            return $this;
        }

        // Set the balance amount on invoice for display
        $grandTotal = abs($invoice->getGrandTotal() - $giftcardAmount) < 0.0001
            ? 0 : $invoice->getGrandTotal() - $giftcardAmount;
        $baseGrandTotal = abs($invoice->getBaseGrandTotal() - $giftcardAmount) < 0.0001
            ? 0 : $invoice->getBaseGrandTotal() - $giftcardAmount;
        $invoice->setGrandTotal($grandTotal);
        $invoice->setBaseGrandTotal($baseGrandTotal);

        return $this;
    }
}
