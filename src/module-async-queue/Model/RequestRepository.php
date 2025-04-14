<?php

declare(strict_types=1);

namespace Leat\AsyncQueue\Model;

use Leat\AsyncQueue\Api\Data\RequestInterface;
use Leat\AsyncQueue\Api\Data\RequestInterfaceFactory;
use Leat\AsyncQueue\Api\Data\RequestSearchResultsInterface;
use Leat\AsyncQueue\Api\Data\RequestSearchResultsInterfaceFactory;
use Leat\AsyncQueue\Api\RequestRepositoryInterface;
use Leat\AsyncQueue\Model\ResourceModel\Request as ResourceRequest;
use Leat\AsyncQueue\Model\ResourceModel\Request\CollectionFactory as RequestCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class RequestRepository implements RequestRepositoryInterface
{

    /**
     * @var Request
     */
    protected $searchResultsFactory;

    /**
     * @var RequestInterfaceFactory
     */
    protected $requestFactory;

    /**
     * @var RequestCollectionFactory
     */
    protected $requestCollectionFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ResourceRequest
     */
    protected $resource;

    /**
     * @param ResourceRequest $resource
     * @param RequestInterfaceFactory $requestFactory
     * @param RequestCollectionFactory $requestCollectionFactory
     * @param RequestSearchResultsInterfaceFactory $searchResultsFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ResourceRequest $resource,
        RequestInterfaceFactory $requestFactory,
        RequestCollectionFactory $requestCollectionFactory,
        RequestSearchResultsInterfaceFactory $searchResultsFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->requestFactory = $requestFactory;
        $this->requestCollectionFactory = $requestCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @inheritDoc
     */
    public function save(RequestInterface $request): RequestInterface
    {
        try {
            $this->resource->save($request);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the request: %1',
                $exception->getMessage()
            ));
        }
        return $request;
    }

    /**
     * @inheritDoc
     */
    public function get(int $requestId): RequestInterface
    {
        $request = $this->requestFactory->create();
        $this->resource->load($request, $requestId);
        if (!$request->getId()) {
            throw new NoSuchEntityException(__('request with id "%1" does not exist.', $requestId));
        }
        return $request;
    }

    /**
     * @inheritDoc
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ): \Magento\Framework\Api\SearchResults {
        $collection = $this->requestCollectionFactory->create();

        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);

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
    public function getListForType($typeCode): RequestSearchResultsInterface
    {
        return $this->getList($this->searchCriteriaBuilder->addFilter('type_code', $typeCode)->create());
    }

    /**
     * @inheritDoc
     */
    public function delete(RequestInterface $request): bool
    {
        try {
            $requestModel = $this->requestFactory->create();
            $this->resource->load($requestModel, $request->getRequestId());
            $this->resource->delete($requestModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the request: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($requestId): bool
    {
        return $this->delete($this->get($requestId));
    }
}
