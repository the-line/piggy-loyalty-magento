<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\Quote\Item;

use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Magento\Quote\Model\Quote\Item\Compare;

/**
 * Plugin to prevent merging of giftcard items by altering option comparison
 */
class CompareOptionsPlugin
{
    /**
     * Prevent merging of giftcard items with different amounts or unique IDs
     *
     * @param Compare $subject
     * @param bool $result
     * @param array $options1
     * @param array $options2
     * @return bool
     */
    public function afterCompareOptions(
        Compare $subject,
        bool $result,
        array $options1,
        array $options2
    ): bool {
        // If the result is already false, respect that
        if (!$result) {
            return false;
        }

        // Check if the items are giftcards
        $isGiftcard1 = $this->isGiftcard($options1);
        $isGiftcard2 = $this->isGiftcard($options2);

        // If only one is a giftcard, they're definitely not the same
        if ($isGiftcard1 !== $isGiftcard2) {
            return false;
        }

        // If both are giftcards, we need to compare amounts and unique IDs
        if ($isGiftcard1 && $isGiftcard2) {
            // Extract info_buyRequest from both option sets
            $buyRequest1 = $this->extractBuyRequest($options1);
            $buyRequest2 = $this->extractBuyRequest($options2);

            foreach ([
                GiftcardResource::BUYREQUEST_OPTION_IS_GIFT,
                GiftcardResource::BUYREQUEST_OPTION_RECIPIENT_EMAIL,
                GiftcardResource::BUYREQUEST_OPTION_RECIPIENT_FIRSTNAME,
                GiftcardResource::BUYREQUEST_OPTION_RECIPIENT_LASTNAME,
                GiftcardResource::BUYREQUEST_OPTION_SENDER_MESSAGE
            ] as $option) {
                // If one has the option and the other doesn't, or they have different values
                if ((isset($buyRequest1[$option]) && !isset($buyRequest2[$option])) ||
                    (!isset($buyRequest1[$option]) && isset($buyRequest2[$option])) ||
                    (isset($buyRequest1[$option]) && isset($buyRequest2[$option]) && $buyRequest1[$option] != $buyRequest2[$option])) {
                    return false;
                }
            }
        }

        return $result;
    }

    /**
     * Check if options are for a giftcard product
     *
     * @param array $options
     * @return bool
     */
    private function isGiftcard(array $options): bool
    {
        $buyRequest = $this->extractBuyRequest($options);

        return isset($buyRequest[GiftcardResource::BUYREQUEST_OPTION_RECIPIENT_EMAIL]);
    }

    /**
     * Extract buyRequest data from options array
     *
     * @param array $options
     * @return array
     */
    private function extractBuyRequest(array $options): array
    {
        foreach ($options as $option) {
            if (isset($option['code']) && $option['code'] === 'info_buyRequest') {
                if (is_array($option['value'])) {
                    return $option['value'];
                }

                $decoded = json_decode($option['value'], true);
                return $decoded ? $decoded : [];
            }
        }

        return [];
    }
}
