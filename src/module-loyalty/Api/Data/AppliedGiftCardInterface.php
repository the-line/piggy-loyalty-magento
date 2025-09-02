<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api\Data;

/**
 * Interface AppliedGiftCardInterface
 * @api
 */
interface AppliedGiftCardInterface
{
    public const ENTITY_ID = 'entity_id';
    public const QUOTE_ID = 'quote_id';
    public const ORDER_ID = 'order_id';
    public const GIFT_CARD_CODE = 'gift_card_code';
    public const BALANCE = 'balance';
    public const APPLIED_AMOUNT = 'applied_amount';
    public const BASE_APPLIED_AMOUNT = 'base_applied_amount';
    public const REFUNDED_AMOUNT = 'refunded_amount';
    public const BASE_REFUNDED_AMOUNT = 'base_refunded_amount';
    public const LEAT_TRANSACTION_UUID = 'leat_transaction_uuid';
    public const LEAT_GIFTCARD_UUID = 'leat_giftcard_uuid';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get Entity ID
     *
     * @return int|null
     */
    /**
     * Get Quote ID
     *
     * @return int|null
     */
    public function getQuoteId(): ?int;

    /**
     * Set Quote ID
     *
     * @param int|null $quoteId
     * @return $this
     */
    public function setQuoteId(?int $quoteId): self;

    /**
     * Get Order ID
     *
     * @return int|null
     */
    public function getOrderId(): ?int;

    /**
     * Set Order ID
     *
     * @param int|null $orderId
     * @return $this
     */
    public function setOrderId(?int $orderId): self;

    /**
     * Get Gift Card Code
     *
     * @return string|null
     */
    public function getGiftCardCode(): ?string;

    /**
     * Set Gift Card Code
     *
     * @param string $giftCardCode
     * @return $this
     */
    public function setGiftCardCode(string $giftCardCode): self;

    /**
     * Get Gift Card Balance
     * @return float|null
     */
    public function getBalance(): ?float;

    /**
     * Set Gift Card Balance
     *
     * @param float $balance
     * @return self
     */
    public function setBalance(float $balance): self;
    /**
     * Get Applied Amount
     *
     * @return float|null
     */
    public function getAppliedAmount(): ?float;

    /**
     * Set Applied Amount
     *
     * @param float $appliedAmount
     * @return $this
     */
    public function setAppliedAmount(float $appliedAmount): self;

    /**
     * Get Base Applied Amount
     *
     * @return float|null
     */
    public function getBaseAppliedAmount(): ?float;

    /**
     * Set Base Applied Amount
     *
     * @param float $baseAppliedAmount
     * @return $this
     */
    public function setBaseAppliedAmount(float $baseAppliedAmount): self;

    /**
     * Get Refunded Amount
     *
     * @return float|null
     */
    public function getRefundedAmount(): ?float;

    /**
     * Set Refunded Amount
     *
     * @param float $refundedAmount
     * @return $this
     */
    public function setRefundedAmount(float $refundedAmount): self;

    /**
     * Get Base Refunded Amount
     *
     * @return float|null
     */
    public function getBaseRefundedAmount(): ?float;

    /**
     * Set Base Refunded Amount
     *
     * @param float $baseRefundedAmount
     * @return $this
     */
    public function setBaseRefundedAmount(float $baseRefundedAmount): self;

    /**
     * Get Leat Transaction UUID
     *
     * @return string|null
     */
    public function getLeatTransactionUuid(): ?string;

    /**
     * Set Leat Transaction UUID
     *
     * @param string|null $leatTransactionUuid
     * @return $this
     */
    public function setLeatTransactionUuid(?string $leatTransactionUuid): self;

    /**
     * Get Leat Giftcard UUID
     *
     * @return string|null
     */
    public function getLeatGiftcardUuid(): ?string;

    /**
     * Set Leat Giftcard UUID
     *
     * @param string|null $leatGiftcardUuid
     * @return $this
     */
    public function setLeatGiftcardUuid(?string $leatGiftcardUuid): self;

    /**
     * Get Created At
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set Created At
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * Get Updated At
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set Updated At
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): self;
}
