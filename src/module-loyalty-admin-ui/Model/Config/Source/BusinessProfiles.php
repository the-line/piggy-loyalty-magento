<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Model\Config\Source;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Leat\Loyalty\Model\ResourceModel\Loyalty\RewardResource;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Models\Giftcards\GiftcardProgram;

class BusinessProfiles implements OptionSourceInterface
{
    public function __construct(
        protected Connector $connector,
        protected StoreManagerInterface $storeManager,
        protected Config $config,
    ) {
    }

    /**
     * Get options array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        try {
            /** @var GiftcardProgram[] $allGiftcardPrograms */
            $client = $this->connector->getConnection();
            $businessProfiles = $client->shops->all();
            foreach ($businessProfiles as $profile) {
                $options[] = [
                    'value' => $profile->getUUID(),
                    'label' => sprintf('%s', $profile->getName())
                ];
            }

            // Sort options alphabetically by label
            usort($options, function ($a, $b) {
                return $a['label'] <=> $b['label'];
            });
        } catch (LocalizedException $e) {
            return [
                'value' => null,
                'label' => __("Couldn't fetch business profiles. Please check your Personal Access Token and connection.")
            ];
        }

        array_unshift($options, [
            'value' => null,
            'label' => __('-- Please Select --')
        ]);

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
