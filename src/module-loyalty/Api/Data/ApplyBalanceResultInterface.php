<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api\Data;

/**
 * Interface for apply balance result data
 */
interface ApplyBalanceResultInterface
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
     * Get balance amount
     *
     * @return float
     */
    public function getBalanceAmount(): float;

    /**
     * Set balance amount
     *
     * @param float $amount
     * @return $this
     */
    public function setBalanceAmount(float $amount): self;

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
}
