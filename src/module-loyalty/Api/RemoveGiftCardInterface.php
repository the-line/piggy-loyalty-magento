<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api;

use Leat\Loyalty\Api\Data\RemoveGiftCardResultInterface; // This will need to be created

/**
 * Interface for removing an applied gift card from a quote
 * @api
 */
interface RemoveGiftCardInterface
{
    /**
     * Remove an applied gift card from the quote.
     *
     * @param string $cartId The cart ID.
     * @param int $appliedCardId The entity ID of the applied gift card record to remove.
     * @return \Leat\Loyalty\Api\Data\RemoveGiftCardResultInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException If the cart or applied card cannot be found.
     * @throws \Magento\Framework\Exception\CouldNotDeleteException If the card could not be removed.
     */
    public function remove(string $cartId, int $appliedCardId): Data\RemoveGiftCardResultInterface;
}
