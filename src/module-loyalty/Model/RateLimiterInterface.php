<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

interface RateLimiterInterface
{
    /**
     * Apply rate limiting based on configuration
     *
     * @return void
     */
    public function limit(): void;
}
