<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model;

use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\AsyncQueue\Api\Data\JobInterfaceFactory;
use Leat\AsyncQueue\Api\Data\JobSearchResultsInterface;
use Leat\AsyncQueue\Api\Data\JobSearchResultsInterfaceFactory;
use Leat\AsyncQueue\Api\JobRepositoryInterface;
use Leat\AsyncQueue\Model\ResourceModel\Job;
use Leat\AsyncQueue\Model\ResourceModel\Job as ResourceJob;
use Leat\AsyncQueue\Model\ResourceModel\Job\CollectionFactory as JobCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class JobRepository implements JobRepositoryInterface
{

    /**
     * @var Job
     */
    protected $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @var ResourceJob
     */
    protected $resource;

    /**
     * @var JobInterfaceFactory
     */
    protected $jobFactory;

    /**
     * @var JobCollectionFactory
     */
    protected $jobCollectionFactory;


    /**
     * @param ResourceJob $resource
     * @param JobInterfaceFactory $jobFactory
     * @param JobCollectionFactory $jobCollectionFactory
     * @param JobSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ResourceJob $resource,
        JobInterfaceFactory $jobFactory,
        JobCollectionFactory $jobCollectionFactory,
        JobSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->jobFactory = $jobFactory;
        $this->jobCollectionFactory = $jobCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function save(JobInterface $job): JobInterface
    {
        try {
            $this->resource->save($job);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the job: %1',
                $exception->getMessage()
            ));
        }
        return $job;
    }

    /**
     * @inheritDoc
     */
    public function get($jobId): JobInterface
    {
        $job = $this->jobFactory->create();
        $this->resource->load($job, $jobId);
        if (!$job->getId()) {
            throw new NoSuchEntityException(__('job with id "%1" does not exist.', $jobId));
        }
        return $job;
    }

    /**
     * @inheritDoc
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ): \Magento\Framework\Api\SearchResults {
        $collection = $this->jobCollectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        $items = [];
        foreach ($collection as $model) {
            $items[] = $model;
        }

        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(JobInterface $job): bool
    {
        try {
            $jobModel = $this->jobFactory->create();
            $this->resource->load($jobModel, $job->getJobId());
            $this->resource->delete($jobModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the job: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($jobId): bool
    {
        return $this->delete($this->get($jobId));
    }

    /**
     * Check if the current job does not have any prerequisites that have failed syncing.
     *
     * @param \Leat\AsyncQueue\Model\Job $job
     * @return bool
     */
    public function hasUncompletedParent(JobInterface $job): bool
    {
        if ($job->getRelationId() === null) {
            return false;
        }

        $select = $this->resource->getConnection()->select()->from(
            $this->resource->getConnection()->getTableName('async_queue_job'),
            'job_id'
        )->where(
            'relation_id = ?',
            $job->getRelationId()
        )->where(
            'completed = ?',
            false
        )->where(
            'job_id < ?',
            $job->getJobId()
        );

        return !empty($this->resource->getConnection()->fetchAll($select));
    }
}
