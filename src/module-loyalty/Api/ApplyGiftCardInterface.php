<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api;

/**
 * Interface for applying prepaid balance to a quote
 */
interface ApplyGiftCardInterface
{
    /**
     * Apply prepaid balance amount to quote
     *
     * @param string $cartId
     * @param string $code
     * @return \Leat\Loyalty\Api\Data\ApplyGiftCardResultInterface
     */
    public function apply(string $cartId, string $code): Data\ApplyGiftCardResultInterface;
}
