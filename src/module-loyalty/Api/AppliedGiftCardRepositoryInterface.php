<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api;

use Leat\Loyalty\Api\Data\AppliedGiftCardInterface;
use Leat\Loyalty\Api\Data\AppliedGiftCardSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Interface AppliedGiftCardRepositoryInterface
 * @api
 */
interface AppliedGiftCardRepositoryInterface
{
    /**
     * Save applied gift card.
     *
     * @param AppliedGiftCardInterface $appliedGiftCard
     * @return AppliedGiftCardInterface
     * @throws CouldNotSaveException
     */
    public function save(AppliedGiftCardInterface $appliedGiftCard): AppliedGiftCardInterface;

    /**
     * Retrieve applied gift card by ID.
     *
     * @param int $entityId
     * @return AppliedGiftCardInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): AppliedGiftCardInterface;

    /**
     * Retrieve applied gift cards matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return AppliedGiftCardSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): AppliedGiftCardSearchResultsInterface;

    /**
     * Delete applied gift card.
     *
     * @param AppliedGiftCardInterface $appliedGiftCard
     * @return bool true on success
     * @throws CouldNotDeleteException
     */
    public function delete(AppliedGiftCardInterface $appliedGiftCard): bool;

    /**
     * Delete applied gift card by ID.
     *
     * @param int $entityId
     * @return bool true on success
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $entityId): bool;

    /**
     * Retrieve applied gift cards by quote ID.
     *
     * @param int $quoteId
     * @return AppliedGiftCardInterface[]
     */
    public function getByQuoteId(int $quoteId): array;

    /**
     * Retrieve applied gift card by quote ID and gift card code.
     *
     * @param int $quoteId
     * @param string $giftCardCode
     * @return AppliedGiftCardInterface|null
     */
    public function getByQuoteAndCard(int $quoteId, string $giftCardCode): ?AppliedGiftCardInterface;

    /**
     * Retrieve applied gift cards by order ID.
     *
     * @param int $orderId
     * @return AppliedGiftCardInterface[]
     */
    public function getByOrderId(int $orderId): array;

    /**
     * Get remaining leat giftcard amount that can be refunded
     *
     * @param OrderInterface $order
     * @return float
     */
    public function getRemainingRefundableAmount(OrderInterface $order): float;
}
