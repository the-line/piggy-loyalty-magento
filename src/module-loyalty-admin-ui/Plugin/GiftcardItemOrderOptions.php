<?php
/**
 * GiftcardOrderOptions
 *
 * Plugin to add giftcard-specific options to order items in the admin panel.
 * This ensures that giftcard details like recipient information are displayed
 * in the admin order view.
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Plugin;

use Leat\Loyalty\Helper\GiftcardHelper;
use Magento\Checkout\Block\Cart\Item\Renderer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Block\Adminhtml\Items\Column\DefaultColumn;
use Magento\Sales\Block\Order\Email\Items\Order\DefaultOrder;
use Magento\Sales\Model\Order\Item;

class GiftcardItemOrderOptions
{
    /**
     * Flag to prevent infinite recursion when processing options
     * This is needed because getBuyRequest() can trigger getProductOptionByCode()
     */
    protected bool $isProcessing = false;

    public function __construct(
        protected GiftcardHelper $giftcardHelper
    ) {
    }

    /**
     * After plugin for getProductOptions to add giftcard-specific options
     * These options will then be displayed in the admin order view.
     *
     * @param Item $item The order item being processed
     * @param array $result The original result from getProductOptions()
     * @return array The modified result with giftcard options added
     * @throws NoSuchEntityException If the product cannot be found
     */
    public function afterGetProductOptions(Item $item, array $result): array
    {
        // Prevent infinite recursion if this method is called during option processing
        if ($this->isProcessing) {
            return $result;
        }

        // Only add giftcard options if the item is actually a giftcard
        if ($this->giftcardHelper->itemIsGiftcard($item)) {
            // Add giftcard options to the attributes_info section
            // Initialize attributes_info as an empty array if it doesn't exist
            $attributesInfo = $result['attributes_info'] ?? [];
            $result['attributes_info'] = $this->giftcardHelper->getGiftcardOptions($item, $attributesInfo);
        }

        return $result;
    }

    /**
     * Around plugin for getProductOptionByCode to prevent infinite recursion
     *
     * When getBuyRequest() is called in the GiftcardHelper::getGiftcardOptions method,
     * it internally calls getProductOptionByCode(), which could then trigger our
     * afterGetProductOptions plugin again, causing an infinite loop.
     *
     * This around plugin sets a flag to prevent that recursion.
     *
     * @param Item $subject The order item being processed
     * @param callable $proceed The original getProductOptionByCode method
     * @param string|null $code The option code to retrieve
     * @return mixed The result of the original method
     */
    public function aroundGetProductOptionByCode(Item $subject, callable $proceed, string $code = null): mixed
    {
        // Set flag to prevent our afterGetProductOptions from processing during this call
        $this->isProcessing = true;

        // Call the original method
        $result = $proceed($code);

        // Reset the flag after processing is complete
        $this->isProcessing = false;

        return $result;
    }
}
