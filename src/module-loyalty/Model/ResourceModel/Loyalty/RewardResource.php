<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\ResourceModel\Loyalty;

use Magento\Framework\Exception\LocalizedException;
use Piggy\Api\Models\Loyalty\Rewards\Reward;
use Piggy\Api\Models\Loyalty\Rewards\CollectableReward;

class RewardResource extends AbstractLeatResource
{
    protected const string LOGGER_PURPOSE = 'reward';

    /**
     * Get available rewards for a customer
     *
     * @param int $customerId
     * @param int|null $storeId
     * @return Reward[]
     * @throws LocalizedException
     */
    public function getAvailableRewards(int $customerId, ?int $storeId = null): array
    {
        return $this->executeApiRequest(
            function () use ($customerId, $storeId) {
                $contactUuid = $this->getContactUuid($customerId);
                $shopUuid = $this->getShopUuid($storeId);
                $client = $this->getClient($storeId);

                return $client->rewards->get($contactUuid, $shopUuid);
            },
            'Error fetching available rewards'
        );
    }

    /**
     * Get available rewards for a shop
     *
     * @param int|null $storeId
     * @return Reward[]
     * @throws LocalizedException
     */
    public function getRewardsForShop(?int $storeId = null): array
    {
        return $this->executeApiRequest(
            function () use ($storeId) {
                $client = $this->getClient($storeId);

                return $client->rewards->list();
            },
            'Error fetching available rewards'
        );
    }

    /**
     * Retrieve all rewards for all shops
     *
     * @return array
     * @throws LocalizedException
     */
    public function getAllRewards(): array
    {
        $result = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();
            $shopUuid = $this->getShopUuid($storeId);
            try {
                if (!$this->config->getIsEnabled($storeId)) {
                    continue;
                }

                if (isset($result[$shopUuid])) {
                    continue;
                }

                $result[$shopUuid] = $this->getRewardsForShop($storeId);
            } catch (LocalizedException $e) {
                $this->logger->log(
                    sprintf('Error fetching rewards for shop %s: %s', $shopUuid, $e->getMessage())
                );
                $result[$shopUuid] = [];
            }
        }

        return $result;
    }

    /**
     * Get collectable rewards for a customer
     *
     * @param int $customerId
     * @param int|null $storeId
     * @return CollectableReward[]
     * @throws LocalizedException
     */
    public function getCollectableRewards(int $customerId, ?int $storeId = null): array
    {
        return $this->executeApiRequest(
            function () use ($customerId, $storeId) {
                $contactUuid = $this->getContactUuid($customerId);
                $client = $this->getClient($storeId);

                return $client->collectableRewards->list($contactUuid);
            },
            'Error fetching collectable rewards'
        );
    }

    /**
     * Create a reward reception for the contact for the given reward
     *
     * @param int $customerId
     * @param string $rewardUuid
     * @param int|null $storeId
     * @return mixed
     * @throws LocalizedException
     */
    public function createRewardReception(int $customerId, string $rewardUuid, ?int $storeId = null): mixed
    {
        return $this->executeApiRequest(
            function () use ($customerId, $rewardUuid, $storeId) {
                $contactUuid = $this->getContactUuid($customerId);
                $shopUuid = $this->getShopUuid($storeId);
                $client = $this->getClient($storeId);

                return $client->rewardReceptions->create($contactUuid, $shopUuid, $rewardUuid);
            },
            'Error redeeming reward'
        );
    }

    /**
     * Collect a collectable reward for a given loyalty transaction UUID
     *
     * @param string $loyaltyTransactionUUID
     * @param int|null $storeId
     * @return CollectableReward
     * @throws LocalizedException
     */
    public function collectCollectableReward(string $loyaltyTransactionUUID, ?int $storeId = null): CollectableReward
    {
        return $this->executeApiRequest(
            function () use ($loyaltyTransactionUUID, $storeId) {
                $client = $this->getClient($storeId);

                return $client->collectableRewards->collect($loyaltyTransactionUUID);
            },
            'Error collecting collectable reward'
        );
    }
}
