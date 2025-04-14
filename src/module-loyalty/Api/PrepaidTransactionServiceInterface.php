<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Service interface for creating prepaid transactions in Leat
 */
interface PrepaidTransactionServiceInterface
{
    /**
     * Create a prepaid transaction in Leat for the given order
     *
     * @param OrderInterface $order The order to create the transaction for
     * @param float $amount The amount to deduct from the customer's prepaid balance
     * @return string|null The UUID of the created transaction, or null if creation failed
     */
    public function createPrepaidTransaction(OrderInterface $order, float $amount): ?string;

    /**
     * Refund a prepaid transaction in Leat for the given order
     *
     * @param OrderInterface $order The order containing the transaction to refund
     * @param float $amount The amount to refund (can be partial)
     * @return bool Whether the refund was successful
     */
    public function refundPrepaidTransaction(OrderInterface $order, float $amount): bool;
}
