<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api\Data;

/**
 * Interface for apply balance result data
 */
interface ApplyGiftCardResultInterface
{
    /**
     * Get success status
     *
     * @return bool
     */
    public function getSuccess(): bool;

    /**
     * Set success status
     *
     * @param bool $success
     * @return $this
     */
    public function setSuccess(bool $success): self;

    /**
     * Get error message
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string;

    /**
     * Set error message
     *
     * @param string|null $errorMessage
     * @return $this
     */
    public function setErrorMessage(?string $errorMessage): self;

    /**
     * Get the applied gift card details.
     *
     * @return \Leat\Loyalty\Api\Data\AppliedGiftCardDetailsInterface|null
     */
    public function getAppliedCard(): ?AppliedGiftCardDetailsInterface;

    /**
     * Set the applied gift card details.
     *
     * @param \Leat\Loyalty\Api\Data\AppliedGiftCardDetailsInterface|null $cardDetails
     * @return $this
     */
    public function setAppliedCard(?AppliedGiftCardDetailsInterface $cardDetails): self;
}
