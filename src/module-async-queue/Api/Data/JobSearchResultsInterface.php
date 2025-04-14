<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Api\Data;

interface JobSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{

    /**
     * Get job list.
     * @return \Leat\AsyncQueue\Api\Data\JobInterface[]
     */
    public function getItems(): array;

    /**
     * Set relation_id list.
     * @param \Leat\AsyncQueue\Api\Data\JobInterface[] $items
     * @return $this
     */
    public function setItems(array $items): static;
}
