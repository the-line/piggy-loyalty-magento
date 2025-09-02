<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface for applied gift card search results.
 * @api
 */
interface AppliedGiftCardSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get applied gift card list.
     *
     * @return \Leat\Loyalty\Api\Data\AppliedGiftCardInterface[]
     */
    public function getItems();

    /**
     * Set applied gift card list.
     *
     * @param \Leat\Loyalty\Api\Data\AppliedGiftCardInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
