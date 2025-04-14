<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface JobRepositoryInterface
{

    /**
     * Save job
     * @param \Leat\AsyncQueue\Api\Data\JobInterface $job
     * @return \Leat\AsyncQueue\Api\Data\JobInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \Leat\AsyncQueue\Api\Data\JobInterface $job
    ): \Leat\AsyncQueue\Api\Data\JobInterface;

    /**
     * Retrieve job
     * @param string $jobId
     * @return \Leat\AsyncQueue\Api\Data\JobInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get($jobId): \Leat\AsyncQueue\Api\Data\JobInterface;

    /**
     * Retrieve job matching the specified criteria.
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Magento\Framework\Api\SearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ): \Magento\Framework\Api\SearchResults;

    /**
     * Delete job
     * @param \Leat\AsyncQueue\Api\Data\JobInterface $job
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(
        \Leat\AsyncQueue\Api\Data\JobInterface $job
    ): bool;

    /**
     * Delete job by ID
     * @param string $jobId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($jobId): bool;

    /**
     * Check if the job has uncompleted parent jobs
     * @param \Leat\AsyncQueue\Api\Data\JobInterface $job
     * @return bool
     */
    public function hasUncompletedParent(\Leat\AsyncQueue\Api\Data\JobInterface $job): bool;
}
