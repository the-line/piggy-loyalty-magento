<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model;

use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\AsyncQueue\Model\ResourceModel\Request\Collection as RequestCollection;
use Leat\AsyncQueue\Model\ResourceModel\Request\CollectionFactory as RequestCollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

class Job extends AbstractModel implements JobInterface
{
    /**
     * @var RequestCollection
     */
    private RequestCollection $requestCollection;

    /**
     * @var RequestCollectionFactory
     */
    private RequestCollectionFactory $requestCollectionFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    private CustomerRepositoryInterface $customerRepository;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ResourceConnection $resourceConnection
     * @param RequestCollectionFactory $requestCollectionFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        RequestCollectionFactory $requestCollectionFactory,
        CustomerRepositoryInterface $customerRepository,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
        $this->requestCollectionFactory = $requestCollectionFactory;
        $this->customerRepository = $customerRepository;
    }


    /**
     * @inheritDoc
     */
    // phpcs:ignore
    public function _construct()
    {
        $this->_init(\Leat\AsyncQueue\Model\ResourceModel\Job::class);
    }

    /**
     * @inheritDoc
     */
    public function getJobId(): int
    {
        return (int) $this->getData(self::JOB_ID);
    }

    /**
     * @inheritDoc
     */
    public function setJobId(int $jobId): static
    {
        return $this->setData(self::JOB_ID, $jobId);
    }

    /**
     * @inheritDoc
     */
    public function getRelationId(): ?string
    {
        return $this->getData(self::RELATION_ID) ? (string) $this->getData(self::RELATION_ID) : null;
    }

    /**
     * @inheritDoc
     */
    public function setRelationId(?string $relationId): static
    {
        return $this->setData(self::RELATION_ID, $relationId);
    }

    /**
     * @inheritDoc
     */
    public function getSourceId(): ?string
    {
        return $this->getData(self::SOURCE_ID) ? (string) $this->getData(self::SOURCE_ID) : null;
    }

    /**
     * @inheritDoc
     */
    public function setSourceId(string $sourceId): static
    {
        return $this->setData(self::SOURCE_ID, $sourceId);
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): ?int
    {
        return $this->getData(self::STORE_ID) ? (int) $this->getData(self::STORE_ID) : null;
    }

    /**
     * @inheritDoc
     */
    public function setStoreId(int $storeId): static
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getCompleted(): bool
    {
        return (bool) $this->getData(self::COMPLETED);
    }

    /**
     * @inheritDoc
     */
    public function setCompleted(bool $completed): static
    {
        return $this->setData(self::COMPLETED, $completed);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): static
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt): static
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * @return RequestCollection
     */
    public function getRequestCollection(): RequestCollection
    {
        if (!isset($this->requestCollection)) {
            /** @var RequestCollection $collection */
            $collection = $this->requestCollectionFactory->create();
            $collection->addFilter('job_id', $this->getJobId());

            $this->requestCollection = $collection;
        }

        return $this->requestCollection;
    }

    /**
     * If the job is related to a customer, return the customer object
     *
     * @return CustomerInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCustomer(): ?CustomerInterface
    {
        if ($this->getRelationId()) {
            try {
                return $this->customerRepository->getById($this->getRelationId());
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                return null;
            }
        }

        return null;
    }
}
