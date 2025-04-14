<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\SalesRule;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Logger;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\OptionFactory;
use Magento\Quote\Api\Data\CartItemExtensionFactory;
use Leat\Loyalty\Model\QuoteItem\ExtensionAttributesFactory;
use Leat\Loyalty\Model\QuoteItem\ExtensionAttributesRepository;
use Magento\Quote\Model\Quote\QuantityCollector;

class GiftProductManager
{
    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ConfigurableProductResolver $configurableProductResolver
     * @param OptionFactory $itemOptionFactory
     * @param CartItemExtensionFactory $extensionFactory
     * @param ExtensionAttributesFactory $itemExtensionFactory
     * @param ExtensionAttributesRepository $extensionRepository
     * @param QuantityCollector $quantityCollector
     * @param Connector $leatConnector
     */
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected ConfigurableProductResolver $configurableProductResolver,
        protected OptionFactory $itemOptionFactory,
        protected CartItemExtensionFactory $extensionFactory,
        protected ExtensionAttributesFactory $itemExtensionFactory,
        protected ExtensionAttributesRepository $extensionRepository,
        protected QuantityCollector $quantityCollector,
        protected Connector $leatConnector
    ) {
        $this->logger = $this->leatConnector->getLogger('reward');
    }

    /**
     * Get extension attributes for a cart item
     *
     * @param CartItemInterface $item
     * @return \Magento\Quote\Api\Data\CartItemExtensionInterface
     */
    private function getExtensionAttributes(CartItemInterface $item)
    {
        $extensionAttributes = $item->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->extensionFactory->create();
            $item->setExtensionAttributes($extensionAttributes);
        }

        return $extensionAttributes;
    }

    /**
     * Add gift products to the quote based on SKUs
     *
     * @param Quote $quote
     * @param int $ruleId
     * @param string $skuString Comma-separated SKUs
     * @param int $maxQty Maximum number of gift items to add
     * @param float $discountPercent Percentage discount to apply
     * @return void
     * @throws LocalizedException
     */
    public function addGiftProductsToQuote(
        Quote $quote,
        int $ruleId,
        string $skuString,
        int $maxQty = 1,
        float $discountPercent = 100.0
    ): void {
        if (empty($skuString)) {
            return;
        }

        $skus = $this->parseSkuString($skuString);
        $itemsAdded = 0;

        $this->logger->debug(sprintf(
            'Adding gift products for rule %d, max qty: %d, discount: %f%%',
            $ruleId,
            $maxQty,
            $discountPercent
        ));

        foreach ($skus as $sku) {
            // Check if we've reached the maximum quantity
            if ($itemsAdded >= $maxQty) {
                $this->logger->debug('Reached maximum gift quantity, stopping');
                break;
            }

            try {
                $this->addSingleGiftProduct($quote, $ruleId, trim($sku), $discountPercent);
                $itemsAdded++;
            } catch (\Exception $e) {
                $this->logger->log(sprintf(
                    'Failed to add gift product %s for rule %d: %s',
                    $sku,
                    $ruleId,
                    $e->getMessage()
                ));
            }
        }

        $this->quantityCollector->collectItemsQtys($quote);
    }

    /**
     * Parse comma-separated SKU string into array
     *
     * @param string $skuString
     * @return array
     */
    private function parseSkuString(string $skuString): array
    {
        return array_filter(array_map('trim', explode(',', $skuString)));
    }

    /**
     * Add a single gift product to the quote
     *
     * @param Quote $quote
     * @param int $ruleId
     * @param string $sku
     * @param float $discountPercent Percentage discount to apply (0-100)
     * @return void
     * @throws LocalizedException
     */
    private function addSingleGiftProduct(Quote $quote, int $ruleId, string $sku, float $discountPercent = 100.0): void
    {
        try {
            $product = $this->productRepository->get($sku);

            // Check if this is a configurable product child
            if ($product->getTypeId() === 'simple') {
                $configurableData = $this->configurableProductResolver->getConfigurableProductData($product);

                if ($configurableData !== null) {
                    $this->addConfigurableGiftProduct(
                        $quote,
                        $ruleId,
                        $configurableData['parent_product'],
                        $configurableData['super_attribute'],
                        $sku,
                        $discountPercent
                    );
                    return;
                }
            }

            $this->addSimpleGiftProduct($quote, $ruleId, $product, $sku, $discountPercent);
        } catch (\Exception $e) {
            throw new LocalizedException(__('Could not add gift product %1: %2', $sku, $e->getMessage()));
        }
    }

    /**
     * Add a simple product as a gift
     *
     * @param Quote $quote
     * @param int $ruleId
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param string $originalSku
     * @param float $discountPercent Percentage discount to apply (0-100)
     * @return CartItemInterface
     * @throws LocalizedException
     */
    private function addSimpleGiftProduct(
        Quote $quote,
        int $ruleId,
        \Magento\Catalog\Api\Data\ProductInterface $product,
        string $originalSku,
        float $discountPercent = 100.0
    ): CartItemInterface {
        // Check if the gift is already in the cart
        foreach ($quote->getAllItems() as $item) {
            // First check by data flag for this request (faster)
            $isFlaggedGift = $item->getData('is_gift_from_rule_' . $ruleId) === true;

            // Then check by stored extension attributes
            $isStoredGift = $this->isGiftFromRule($item, $ruleId);

            if (($isFlaggedGift || $isStoredGift) && $item->getSku() === $product->getSku()) {
                $this->logger->debug(sprintf(
                    'Gift product %s from rule %d already exists in cart, skipping addition',
                    $product->getSku(),
                    $ruleId
                ));
                return $item;
            }
        }

        $quoteItem = $quote->addProduct($product, 1);

        if (is_string($quoteItem)) {
            throw new LocalizedException(__($quoteItem));
        }

        $this->markItemAsGift($quoteItem, $ruleId, $originalSku, $discountPercent);
        return $quoteItem;
    }

    /**
     * Add a configurable product as a gift
     *
     * @param Quote $quote
     * @param int $ruleId
     * @param \Magento\Catalog\Api\Data\ProductInterface $parentProduct
     * @param array $superAttributeConfig
     * @param string $originalSku
     * @param float $discountPercent Percentage discount to apply (0-100)
     * @return CartItemInterface
     * @throws LocalizedException
     */
    private function addConfigurableGiftProduct(
        Quote $quote,
        int $ruleId,
        \Magento\Catalog\Api\Data\ProductInterface $parentProduct,
        array $superAttributeConfig,
        string $originalSku,
        float $discountPercent = 100.0
    ): CartItemInterface {
        // Check if the configurable gift is already in the cart
        foreach ($quote->getAllItems() as $item) {
            if ($this->isGiftFromRule($item, $ruleId) &&
                $item->getProduct()->getSku() === $parentProduct->getSku()) {
                // Check if it's the same configuration
                $sameConfig = true;
                foreach ($superAttributeConfig as $attributeId => $optionId) {
                    $option = $item->getOptionByCode('option_' . $attributeId);
                    if (!$option || $option->getValue() != $optionId) {
                        $sameConfig = false;
                        break;
                    }
                }

                if ($sameConfig) {
                    return $item;
                }
            }
        }

        $buyRequest = new \Magento\Framework\DataObject([
            'super_attribute' => $superAttributeConfig,
            'qty' => 1
        ]);

        $quoteItem = $quote->addProduct($parentProduct, $buyRequest);

        if (is_string($quoteItem)) {
            throw new LocalizedException(__($quoteItem));
        }

        $this->markItemAsGift($quoteItem, $ruleId, $originalSku, $discountPercent);
        return $quoteItem;
    }

    /**
     * Mark an item as a gift
     *
     * @param Item|CartItemInterface $item
     * @param int $ruleId
     * @param string $originalSku
     * @param float $discountPercent Percentage discount to apply (0-100)
     * @return void
     */
    private function markItemAsGift(CartItemInterface $item, int $ruleId, string $originalSku, float $discountPercent = 100.0): void
    {
        // Set a unique flag on the item to identify it as a gift from this rule
        // This is used to prevent duplicate additions when checking existing gifts
        $item->setData('is_gift_from_rule_' . $ruleId, true);

        // Set basic gift properties
        if ($discountPercent >= 100) {
            // 100% discount means the item is free
            $item->setGiftMessage(__('Free gift from promotion'));
            $item->setCustomPrice(0);
            $item->setOriginalCustomPrice(0);
        } else {
            // Apply the discount percentage
            $originalPrice = $item->getProduct()->getFinalPrice();
            $discountedPrice = $originalPrice * (1 - ($discountPercent / 100));

            // Round to 2 decimal places for currency
            $discountedPrice = round($discountedPrice, 2);

            $item->setGiftMessage(sprintf((string) __('Gift from promotion (%d%% discount)'), (int)$discountPercent));
            $item->setCustomPrice($discountedPrice);
            $item->setOriginalCustomPrice($discountedPrice);

            $this->logger->debug(sprintf(
                'Applied %f%% discount to gift product %s, original price: %f, discounted price: %f',
                $discountPercent,
                $item->getSku(),
                $originalPrice,
                $discountedPrice
            ));
        }

        $item->getProduct()->setCustomOptions([]);

        // Set extension attributes
        $extensionAttributes = $item->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->getExtensionAttributes($item);
        }

        $extensionAttributes->setIsGift(true);
        $extensionAttributes->setGiftRuleId($ruleId);
        $extensionAttributes->setOriginalProductSku($originalSku);
        $item->setExtensionAttributes($extensionAttributes);

        // Save extension attributes to the database if we have an item ID
        $itemId = $item->getItemId();
        if ($itemId) {
            try {
                $this->saveExtensionAttributes((int) $itemId, true, $ruleId, $originalSku);
            } catch (\Exception $e) {
                $this->logger->log(sprintf(
                    'Failed to save extension attributes for item %d: %s',
                    $itemId,
                    $e->getMessage()
                ));
            }
        } else {
            // Note: Setting extension attributes on items without an ID.
            // The SaveExtensionAttributesPlugin class will automatically persist these
            // extension attributes when the item gets an ID via the 'afterSetData' plugin.
            $this->logger->debug(sprintf(
                'Item %s has no ID yet. The extension attributes will be saved when the item ID is assigned.',
                $item->getSku()
            ));
        }
    }

    /**
     * Save extension attributes to the database
     *
     * @param int $itemId
     * @param bool $isGift
     * @param int $giftRuleId
     * @param string $originalSku
     * @return void
     */
    private function saveExtensionAttributes(int $itemId, bool $isGift, int $giftRuleId, string $originalSku): void
    {
        try {
            // Try to get existing record
            try {
                $extensionAttributes = $this->extensionRepository->getByItemId($itemId);
            } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                // Create a new record if it doesn't exist
                $extensionAttributes = $this->itemExtensionFactory->create();
                $extensionAttributes->setItemId($itemId);
            }

            // Set values
            $extensionAttributes->setIsGift($isGift);
            $extensionAttributes->setGiftRuleId($giftRuleId);
            $extensionAttributes->setOriginalProductSku($originalSku);

            // Save to database
            $this->extensionRepository->save($extensionAttributes);

            $this->logger->debug(sprintf(
                'Saved extension attributes for item %d: isGift=%d, giftRuleId=%d, originalSku=%s',
                $itemId,
                $isGift ? 1 : 0,
                $giftRuleId,
                $originalSku
            ));
        } catch (\Exception $e) {
            throw new \Exception(sprintf('Failed to save extension attributes: %s', $e->getMessage()), 0, $e);
        }
    }

    /**
     * Check if item is a gift from a specific rule
     *
     * @param CartItemInterface $item
     * @param int $ruleId
     * @return bool
     */
    public function isGiftFromRule(CartItemInterface $item, int $ruleId): bool
    {
        $extensionAttributes = $item->getExtensionAttributes();
        if (!$extensionAttributes) {
            return false;
        }

        return $extensionAttributes->getIsGift() &&
               $extensionAttributes->getGiftRuleId() === $ruleId;
    }

    /**
     * Remove all gift products added by a specific rule
     *
     * @param Quote $quote
     * @param int $ruleId
     * @return void
     */
    public function removeGiftProductsByRule(Quote $quote, int $ruleId): void
    {
        $itemsToRemove = [];

        foreach ($quote->getAllItems() as $item) {
            if ($this->isGiftFromRule($item, $ruleId)) {
                $itemsToRemove[] = $item->getId();
            }
        }

        foreach ($itemsToRemove as $itemId) {
            $quote->removeItem($itemId);
        }
    }

    /**
     * Remove all gift products that were added by rules that no longer apply
     *
     * @param Quote $quote
     * @param array $activeRuleIds
     * @return void
     */
    public function removeInvalidGiftProducts(Quote $quote, array $activeRuleIds): void
    {
        $itemsToRemove = [];

        foreach ($quote->getAllItems() as $item) {
            $extensionAttributes = $item->getExtensionAttributes();
            if (!$extensionAttributes || !$extensionAttributes->getIsGift()) {
                continue;
            }

            $giftRuleId = $extensionAttributes->getGiftRuleId();
            if ($giftRuleId === null) {
                continue;
            }

            if (!in_array($giftRuleId, $activeRuleIds)) {
                $itemsToRemove[] = $item->getId();
            }
        }

        foreach ($itemsToRemove as $itemId) {
            $quote->removeItem($itemId);
        }
    }
}
