<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api;

/**
 * Interface for fetching currently applied gift cards for a quote.
 */
interface GetAppliedGiftCardsInterface
{
    /**
     * Get all applied gift cards for the specified cart.
     *
     * Each item in the returned array will be an instance of AppliedGiftCardDetailsInterface.
     *
     * @param string $cartId The ID of the cart (quote).
     * @return \Leat\Loyalty\Api\Data\AppliedGiftCardDetailsInterface[] An array of applied gift card DTOs.
     * @throws \Magento\Framework\Exception\NoSuchEntityException If the cart doesn't exist.
     */
    public function get(string $cartId): array;
}
