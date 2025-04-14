<?php

declare(strict_types=1);

namespace Leat\Loyalty\Service;

use Leat\Loyalty\Model\Connector;
use Magento\Framework\Exception\AuthenticationException;

class CreditCalculator
{
    /**
     * @param Connector $connector
     */
    public function __construct(
        protected Connector $connector
    ) {
    }

    /**
     * @param float $purchaseValue
     * @param int $storeId
     * @param string|null $contactUuid
     * @return int
     */
    public function calculateCreditsByPurchaseAmount(float $purchaseValue, int $storeId, ?string $contactUuid = null): int
    {
        try {
            $client = $this->connector->getConnection();
            $shopUuid = $this->connector->getConfig()->getShopUuid($storeId);

            $result = $client->creditReceptions->calculate(
                $shopUuid,
                floor($purchaseValue),
                $contactUuid
            );
        } catch (\Throwable $e) {
            $result = 0;
        }

        return $result;
    }
}
