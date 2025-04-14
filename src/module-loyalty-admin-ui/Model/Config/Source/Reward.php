<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Model\Config\Source;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\ResourceModel\Loyalty\RewardResource;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class Reward implements OptionSourceInterface
{
    public function __construct(
        protected RewardResource $rewardResource,
        protected StoreManagerInterface $storeManager,
        protected Config $config,
    ) {
    }

    /**
     * Get options array
     *
     * @param int|null $excludeRuleId Option to exclude current rule ID from filtering
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];

        try {
            /** @var \Loyalty\Api\Models\Loyalty\Rewards\Reward[] $allRewards */
            $allRewards = $this->rewardResource->getAllRewards();
            $shopNames = $this->getStoreNames();

            foreach ($allRewards as $shopUuid => $rewards) {
                $shopName = $shopNames[$shopUuid] ?? $shopUuid;

                foreach ($rewards as $reward) {
                    $options[] = [
                        'value' => $reward->getUUID(),
                        'label' => sprintf('%s (%s)', $reward->getTitle(), $shopName)
                    ];
                }
            }

            // Sort options alphabetically by label
            usort($options, function ($a, $b) {
                return $a['label'] <=> $b['label'];
            });
        } catch (LocalizedException $e) {
            // If there's an error getting rewards, return empty array
            // We don't want to break the admin UI
        }

        return $options;
    }

    /**
     * Get a mapping of shop UUIDs to store names
     *
     * @return array
     */
    private function getStoreNames(): array
    {
        $shopNames = [];
        foreach ($this->storeManager->getStores() as $store) {
            try {
                $shopUUID = $this->config->getShopUuid((int) $store->getId()) ?? 'unknown';
                $shopNames[$shopUUID] = $store->getName();
            } catch (LocalizedException $e) {
                // Skip stores that don't have a shop UUID or rewards
                continue;
            }
        }

        return $shopNames;
    }
}
