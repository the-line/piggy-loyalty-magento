<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Data;

use Leat\Loyalty\Api\Data\AppliedGiftCardInterface;
use Magento\Framework\Model\AbstractModel;

class AppliedGiftCard extends AbstractModel implements AppliedGiftCardInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(\Leat\Loyalty\Model\ResourceModel\AppliedGiftCard::class);
    }

    /**
     * @inheritDoc
     */
    public function getQuoteId(): ?int
    {
        return $this->getData(self::QUOTE_ID) === null ? null : (int)$this->getData(self::QUOTE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setQuoteId(?int $quoteId): self
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): ?int
    {
        return $this->getData(self::ORDER_ID) === null ? null : (int)$this->getData(self::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(?int $orderId): self
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getGiftCardCode(): ?string
    {
        return $this->getData(self::GIFT_CARD_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setGiftCardCode(string $giftCardCode): self
    {
        return $this->setData(self::GIFT_CARD_CODE, $giftCardCode);
    }

    /**
     * @inheritDoc
     */
    public function getBalance(): ?float
    {
        return $this->getData(self::BALANCE) === null ? null : (float)$this->getData(self::BALANCE);
    }

    /**
     * @inheritDoc
     */
    public function setBalance(float $balance): self
    {
        return $this->setData(self::BALANCE, $balance);
    }

    /**
     * @inheritDoc
     */
    public function getAppliedAmount(): ?float
    {
        return $this->getData(self::APPLIED_AMOUNT) === null ? null : (float)$this->getData(self::APPLIED_AMOUNT);
    }

    /**
     * @inheritDoc
     */
    public function setAppliedAmount(float $appliedAmount): self
    {
        return $this->setData(self::APPLIED_AMOUNT, $appliedAmount);
    }

    /**
     * @inheritDoc
     */
    public function getBaseAppliedAmount(): ?float
    {
        return $this->getData(self::BASE_APPLIED_AMOUNT) === null ? null : (float)$this->getData(self::BASE_APPLIED_AMOUNT);
    }

    /**
     * @inheritDoc
     */
    public function setBaseAppliedAmount(float $baseAppliedAmount): self
    {
        return $this->setData(self::BASE_APPLIED_AMOUNT, $baseAppliedAmount);
    }

    /**
     * @inheritDoc
     */
    public function getRefundedAmount(): ?float
    {
        return $this->getData(self::REFUNDED_AMOUNT) === null ? null : (float)$this->getData(self::REFUNDED_AMOUNT);
    }

    /**
     * @inheritDoc
     */
    public function setRefundedAmount(float $refundedAmount): self
    {
        return $this->setData(self::REFUNDED_AMOUNT, $refundedAmount);
    }

    /**
     * @inheritDoc
     */
    public function getBaseRefundedAmount(): ?float
    {
        return $this->getData(self::BASE_REFUNDED_AMOUNT) === null ? null : (float)$this->getData(self::BASE_REFUNDED_AMOUNT);
    }

    /**
     * @inheritDoc
     */
    public function setBaseRefundedAmount(float $baseRefundedAmount): self
    {
        return $this->setData(self::BASE_REFUNDED_AMOUNT, $baseRefundedAmount);
    }

    /**
     * @inheritDoc
     */
    public function getLeatTransactionUuid(): ?string
    {
        return $this->getData(self::LEAT_TRANSACTION_UUID);
    }

    /**
     * @inheritDoc
     */
    public function setLeatTransactionUuid(?string $leatTransactionUuid): self
    {
        return $this->setData(self::LEAT_TRANSACTION_UUID, $leatTransactionUuid);
    }

    /**
     * @inheritDoc
     */
    public function getLeatGiftcardUuid(): ?string
    {
        return $this->getData(self::LEAT_GIFTCARD_UUID);
    }

    /**
     * @inheritDoc
     */
    public function setLeatGiftcardUuid(?string $leatGiftcardUuid): self
    {
        return $this->setData(self::LEAT_GIFTCARD_UUID, $leatGiftcardUuid);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt): self
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
