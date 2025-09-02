<?php
/**
 * GiftcardOrderOptions
 *
 * Plugin to add giftcard-specific options to cart item renderers.
 * This ensures that giftcard details like recipient information are displayed
 * in the cart and checkout pages.
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Plugin;

use Leat\Loyalty\Helper\GiftcardHelper;
use Magento\Checkout\Block\Cart\Item\Renderer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order\Item;

class GiftcardRenderOrderOptions
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
     * After plugin for getProductOptions to add giftcard-specific options
     *
     * This method intercepts the result of getProductOptions() from the cart item renderer
     * and adds giftcard options to the result if the item is a giftcard.
     * These options will then be displayed in the cart and checkout pages.
     *
     * @param Renderer $subject The cart item renderer
     * @param array $result The original result from getProductOptions()
     * @return array The modified result with giftcard options added
     * @throws NoSuchEntityException If the product cannot be found
     */
    public function afterGetProductOptions(Renderer $subject, array $result): array
    {
        $item = $subject->getItem();
        if ($this->giftcardHelper->itemIsGiftcard($item)) {
            // Add giftcard options to the result array
            // Unlike GiftcardItemOrderOptions, this directly replaces the result
            // rather than specifically targeting the 'attributes_info' key
            $result = $this->giftcardHelper->getGiftcardOptions($item, $result);
        }

        return $result;
    }
}
