<?php
/**
 * GiftcardItemData
 *
 * Plugin to modify customer data for giftcard items in the mini cart and checkout.
 * This ensures that giftcard-specific options are displayed correctly and
 * configuration options are properly handled.
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Plugin\Giftcard;

use Leat\Loyalty\Helper\GiftcardHelper;
use Magento\Checkout\CustomerData\ItemPool;
use Magento\Quote\Model\Quote\Item;

class GiftcardItemData
{
    /**
     * Constructor
     *
     * @param GiftcardHelper $giftcardHelper Helper class for giftcard-related functionality
     */
    public function __construct(
        protected GiftcardHelper $giftcardHelper
    ) {
    }

    /**
     * After plugin for getItemData to add giftcard-specific options and modify item behavior
     *
     * This method intercepts the result of getItemData() from the ItemPool and modifies
     * the data for giftcard items to:
     * 1. Add giftcard-specific options (recipient info, etc.)
     * 2. Remove the configuration URL since giftcards shouldn't be reconfigured after adding to cart
     * 3. Hide the item from site visibility to prevent direct navigation to the product page
     *
     * @param ItemPool $subject The ItemPool instance
     * @param mixed $result The original result from getItemData()
     * @param Item $item The quote item being processed
     * @return mixed The modified result with giftcard-specific data
     */
    public function afterGetItemData(ItemPool $subject, mixed $result, Item $item)
    {
        if ($this->giftcardHelper->itemIsGiftcard($item)) {
            // Add giftcard options to the item data
            $result['options'] = $this->giftcardHelper->getGiftcardOptions(
                $item,
                ($result['options'] ?? [])
            );
            
            // Remove configure URL to prevent reconfiguration of giftcard after adding to cart
            $result['configure_url'] = null;
            
            // Hide the item from site visibility
            // This prevents direct navigation to the product page from the cart
            $result['is_visible_in_site_visibility'] = false;
        }

        return $result;
    }
}
