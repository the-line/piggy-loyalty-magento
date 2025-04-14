<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\Quote\CartItem;

use Leat\Loyalty\Model\QuoteItem\ExtensionAttributesFactory;
use Leat\Loyalty\Model\QuoteItem\ExtensionAttributesRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\Data\CartItemExtensionFactory;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Option;

class ExtensionAttributesPlugin
{
    /**
     * @var CartItemExtensionFactory
     */
    private $extensionFactory;

    /**
     * @var ExtensionAttributesFactory
     */
    private $quoteItemExtensionFactory;

    /**
     * @var ExtensionAttributesRepository
     */
    private $extensionRepository;

    /**
     * @param CartItemExtensionFactory $extensionFactory
     * @param ExtensionAttributesFactory $quoteItemExtensionFactory
     * @param ExtensionAttributesRepository $extensionRepository
     */
    public function __construct(
        CartItemExtensionFactory $extensionFactory,
        ExtensionAttributesFactory $quoteItemExtensionFactory,
        ExtensionAttributesRepository $extensionRepository
    ) {
        $this->extensionFactory = $extensionFactory;
        $this->quoteItemExtensionFactory = $quoteItemExtensionFactory;
        $this->extensionRepository = $extensionRepository;
    }

    /**
     * Add extension attributes to cart item after it's loaded
     *
     * @param CartItemRepositoryInterface $subject
     * @param CartItemInterface $cartItem
     * @return CartItemInterface
     */
    public function afterGet(
        CartItemRepositoryInterface $subject,
        CartItemInterface $cartItem
    ): CartItemInterface {
        $this->processExtensionAttributes($cartItem);
        return $cartItem;
    }

    /**
     * Add extension attributes to cart items after they're loaded
     *
     * @param CartItemRepositoryInterface $subject
     * @param array $items
     * @return array
     */
    public function afterGetList(
        CartItemRepositoryInterface $subject,
        array $items
    ): array {
        foreach ($items as $item) {
            $this->processExtensionAttributes($item);
        }

        return $items;
    }

    /**
     * Process extension attributes for cart item
     *
     * @param CartItemInterface $item
     * @return void
     */
    private function processExtensionAttributes(CartItemInterface $item): void
    {
        $extensionAttributes = $item->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->extensionFactory->create();
            $item->setExtensionAttributes($extensionAttributes);
        }

        $itemId = $item->getItemId();
        if (!$itemId) {
            return;
        }

        try {
            // Try to load from the database
            $quoteItemExtension = $this->extensionRepository->getByItemId((int)$itemId);

            // Set extension attributes from the database record
            $extensionAttributes->setIsGift($quoteItemExtension->getIsGift());
            $extensionAttributes->setGiftRuleId($quoteItemExtension->getGiftRuleId());
            $extensionAttributes->setOriginalProductSku($quoteItemExtension->getOriginalProductSku());
            return;
        } catch (NoSuchEntityException $e) {
            // If no record exists, fall back to options/direct properties
        }

        // Fallback to item properties and options

        // Process is_gift attribute
        $isGift = $item->getIsGift();
        $extensionAttributes->setIsGift((bool)$isGift);

        // Process gift_rule_id attribute from item data or option
        $giftRuleId = $item->getGiftRuleId();
        if ($giftRuleId === null && $item instanceof Item) {
            $ruleOption = $item->getOptionByCode('rule_id');
            if ($ruleOption instanceof Option) {
                $giftRuleId = (int)$ruleOption->getValue();
            }
        }
        $extensionAttributes->setGiftRuleId($giftRuleId);

        // Process original_product_sku attribute from item data or option
        $originalSku = $item->getOriginalProductSku();
        if ($originalSku === null && $item instanceof Item) {
            $skuOption = $item->getOptionByCode('original_product_sku');
            if ($skuOption instanceof Option) {
                $originalSku = $skuOption->getValue();
            }
        }
        $extensionAttributes->setOriginalProductSku($originalSku);
    }

    /**
     * Save extension attributes before cart item is saved
     *
     * @param CartItemRepositoryInterface $subject
     * @param CartItemInterface $cartItem
     * @return array
     */
    public function beforeSave(
        CartItemRepositoryInterface $subject,
        CartItemInterface $cartItem
    ): array {
        $extensionAttributes = $cartItem->getExtensionAttributes();
        if ($extensionAttributes !== null) {
            // Save extension attributes to the database
            if ($extensionAttributes->getIsGift() !== null && $cartItem->getItemId()) {
                $this->saveExtensionAttributes(
                    (int)$cartItem->getItemId(),
                    (bool)$extensionAttributes->getIsGift(),
                    $extensionAttributes->getGiftRuleId(),
                    $extensionAttributes->getOriginalProductSku()
                );
            }

            // Also set on the item for backward compatibility
            if ($extensionAttributes->getIsGift() !== null) {
                $cartItem->setIsGift($extensionAttributes->getIsGift());
            }

            if ($extensionAttributes->getGiftRuleId() !== null) {
                $cartItem->setGiftRuleId($extensionAttributes->getGiftRuleId());
            }

            if ($extensionAttributes->getOriginalProductSku() !== null) {
                $cartItem->setOriginalProductSku($extensionAttributes->getOriginalProductSku());
            }
        }

        return [$cartItem];
    }

    /**
     * Save extension attributes to the database
     *
     * @param int $itemId
     * @param bool $isGift
     * @param int|null $giftRuleId
     * @param string|null $originalSku
     * @return void
     */
    private function saveExtensionAttributes(
        int $itemId,
        bool $isGift,
        ?int $giftRuleId,
        ?string $originalSku
    ): void {
        try {
            try {
                $extensionAttributes = $this->extensionRepository->getByItemId($itemId);
            } catch (NoSuchEntityException $e) {
                $extensionAttributes = $this->quoteItemExtensionFactory->create();
                $extensionAttributes->setItemId($itemId);
            }

            $extensionAttributes->setIsGift($isGift);
            $extensionAttributes->setGiftRuleId($giftRuleId);
            $extensionAttributes->setOriginalProductSku($originalSku);

            $this->extensionRepository->save($extensionAttributes);
        } catch (\Exception $e) {
            // Log error but continue
        }
    }

    /**
     * Remove extension attributes after item is deleted
     *
     * @param CartItemRepositoryInterface $subject
     * @param callable $proceed
     * @param int $itemId
     * @return bool
     */
    public function aroundDeleteById(
        CartItemRepositoryInterface $subject,
        callable $proceed,
        $itemId
    ) {
        $result = $proceed($itemId);

        if ($result) {
            // Clean up extension attributes
            try {
                $this->extensionRepository->deleteByItemId((int)$itemId);
            } catch (\Exception $e) {
                // Log error but continue
            }
        }

        return $result;
    }
}
