<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Interface for balance validator
 */
interface BalanceValidatorInterface
{
    /**
     * Check if requested balance amount is valid
     *
     * @param CartInterface $quote
     * @param float $amount
     * @return bool
     */
    public function isValid(CartInterface $quote, float $amount): bool;

    /**
     * Get error message if validation failed
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string;
}
