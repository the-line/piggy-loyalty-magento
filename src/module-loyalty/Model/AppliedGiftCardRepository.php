<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Leat\Loyalty\Api\Data\AppliedGiftCardInterface;
use Leat\Loyalty\Api\Data\AppliedGiftCardInterfaceFactory;
use Leat\Loyalty\Api\Data\AppliedGiftCardSearchResultsInterface;
use Leat\Loyalty\Api\Data\AppliedGiftCardSearchResultsInterfaceFactory;
use Leat\Loyalty\Model\ResourceModel\AppliedGiftCard as AppliedGiftCardResource;
use Leat\Loyalty\Model\ResourceModel\AppliedGiftCard\CollectionFactory as AppliedGiftCardCollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;

class AppliedGiftCardRepository implements AppliedGiftCardRepositoryInterface
{
    private AppliedGiftCardResource $resource;
    private AppliedGiftCardInterfaceFactory $appliedGiftCardFactory;
    private AppliedGiftCardCollectionFactory $appliedGiftCardCollectionFactory;
    private AppliedGiftCardSearchResultsInterfaceFactory $searchResultsFactory;
    private CollectionProcessorInterface $collectionProcessor;

    public function __construct(
        AppliedGiftCardResource $resource,
        AppliedGiftCardInterfaceFactory $appliedGiftCardFactory,
        AppliedGiftCardCollectionFactory $appliedGiftCardCollectionFactory,
        AppliedGiftCardSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->appliedGiftCardFactory = $appliedGiftCardFactory;
        $this->appliedGiftCardCollectionFactory = $appliedGiftCardCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function save(AppliedGiftCardInterface $appliedGiftCard): AppliedGiftCardInterface
    {
        try {
            $this->resource->save($appliedGiftCard);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the applied gift card: %1',
                $exception->getMessage()
            ));
        }
        return $appliedGiftCard;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $entityId): AppliedGiftCardInterface
    {
        /** @var AppliedGiftCardInterface $appliedGiftCard */
        $appliedGiftCard = $this->appliedGiftCardFactory->create();
        $this->resource->load($appliedGiftCard, $entityId);
        if (!$appliedGiftCard->getId()) {
            throw new NoSuchEntityException(__('Applied gift card with id "%1" does not exist.', $entityId));
        }
        return $appliedGiftCard;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): AppliedGiftCardSearchResultsInterface
    {
        $collection = $this->appliedGiftCardCollectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var AppliedGiftCardSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(AppliedGiftCardInterface $appliedGiftCard): bool
    {
        try {
            $this->resource->delete($appliedGiftCard);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the applied gift card: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->getById($entityId));
    }

    /**
     * @inheritDoc
     */
    public function getByQuoteId(int $quoteId): array
    {
        $collection = $this->appliedGiftCardCollectionFactory->create();
        $collection->addFieldToFilter(AppliedGiftCardInterface::QUOTE_ID, $quoteId);
        return $collection->getItems();
    }

    /**
     * @param int $quoteId
     * @param string $giftCardCode
     * @return AppliedGiftCardInterface|null
     */
    public function getByQuoteAndCard(int $quoteId, string $giftCardCode): ?AppliedGiftCardInterface
    {
        $collection = $this->appliedGiftCardCollectionFactory->create();
        $collection->addFieldToFilter(AppliedGiftCardInterface::QUOTE_ID, $quoteId);
        $collection->addFieldToFilter(AppliedGiftCardInterface::GIFT_CARD_CODE, $giftCardCode);
        $items = $collection->getItems();

        if (empty($items)) {
            return null;
        }

        return reset($items);
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(int $orderId): array
    {
        $collection = $this->appliedGiftCardCollectionFactory->create();
        $collection->addFieldToFilter(AppliedGiftCardInterface::ORDER_ID, $orderId);
        return $collection->getItems();
    }

    /**
     * @param OrderInterface $order
     * @return float
     */
    public function getRemainingRefundableAmount(OrderInterface $order): float
    {
        $originalAmount = 0;
        $refundedAmount = 0;

        $cards = $this->getByOrderId((int) $order->getId());

        foreach ($cards as $card) {
            $originalAmount += $card->getBaseAppliedAmount();
            $refundedAmount += $card->getBaseRefundedAmount();
        }


        $remaining = $originalAmount - $refundedAmount;
        return max(0.0, $remaining);
    }
}
