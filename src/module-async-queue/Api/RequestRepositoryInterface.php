<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface RequestRepositoryInterface
{

    /**
     * Save request
     * @param \Leat\AsyncQueue\Api\Data\RequestInterface $request
     * @return \Leat\AsyncQueue\Api\Data\RequestInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \Leat\AsyncQueue\Api\Data\RequestInterface $request
    ): \Leat\AsyncQueue\Api\Data\RequestInterface;

    /**
     * Retrieve request matching the specified criteria.
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Leat\AsyncQueue\Api\Data\RequestSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ): \Magento\Framework\Api\SearchResults;

    /**
     * Retrieve request matching the given request type
     * @param string $typeCode
     * @return \Leat\AsyncQueue\Api\Data\RequestSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getListForType($typeCode): \Leat\AsyncQueue\Api\Data\RequestSearchResultsInterface;

    /**
     * Delete requestDus
     * @param \Leat\AsyncQueue\Api\Data\RequestInterface $request
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(
        \Leat\AsyncQueue\Api\Data\RequestInterface $request
    ): bool;

    /**
     * Delete request by ID
     * @param string $requestId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($requestId): bool;
}
