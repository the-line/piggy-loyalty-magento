<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api\Data;

/**
 * DTO for individual applied gift card details.
 * This is used as a nested object in API responses.
 */
interface AppliedGiftCardDetailsInterface
{
    public const KEY_ID = 'id';
    public const KEY_MASKED_CODE = 'masked_code';
    public const KEY_APPLIED_AMOUNT_FORMATTED = 'applied_amount_formatted';

    /**
     * Get the ID of the applied gift card record.
     *
     * @return int|null
     */
    public function getId(): ?int;

    /**
     * Set the ID of the applied gift card record.
     *
     * @param int $id
     * @return $this
     */
    public function setId(int $id): self;

    /**
     * Get the masked gift card code.
     *
     * @return string|null
     */
    public function getMaskedCode(): ?string;

    /**
     * Set the masked gift card code.
     *
     * @param string $maskedCode
     * @return $this
     */
    public function setMaskedCode(string $maskedCode): self;

    /**
     * Get the formatted applied amount.
     *
     * @return string|null
     */
    public function getAppliedAmountFormatted(): ?string;

    /**
     * Set the formatted applied amount.
     *
     * @param string $amountFormatted
     * @return $this
     */
    public function setAppliedAmountFormatted(string $amountFormatted): self;
}
