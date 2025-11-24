<?php
/**
 * GiftcardHelper
 *
 * Helper class that provides functionality for working with giftcard products.
 * This class handles identification of giftcard products and retrieval of giftcard-specific options.
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\Loyalty\Helper;

use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Leat\Loyalty\Setup\Patch\Data\AddGiftcardConfigurableAttributes;
use Leat\Loyalty\Setup\Patch\Data\AddLeatGiftcardAttribute;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Sales\Model\AbstractModel;
use Magento\Store\Model\StoreManagerInterface;

class GiftcardHelper
{
    /**
     * Defines the mapping between giftcard option keys and their display labels
     * These fields are stored in the buy request when a customer purchases a giftcard
     */
    protected const array GIFTCARD_BUYREQUEST_FIELDS = [
        GiftcardResource::BUYREQUEST_OPTION_IS_GIFT => 'Is Gift',
        GiftcardResource::BUYREQUEST_OPTION_RECIPIENT_EMAIL => 'Recipient Email',
        GiftcardResource::BUYREQUEST_OPTION_RECIPIENT_FIRSTNAME => 'Recipient Firstname',
        GiftcardResource::BUYREQUEST_OPTION_RECIPIENT_LASTNAME => 'Recipient Lastname',
        GiftcardResource::BUYREQUEST_OPTION_SENDER_MESSAGE => 'Sender Message'
    ];

    /**
     * Defines the mapping between giftcard option keys and their display labels
     * These fields are stored in the buy request when a customer purchases a giftcard
     */
    protected const array GIFTCARD_EMAIL_MERGE_TAG_FIELDS = [
        GiftcardResource::BUYREQUEST_OPTION_RECIPIENT_FIRSTNAME => 'custom.firstname',
        GiftcardResource::BUYREQUEST_OPTION_RECIPIENT_LASTNAME => 'custom.lastname',
        GiftcardResource::BUYREQUEST_OPTION_SENDER_MESSAGE => 'custom.message'
    ];

    /**
     * Defines the product types that are considered giftcards for export purposes
     * - simple products are managed manually by a Leat supplied app in combination with physical giftcard products
     */
    const array GIFTCARD_PRODUCT_TYPES_ORDER_EXPORT = ['simple', 'virtual'];

    /**
     * Cache to store whether a product is a giftcard to avoid repeated lookups
     */
    protected array $isProductGiftcardCache = [];

    /**
     * Cache to store giftcard options for quote/order items to avoid repeated processing
     */
    protected array $giftcardOptionsCache = [];

    /**
     * Cache to store giftcard products for a specific store
     * @var array
     */
    protected array $giftcardProducts;

    /**
     * Constructor
     *
     * @param ProductRepositoryInterface $productRepository For loading product data
     * @param Escaper $escaper For escaping HTML output in option values
     */
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected Escaper $escaper,
        protected SearchCriteriaBuilder $searchCriteriaBuilder,
        protected StoreManagerInterface $storeManager,
        protected GiftcardResource $giftcardResource,
    ) {
    }

    /**
     * Check if the product is a gift card
     *
     * Determines if a product is a giftcard by checking the giftcard attribute value.
     * Results are cached to improve performance for repeated calls.
     *
     * @param int|string $productId The ID of the product to check
     * @return bool True if the product is a giftcard, false otherwise
     * @throws \Magento\Framework\Exception\NoSuchEntityException If the product cannot be found
     */
    public function productIsGiftcard($productId): bool
    {
        if (!isset($this->isProductGiftcardCache[$productId])) {
            $product = $this->productRepository->getById($productId);
            // Note: There appears to be a potential bug here - if $product is null,
            // the second condition would cause a PHP error
            if ($product && $product->getTypeId() === 'configurable') {
                // Check if the product has the giftcard attribute set to a truthy value
                $giftcardAttrValue = $product->getData(AddLeatGiftcardAttribute::GIFTCARD_ATTRIBUTE_CODE);
                $this->isProductGiftcardCache[$productId] = $giftcardAttrValue && $giftcardAttrValue !== '0';
            } elseif ($product && ($product->getTypeId() === 'simple' || $product->getTypeId() === 'virtual')) {
                $giftcardValueAttrValue = $product->getData(AddGiftcardConfigurableAttributes::GIFTCARD_VALUE_ATTRIBUTE_CODE);
                $this->isProductGiftcardCache[$productId] = !empty($giftcardValueAttrValue);
            } else {
                // If the product is not found or not a giftcard, cache the result as false
                $this->isProductGiftcardCache[$productId] = false;
            }
        }

        return $this->isProductGiftcardCache[$productId];
    }

    /**
     * Check if a quote or order item is a giftcard
     *
     * Convenience method that extracts the product ID from the item
     * and delegates to productIsGiftcard().
     *
     * @param AbstractItem|AbstractModel $item The quote or order item to check
     * @return bool True if the item is a giftcard, false otherwise
     * @throws \Magento\Framework\Exception\NoSuchEntityException If the product cannot be found
     */
    public function itemIsGiftcard(AbstractItem|AbstractModel $item): bool
    {
        $product = $item->getProduct();
        if ($product === null) {
            return false;
        }
        return $this->productIsGiftcard($product->getId());
    }

    /**
     * Get formatted giftcard options for display
     *
     * Extracts giftcard-specific options from the item's buy request and formats them
     * for display in the frontend or admin area. Results are cached by item ID.
     *
     * @param AbstractItem|AbstractModel $item The quote or order item
     * @param array $options Existing options array to append to (useful for merging with standard product options)
     * @return array Array of formatted option data with 'label' and 'value' keys
     */
    public function getGiftcardOptions(AbstractItem|AbstractModel $item, $options = []): array
    {
        if (!isset($this->giftcardOptionsCache[$item->getId()])) {
            $buyRequest = $item->getBuyRequest();
            foreach (self::GIFTCARD_BUYREQUEST_FIELDS as $option => $optionLabel) {
                if ($optionValue = $buyRequest->getData($option)) {
                    // Convert boolean "on" value to localized Yes/No for display
                    if ($option === GiftcardResource::BUYREQUEST_OPTION_IS_GIFT) {
                        $optionValue = $optionValue === 'on' ? __('Yes') : __('No');
                    }
                    $itemData = [
                        'label' => __($optionLabel),
                        'value' => $this->escaper->escapeHtml($optionValue),
                    ];
                    $options[] = $itemData;
                }
            }

            // Remove any duplicate options that might have been added
            $this->giftcardOptionsCache[$item->getId()] = array_unique($options, SORT_REGULAR);
        }

        return $this->giftcardOptionsCache[$item->getId()];
    }

    /**
     * @param AbstractItem|AbstractModel $item
     * @param $options
     * @return array|mixed
     */
    public function getGiftcardOptionKeyValue(AbstractItem|AbstractModel $item, $options = []): array
    {
        $buyRequest = $item->getBuyRequest();
        foreach (self::GIFTCARD_BUYREQUEST_FIELDS as $option => $optionLabel) {
            if ($optionValue = $buyRequest->getData($option)) {
                if ($option === GiftcardResource::BUYREQUEST_OPTION_IS_GIFT) {
                    $optionValue = $optionValue === 'on';
                }

                $options[$option] = $optionValue;
            }
        }

        return $options;
    }

    /**
     * @param AbstractItem|AbstractModel $item
     * @return array
     */
    public function getGiftcardEmailMergeTags(AbstractItem|AbstractModel $item)
    {
        $keyValue = $this->getGiftcardOptionKeyValue($item);
        $mergeTags = [];
        foreach (self::GIFTCARD_EMAIL_MERGE_TAG_FIELDS as $option => $mergeTag) {
            if ($optionValue = ($keyValue[$option] ?? '')) {
                $mergeTags[$mergeTag] = $this->escaper->escapeHtml($optionValue);
            }
        }

        return $mergeTags;
    }

    /**
     * @param int|null $storeId
     * @param bool $idOnly
     * @param bool $flat
     * @param bool $orderExport
     * @return array
     * @throws NoSuchEntityException
     */
    public function getGiftcardProducts(
        int $storeId = 0,
        bool $idOnly = false,
        bool $flat = false,
        bool $orderExport = false
    ): array {
        $optionsKey = $storeId . $idOnly . $flat . $orderExport;
        if (empty($this->giftcardProducts[$optionsKey])) {
            $this->giftcardProducts[$optionsKey] = $this->getGiftcardProductsForStore($storeId, $idOnly, $orderExport);
        }

        if ($flat && isset($this->giftcardProducts[$optionsKey])) {
            return array_merge(...array_values($this->giftcardProducts[$optionsKey]));
        }

        return $this->giftcardProducts[$optionsKey];
    }

    /**
     * @param int|null $storeId
     * @param bool $idOnly
     * @param bool $orderExport
     * @return array
     * @throws NoSuchEntityException
     */
    public function getGiftcardProductsForStore(?int $storeId, bool $idOnly, bool $orderExport): array
    {
        $giftcardProducts = [];
        $searchCriteria = $this->getGiftcardProductSearchCriteria($storeId)->create();
        foreach ($this->productRepository->getList($searchCriteria)->getItems() as $product) {
            $simples = $product->getTypeInstance()->getUsedProducts($product);
            foreach ($simples as $simple) {
                if ($this->productIsGiftcard($product->getId()) &&
                    (!$orderExport || in_array($simple->getTypeId(), self::GIFTCARD_PRODUCT_TYPES_ORDER_EXPORT))
                ) {
                    $giftcardProducts[$product->getId()][] = !$idOnly ? $simple : $simple->getId();
                }
            }
        }

        return $giftcardProducts;
    }

    /**
     * @param int $storeId
     * @return SearchCriteriaBuilder
     */
    protected function getGiftcardProductSearchCriteria(int $storeId): SearchCriteriaBuilder
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilder
            ->addFilter(AddLeatGiftcardAttribute::GIFTCARD_ATTRIBUTE_CODE, true)
            ->addFilter('type_id', 'configurable');

        if ($storeId) {
            $searchCriteriaBuilder->addFilter('store_id', $storeId);
        }

        return $searchCriteriaBuilder;
    }

    /**
     * Get the UUID of a giftcard by its hash
     *
     * @param string $giftcardHash
     * @param int|null $storeId
     * @return string|null
     * @throws LocalizedException
     */
    public function getGiftcardUUIDByHash(string $giftcardHash, ?int $storeId = null): ?string
    {
        $giftcard = $this->giftcardResource->findGiftcardByHash($giftcardHash, $storeId);
        return $giftcard->getUuid() ?? null;
    }
}
