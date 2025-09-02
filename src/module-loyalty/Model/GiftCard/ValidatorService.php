<?php
declare(strict_types=1);

namespace Leat\Loyalty\Model\GiftCard;

use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Piggy\Api\Models\Giftcards\Giftcard;

class ValidatorService
{
    public function __construct(protected GiftcardResource $giftcardResource)
    {
    }

    /**
     * @param string|Giftcard $giftCardCode
     * @param int|null $storeId
     * @return bool
     */
    public function isValid(string|Giftcard $giftCardCode, ?int $storeId = null): bool
    {
        if (is_string($giftCardCode)) {
            try {
                $giftCard = $this->giftcardResource->findGiftcardByHash($giftCardCode, $storeId);
            } catch (\Exception) {
                //A card that is not found throws an exception, so just catch it and return false.
                return false;
            }
        } else {
            $giftCard = $giftCardCode;
        }

        return $giftCard->isActive() &&
            ($giftCard->getExpirationDate() === null || $giftCard->getExpirationDate()->format('Y-m-d') > date('Y-m-d')) &&
            $giftCard->getAmountInCents() > 0;
    }

    /**
     * @param string|Giftcard $giftCardCode
     * @param int|null $storeId
     * @return float
     */
    public function getAvailableBalance(string|Giftcard $giftCardCode, ?int $storeId = null): float
    {
        if (is_string($giftCardCode)) {
            try {
                $giftCard = $this->giftcardResource->findGiftcardByHash($giftCardCode, $storeId);
            } catch (\Exception) {
                //A card that is not found throws an exception, so just catch it and return false.
                return 0.0;
            }
        } else {
            $giftCard = $giftCardCode;
        }

        return $giftCard->getAmountInCents() / 100;
    }

    /**
     * @param string $giftCardCode
     * @param int|null $storeId
     * @return Giftcard|null
     */
    public function getCard(string $giftCardCode, ?int $storeId = null): ?Giftcard
    {
        try {
            return $this->giftcardResource->findGiftcardByHash($giftCardCode, $storeId);
        } catch (\Exception) {
            return null;
        }
    }
}
