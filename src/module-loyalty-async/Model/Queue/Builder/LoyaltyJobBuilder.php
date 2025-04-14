<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Builder;

use Leat\AsyncQueue\Model\Builder\JobBuilder;

class LoyaltyJobBuilder extends JobBuilder
{
    public const string LEAT_SOURCE_ID = 'leat';
    protected const string DEFAULT_SOURCE_ID = self::LEAT_SOURCE_ID;
}
