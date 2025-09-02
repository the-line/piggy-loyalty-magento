<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\Quote;

use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Leat\Loyalty\Observer\AddGiftcardOptionsToQuoteItem;
use Magento\Quote\Model\Quote\Item;
use Magento\Catalog\Model\Product;

/**
 * Plugin to prevent merging of gift card items in the cart
 */
class PreventGiftcardMergePlugin
{
    /**
     * Prevent gift cards from merging by returning false from representProduct
     * This is called by Magento\Quote\Model\Quote::getItemByProduct when checking for duplicate items
     *
     * @param Item $subject
     * @param bool $result
     * @param Product $product
     * @return bool
     */
    public function afterRepresentProduct(
        Item $subject,
        bool $result,
        Product $product
    ): bool {
        // If the current result is already false or this isn't a gift card, respect the original result
        if (!$result || !$this->isGiftcard($subject)) {
            return $result;
        }

        // For gift cards, we need to compare buy requests to ensure they're truly identical
        $newBuyRequest = $product->getCustomOption('info_buyRequest');
        $itemBuyRequest = $this->getBuyRequest($subject);

        if ($newBuyRequest) {
            $newBuyRequestValue = is_array($newBuyRequest->getValue())
                ? $newBuyRequest->getValue()
                : json_decode($newBuyRequest->getValue(), true);

            foreach ([$newBuyRequestValue, $itemBuyRequest] as &$buyRequest) {
                unset($buyRequest['uenc']);
                unset($buyRequest['qty']);
            }

            $newBuyRequestHash = md5(serialize($newBuyRequestValue));
            $itemBuyRequestHash = md5(serialize($itemBuyRequest));

            $result = $newBuyRequestHash === $itemBuyRequestHash;
        }

        return $result;
    }

    /**
     * Check if the item is a gift card
     *
     * @param Item $item
     * @return bool
     */
    private function isGiftcard(Item $item): bool
    {
        $buyRequest = $this->getBuyRequest($item);

        return isset($buyRequest[GiftcardResource::BUYREQUEST_OPTION_RECIPIENT_EMAIL]);
    }

    /**
     * Get buy request data from item
     *
     * @param Item $item
     * @return array
     */
    private function getBuyRequest(Item $item): array
    {
        $option = $item->getOptionByCode('info_buyRequest');
        if ($option) {
            if (is_array($option->getValue())) {
                return $option->getValue();
            } else {
                $decoded = json_decode($option->getValue(), true);
                return $decoded ? $decoded : [];
            }
        }

        return [];
    }
}
