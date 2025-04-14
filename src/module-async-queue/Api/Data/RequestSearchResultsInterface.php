<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Api\Data;

interface RequestSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{

    /**
     * Get request list.
     * @return \Leat\AsyncQueue\Api\Data\RequestInterface[]
     */
    public function getItems(): array;

    /**
     * Set type list.
     * @param \Leat\AsyncQueue\Api\Data\RequestInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static;
}
