<?php
/**
 * HideGiftcardEdit
 *
 * Plugin to control the visibility of the edit action for giftcard products in the cart.
 * This ensures that giftcard products cannot be edited after they've been added to the cart,
 * as changing recipient information after the fact could lead to inconsistencies.
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Plugin\Giftcard;

use Leat\Loyalty\Helper\GiftcardHelper;
use Magento\Checkout\Block\Cart\Item\Renderer\Actions\Generic;
use Magento\Framework\Exception\NoSuchEntityException;

class HideGiftcardEdit
{
    public function __construct(
        protected GiftcardHelper $giftcardHelper
    ) {
    }

    /**
     * After plugin for isProductVisibleInSiteVisibility to control edit action visibility
     *
     * The method name is somewhat confusing - it actually determines if the edit action
     * should be shown, not just if the product is visible on the site.
     *
     * @param Generic $subject The cart item action renderer
     * @param bool $result The original result from isProductVisibleInSiteVisibility()
     * @return bool False for giftcard products (to hide edit action), original result for others
     * @throws NoSuchEntityException If the product cannot be found
     */
    public function afterIsProductVisibleInSiteVisibility(Generic $subject, bool $result): bool
    {
        // If the product is already not visible/editable, keep it that way
        if (!$result) {
            return false;
        }

        // For giftcard products, we want to return the opposite of the itemIsGiftcard result
        // If it is a giftcard, we return false (hide edit action)
        // If it's not a giftcard, we return true (show edit action)
        return !$this->giftcardHelper->itemIsGiftcard($subject->getItem());
    }
}
