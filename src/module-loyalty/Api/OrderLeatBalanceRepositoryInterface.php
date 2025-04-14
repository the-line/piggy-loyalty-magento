<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Interface for Order Leat Balance Repository
 */
interface OrderLeatBalanceRepositoryInterface
{
    /**
     * Get leat balance amount from order
     *
     * @param OrderInterface $order
     * @return float
     */
    public function getLeatBalanceAmount(OrderInterface $order): float;

    /**
     * Set leat balance amount on order
     *
     * @param OrderInterface $order
     * @param float $amount
     * @return void
     */
    public function setLeatBalanceAmount(OrderInterface $order, float $amount): void;

    /**
     * Get leat balance amount by order ID
     *
     * @param int $orderId
     * @return float
     */
    public function getByOrderId(int $orderId): float;

    /**
     * Get prepaid transaction UUID from order
     *
     * @param OrderInterface $order
     * @return string|null
     */
    public function getPrepaidTransactionUuid(OrderInterface $order): ?string;

    /**
     * Set prepaid transaction UUID on order
     *
     * @param OrderInterface $order
     * @param string $uuid
     * @return void
     */
    public function setPrepaidTransactionUuid(OrderInterface $order, string $uuid): void;

    /**
     * Get leat refunded balance amount from order
     *
     * @param OrderInterface $order
     * @return float
     */
    public function getLeatRefundedAmount(OrderInterface $order): float;

    /**
     * Add to leat refunded balance amount on order
     *
     * @param OrderInterface $order
     * @param float $amount
     * @return void
     */
    public function addToLeatRefundedAmount(OrderInterface $order, float $amount): void;

    /**
     * Get remaining leat balance amount that can be refunded
     *
     * @param OrderInterface $order
     * @return float
     */
    public function getRemainingRefundableAmount(OrderInterface $order): float;
}
