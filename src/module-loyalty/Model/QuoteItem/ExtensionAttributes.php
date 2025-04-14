<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\QuoteItem;

use Magento\Framework\Model\AbstractModel;
use Leat\Loyalty\Model\ResourceModel\QuoteItem\ExtensionAttributes as ResourceModel;

class ExtensionAttributes extends AbstractModel
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * Set the item ID
     *
     * @param int $itemId
     * @return $this
     */
    public function setItemId(int $itemId)
    {
        return $this->setData('item_id', $itemId);
    }

    /**
     * Get the item ID
     *
     * @return int|null
     */
    public function getItemId(): ?int
    {
        return $this->getData('item_id') ? (int)$this->getData('item_id') : null;
    }

    /**
     * Set is gift flag
     *
     * @param bool $isGift
     * @return $this
     */
    public function setIsGift(bool $isGift)
    {
        return $this->setData('is_gift', $isGift);
    }

    /**
     * Get is gift flag
     *
     * @return bool
     */
    public function getIsGift(): bool
    {
        return (bool)$this->getData('is_gift');
    }

    /**
     * Set gift rule ID
     *
     * @param int|null $ruleId
     * @return $this
     */
    public function setGiftRuleId(?int $ruleId)
    {
        return $this->setData('gift_rule_id', $ruleId);
    }

    /**
     * Get gift rule ID
     *
     * @return int|null
     */
    public function getGiftRuleId(): ?int
    {
        return $this->getData('gift_rule_id') ? (int)$this->getData('gift_rule_id') : null;
    }

    /**
     * Set original product SKU
     *
     * @param string|null $sku
     * @return $this
     */
    public function setOriginalProductSku(?string $sku)
    {
        return $this->setData('original_product_sku', $sku);
    }

    /**
     * Get original product SKU
     *
     * @return string|null
     */
    public function getOriginalProductSku(): ?string
    {
        return $this->getData('original_product_sku');
    }
}
