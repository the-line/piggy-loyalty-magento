<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Model\Config\Source;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Leat\Loyalty\Model\ResourceModel\Loyalty\RewardResource;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Piggy\Api\Models\Giftcards\GiftcardProgram;

class GiftcardPrograms implements OptionSourceInterface
{
    public function __construct(
        protected GiftcardResource $giftcardResource,
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
            $allGiftcardPrograms = $this->giftcardResource->getAllGiftcardPrograms();
            $shopNames = $this->getStoreNames();
            foreach ($allGiftcardPrograms as $shopUUID => $programs) {
                $shopName = $shopNames[$shopUUID] ?? $shopUUID;
                foreach ($programs as $giftcardProgram) {
                    if (!$giftcardProgram->isActive()) {
                        // Skip inactive giftcard programs
                        continue;
                    }

                    $options[] = [
                        'value' => $giftcardProgram->getUUID(),
                        'label' => sprintf('%s (%s)', $giftcardProgram->getName(), $shopName)
                    ];
                }
            }

            // Sort options alphabetically by label
            usort($options, function ($a, $b) {
                return $a['label'] <=> $b['label'];
            });
        } catch (LocalizedException $e) {
            // If there's an error getting giftcard programs, return empty array
            // We don't want to break the admin UI
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
