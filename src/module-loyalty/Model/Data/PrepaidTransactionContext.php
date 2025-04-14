<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Data;

class PrepaidTransactionContext
{
    /**
     * @param string|null $existingTransactionUuid
     * @param string|null $contactUuid
     * @param string|null $shopUuid
     * @param float|null $amount
     */
    public function __construct(
        public readonly ?string $existingTransactionUuid = null,
        public readonly ?string $contactUuid = null,
        public readonly ?string $shopUuid = null,
        public readonly ?float $amount = null
    ) {
    }
}
