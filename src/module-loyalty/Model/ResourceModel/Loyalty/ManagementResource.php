<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel\Loyalty;

use Magento\Framework\Exception\LocalizedException;

class ManagementResource extends AbstractLeatResource
{
    protected const string LOGGER_PURPOSE = 'management';

    /**
     * Get shop information
     *
     * @param int|null $storeId
     * @return mixed
     * @throws LocalizedException
     */
    public function getShopInfo(?int $storeId = null): mixed
    {
        return $this->executeApiRequest(
            function () use ($storeId) {
                $shopUuid = $this->getShopUuid($storeId);
                $client = $this->getClient($storeId);

                return $client->shops->get($shopUuid);
            },
            'Error getting shop information'
        );
    }

    /**
     * Ping the Leat API
     *
     * @param int|null $storeId
     * @return mixed
     * @throws LocalizedException
     */
    public function ping(?int $storeId = null): mixed
    {
        return $this->executeApiRequest(
            function () use ($storeId) {
                $client = $this->getClient($storeId);

                return $client->ping();
            },
            'Error pinging Leat API'
        );
    }
}
