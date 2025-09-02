<?php
/**
 * GiftcardOptions
 *
 * Plugin to add giftcard-specific options to product configuration and cart renderers.
 * This ensures that giftcard details like recipient information are displayed
 * in various frontend contexts, particularly for configurable products.
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Plugin\Giftcard;

use Leat\Loyalty\Helper\GiftcardHelper;
use Magento\Catalog\Helper\Product\Configuration;
use Magento\Catalog\Model\Product\Configuration\Item\ItemInterface;
use Magento\ConfigurableProduct\Block\Cart\Item\Renderer\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;

class GiftcardOptions
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
     * After plugin for getOptionList to add giftcard-specific options to configurable products
     *
     * @param Configurable $subject The configurable product renderer
     * @param array $result The original result from getOptionList()
     * @return array The modified result with giftcard options added
     * @throws \Magento\Framework\Exception\NoSuchEntityException If the product cannot be found
     */
    public function afterGetOptionList(Configurable $subject, array $result): array
    {
        $item = $subject->getItem();
        if ($this->giftcardHelper->itemIsGiftcard($item)) {
            // Add giftcard options to the result array
            $result = $this->giftcardHelper->getGiftcardOptions($item, $result);
        }

        return $result;
    }

    /**
     * After plugin for getOptions to add giftcard-specific options to product configuration
     *
     * @param Configuration $subject The product configuration helper
     * @param array $result The original result from getOptions()
     * @param ItemInterface $item The item being configured
     * @return array The modified result with giftcard options added
     * @throws NoSuchEntityException If the product cannot be found
     */
    public function afterGetOptions(Configuration $subject, array $result, ItemInterface $item): array
    {
        if ($this->giftcardHelper->itemIsGiftcard($item)) {
            // Add giftcard options to the result array
            $result = $this->giftcardHelper->getGiftcardOptions($item, $result);
        }

        return $result;
    }
}
