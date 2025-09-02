<?php


declare(strict_types=1);

namespace Leat\AsyncQueue\Model\Builder;

use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\AsyncQueue\Api\JobRepositoryInterface;
use Leat\AsyncQueue\Api\RequestRepositoryInterface;
use Leat\AsyncQueue\Model\Job;
use Leat\AsyncQueue\Model\JobFactory;
use Leat\AsyncQueue\Model\Request;
use Leat\AsyncQueue\Model\RequestFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class JobBuilder
{
    protected const string DEFAULT_SOURCE_ID = 'default';

    /**
     * @var string
     */
    protected string $defaultSourceId;

    /**
     * @var int $defaultStoreId
     */
    protected int $defaultStoreId;

    /**
     * @var array
     */
    private array $requests = [];

    /**
     * @var array
     */
    private array $requestHistory = [];

    /**
     * @var ?Job
     */
    private ?Job $currentJob;

    public function __construct(
        protected JobRepositoryInterface $jobRepository,
        protected RequestRepositoryInterface $requestRepository,
        protected JobFactory $jobFactory,
        protected RequestFactory $requestFactory,
        protected StoreManagerInterface $storeManager
    ) {
        $this->_init();
    }

    /**
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function _init(): void
    {
        $this->defaultSourceId = static::DEFAULT_SOURCE_ID;
        $this->defaultStoreId = (int) $this->storeManager->getStore()->getId();
    }

    /**
     * @return void
     */
    protected function reset(): void
    {
        $this->currentJob = null;
        $this->requests = [];
    }

    /**
     * @param string|null $relationId
     * @return $this
     */
    public function newJob(string $relationId = null, int $storeId = null, string $sourceId = null): self
    {
        $this->reset();

        $this->currentJob = $this->jobFactory->create();
        $this->currentJob->setRelationId($relationId);
        $this->setStoreId($storeId ?? $this->getDefaultStoreId());
        $this->setSourceId($sourceId ?? $this->getDefaultSourceId());

        return $this;
    }

    /**
     * @param array $payload
     * @param string $typeCode
     * @return $this
     */
    public function addRequest(array $payload, string $typeCode): self
    {
        $request = $this->requestFactory->create();
        $request->setPayload($payload);
        $request->setTypeCode($typeCode);

        $this->requests[] = $request;

        return $this;
    }

    /**
     * @return JobInterface|null
     * @throws LocalizedException
     */
    public function create(bool $debounce = true): ?JobInterface
    {
        if ($debounce && $this->debounceRequests()) {
            $this->reset();
            return null;
        }

        $job = $this->jobRepository->save($this->currentJob);

        /** @var Request $request */
        foreach ($this->requests as $request) {
            $request->setJobId($job->getJobId());
            $this->requestRepository->save($request);
        }

        $this->reset();

        return $job;
    }

    /**
     * Filter out any duplicate job creation requests
     *
     * @return bool
     */
    private function debounceRequests(): bool
    {
        /** @var Request $request */
        $relationId = $this->currentJob->getRelationId();
        foreach ($this->requests as $key => $request) {
            $hash = md5(serialize($request->getPayload()));
            $typeCode = $request->getTypeCode();
            if (!isset($this->requestHistory[$relationId][$typeCode][$hash])) {
                $this->requestHistory[$relationId][$typeCode][$hash] = true;
            } else {
                unset($this->requests[$key]);
            }
        }

        return empty($this->requests);
    }

    /**
     * Set the source id for the job currently being built
     *
     * @param string $sourceId
     * @return JobBuilder
     */
    public function setSourceId(string $sourceId): self
    {
        $this->currentJob?->setSourceId($sourceId);
        return $this;
    }

    /**
     * Set the store id for the job currently being built
     *
     * @param int $storeId
     * @return JobBuilder
     */
    public function setStoreId(int $storeId): self
    {
        $this->currentJob?->setStoreId($storeId);
        return $this;
    }

    /**
     * Set the store id for this instance of the JobBuilder
     *
     * @param int $storeId
     * @return $this
     */
    public function setDefaultStoreId(int $storeId): self
    {
        $this->defaultStoreId = $storeId;
        return $this;
    }

    /**
     * Set the default source id for this instance of the JobBuilder
     *
     * @param string $sourceId
     * @return $this
     */
    public function setDefaultSourceId(string $sourceId): self
    {
        $this->defaultSourceId = $sourceId;
        return $this;
    }

    public function getDefaultSourceId(): string
    {
        return $this->defaultSourceId;
    }

    public function getDefaultStoreId(): int
    {
        return $this->defaultStoreId;
    }
}
