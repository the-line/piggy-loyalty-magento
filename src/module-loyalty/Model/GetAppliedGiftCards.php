<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Leat\Loyalty\Api\GetAppliedGiftCardsInterface;
use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Leat\Loyalty\Api\Data\AppliedGiftCardInterface;
use Leat\Loyalty\Api\Data\AppliedGiftCardDetailsInterfaceFactory; // Added
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Quote\Api\CartRepositoryInterface;

class GetAppliedGiftCards implements GetAppliedGiftCardsInterface
{
    public function __construct(
        private readonly AppliedGiftCardRepositoryInterface $appliedGiftCardRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly PricingHelper $pricingHelper,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly AppliedGiftCardDetailsInterfaceFactory $appliedGiftCardDetailsFactory // Added
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(string $cartId): array
    {
        $this->quoteRepository->getActive((int)$cartId); // Ensures quote exists

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(AppliedGiftCardInterface::QUOTE_ID, $cartId)
            ->create();

        $appliedCardsCollection = $this->appliedGiftCardRepository->getList($searchCriteria);
        $result = [];

        /** @var AppliedGiftCardInterface $appliedCard */
        foreach ($appliedCardsCollection->getItems() as $appliedCard) {
            $amountToFormat = $appliedCard->getBalance(); // Default to original balance
            if ($appliedCard->getAppliedAmount() !== null && $appliedCard->getAppliedAmount() > 0) {
                // Prefer actual applied amount if set by a collector
                $amountToFormat = $appliedCard->getAppliedAmount();
            }

            /** @var \Leat\Loyalty\Api\Data\AppliedGiftCardDetailsInterface $detailsDto */
            $detailsDto = $this->appliedGiftCardDetailsFactory->create();
            $detailsDto->setId((int) $appliedCard->getId());
            $detailsDto->setMaskedCode('••••' . substr((string)$appliedCard->getGiftCardCode(), -4));
            $detailsDto->setAppliedAmountFormatted(
                $this->pricingHelper->currency((float)$amountToFormat, true, false)
            );
            $result[] = $detailsDto;
        }
        return $result;
    }
}
