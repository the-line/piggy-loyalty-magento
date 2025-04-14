<?php

declare(strict_types=1);

namespace Leat\Loyalty\Api;

/**
 * Interface for applying prepaid balance to a quote
 */
interface ApplyBalanceInterface
{
    /**
     * Apply prepaid balance amount to quote
     *
     * @param string $cartId
     * @param float $balanceAmount
     * @return \Leat\Loyalty\Api\Data\ApplyBalanceResultInterface
     */
    public function apply(string $cartId, float $balanceAmount): Data\ApplyBalanceResultInterface;
}
