<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\GiftCard;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Leat\Loyalty\Api\Data\AppliedGiftCardInterface;
use Leat\Loyalty\Api\Data\AppliedGiftCardInterfaceFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Quote\Api\CartRepositoryInterface;

class ApplicationService
{
    public const LOGGER_PURPOSE = 'giftcard_redemption';

    private AppliedGiftCardRepositoryInterface $appliedGiftCardRepository;
    private AppliedGiftCardInterfaceFactory $appliedGiftCardFactory;
    private CartRepositoryInterface $cartRepository;

    public function __construct(
        AppliedGiftCardRepositoryInterface $appliedGiftCardRepository,
        AppliedGiftCardInterfaceFactory $appliedGiftCardFactory,
        CartRepositoryInterface $cartRepository
    ) {
        $this->appliedGiftCardRepository = $appliedGiftCardRepository;
        $this->appliedGiftCardFactory = $appliedGiftCardFactory;
        $this->cartRepository = $cartRepository;
    }

    /**
     * Applies a gift card to the specified quote or updates an existing application.
     * Stores the gift card code and its balance at the time of application.
     * The actual applied amount will be determined by a total collector.
     *
     * @param int $quoteId
     * @param string $giftCardCode
     * @param float $originalCardBalance The balance of the gift card at the time of application.
     * @return AppliedGiftCardInterface
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function applyToQuote(int $quoteId, string $giftCardCode, float $originalCardBalance): AppliedGiftCardInterface
    {
        $appliedGiftCard = $this->appliedGiftCardRepository->getByQuoteAndCard($quoteId, $giftCardCode);

        if ($appliedGiftCard === null) {
            $appliedGiftCard = $this->appliedGiftCardFactory->create();
            $appliedGiftCard->setQuoteId($quoteId);
            $appliedGiftCard->setGiftCardCode($giftCardCode);
        }

        // Store the card's balance at the time of this application/update
        // applied_amount and base_applied_amount will be set by a total collector
        $appliedGiftCard->setBalance($originalCardBalance);

        return $this->appliedGiftCardRepository->save($appliedGiftCard);
    }

    /**
     * Removes an applied gift card from the quote.
     *
     * @param int $quoteId
     * @param int $appliedGiftCardEntityId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function removeFromQuote(int $quoteId, int $appliedGiftCardEntityId): bool
    {
        $appliedGiftCard = $this->appliedGiftCardRepository->getById($appliedGiftCardEntityId);
        if ($appliedGiftCard->getQuoteId() !== $quoteId) {
            throw new NoSuchEntityException(
                __('Applied gift card with ID "%1" does not belong to quote ID "%2".', $appliedGiftCardEntityId, $quoteId)
            );
        }
        return $this->appliedGiftCardRepository->deleteById($appliedGiftCardEntityId);
    }
}
