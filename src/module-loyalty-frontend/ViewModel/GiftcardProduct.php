<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\ViewModel;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Setup\Patch\Data\AddLeatGiftcardAttribute;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Registry;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Directory\Model\Currency;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Eav\Model\Config as EavConfig;

class GiftcardProduct implements ArgumentInterface
{
    /**
     * @param Registry $registry
     * @param Config $config
     * @param PriceHelper $priceHelper
     * @param StoreManagerInterface $storeManager
     * @param Currency $currency
     * @param EavConfig $eavConfig
     */
    public function __construct(
        protected Registry $registry,
        protected Config $config,
        protected PriceHelper $priceHelper,
        protected StoreManagerInterface $storeManager,
        protected Currency $currency,
        protected EavConfig $eavConfig
    ) {
    }

    /**
     * Get current product
     *
     * @return ProductInterface|null
     */
    public function getProduct(): ?ProductInterface
    {
        return $this->registry->registry('current_product');
    }

    /**
     * Check if gift card functionality is enabled
     *
     * @return bool
     */
    public function isGiftcardEnabled(): bool
    {
        return $this->config->isGiftcardEnabled();
    }

    /**
     * Check if gift form is enabled
     *
     * @return bool
     */
    public function isGiftFormEnabled(): bool
    {
        return $this->config->isShowSendAsGift();
    }

    /**
     * Check if the current product is a Leat Giftcard
     *
     * @return bool
     */
    public function isLeatGiftcard(): bool
    {
        $product = $this->getProduct();
        if (!$product) {
            return false;
        }

        $giftcardAttrValue = $product->getData(AddLeatGiftcardAttribute::GIFTCARD_ATTRIBUTE_CODE);
        return $giftcardAttrValue && $giftcardAttrValue !== '0';
    }

    /**
     * @return bool
     */
    public function isEmailVisible(): bool
    {
        return (bool) $this->config->isShowRecipientEmail()['is_visible'] ?? false;
    }

    /**
     * @return bool
     */
    public function isEmailRequired(): bool
    {
        return (bool) $this->config->isShowRecipientEmail()['is_required'] ?? false;
    }

    /**
     * @return bool
     */
    public function isFirstnameVisible(): bool
    {
        return (bool) $this->config->isShowRecipientFirstname()['is_visible'] ?? false;
    }

    /**
     * @return bool
     */
    public function isFirstnameRequired(): bool
    {
        return (bool) $this->config->isShowRecipientFirstname()['is_required'] ?? false;
    }

    /**
     * @return bool
     */
    public function isLastnameVisible(): bool
    {
        return (bool) $this->config->isShowRecipientLastname()['is_visible'] ?? false;
    }

    /**
     * @return bool
     */
    public function isLastnameRequired(): bool
    {
        return (bool) $this->config->isShowRecipientLastname()['is_required'] ?? false;
    }

    /**
     * @return bool
     */
    public function isMessageVisible(): bool
    {
        return (bool) $this->config->isShowSenderMessage()['is_visible'] ?? false;
    }

    /**
     * @return bool
     */
    public function isMessageRequired(): bool
    {
        return (bool) $this->config->isShowSenderMessage()['is_required'] ?? false;
    }
}
