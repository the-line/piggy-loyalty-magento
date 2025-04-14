<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Leat\Loyalty\Model\ResourceModel\Loyalty\RewardResource;
use Leat\Loyalty\Model\SalesRule\EmptyCartGiftManager;
use Leat\Loyalty\Model\SalesRule\LoyaltyTransactionRelatedGiftRemover;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Piggy\Api\Models\Loyalty\Receptions\DigitalRewardReception;
use Piggy\Api\Models\Loyalty\Receptions\PhysicalRewardReception;

/**
 * Manages applied Leat coupons for quotes
 * Prepared to allow for multi coupon support, but currently only supports one coupon per reward
 */
class AppliedCouponsManager
{
    protected array $collectableRewardsCache = [];

    public function __construct(
        protected CustomerSession $customerSession,
        protected CheckoutSession $checkoutSession,
        protected CartRepositoryInterface $cartRepository,
        protected Json $json,
        protected Connector $connector,
        protected RewardResource $rewardResource,
        protected ManagerInterface $eventManager,
        protected LoyaltyTransactionRelatedGiftRemover $couponRelatedGiftRemover,
        protected EmptyCartGiftManager $emptyCartGiftManager
    ) {
    }

    /**
     * Add a coupon (loyalty transaction UUID)  to the quote's applied coupons
     *
     * @param string $loyaltyTransactionUUID
     * @return bool
     * @throws LocalizedException
     */
    public function addCoupon(string $loyaltyTransactionUUID): bool
    {
        try {
            $quote = $this->getQuote();
            if (!$quote) {
                throw new LocalizedException(__('Active quote not found'));
            }

            $appliedCoupons = $this->getAppliedCoupons($quote);
            $rewardUUID = $this->getRewardUUIDForLoyaltyTransactionUUID($loyaltyTransactionUUID, $quote);
            if (in_array($loyaltyTransactionUUID, $appliedCoupons[$rewardUUID] ?? [])) {
                return true; // Already applied, consider it a success
            }

            // TODO: REMOVE TO ALLOW MULTIPLE COUPONS
            unset($appliedCoupons[$rewardUUID]);

            // Add the new coupon
            $appliedCoupons[$rewardUUID][] = $loyaltyTransactionUUID;

            $this->eventManager->dispatch(
                'leat_coupon_applied',
                ['quote' => $quote, 'loyalty_transaction_uuid' => $loyaltyTransactionUUID]
            );

            // Save to quote
            $this->saveAppliedCoupons($quote, $appliedCoupons);

            $this->rewardResource->getLogger()->log(
                sprintf('Added coupon %s to quote %s', $loyaltyTransactionUUID, $quote->getId())
            );

            return true;
        } catch (\Exception $e) {
            $this->rewardResource->getLogger()->log(
                sprintf('Error adding coupon: %s', $e->getMessage()),
            );
            throw new LocalizedException(__('Could not apply coupon: %1', $e->getMessage()));
        }
    }

    /**
     * Remove a coupon (loyalty transaction UUID) from the quote's applied coupons (loyalty transaction UUIDs)
     *
     * @param string $loyaltyTransactionUUID
     * @return bool
     * @throws LocalizedException
     */
    public function removeCoupon(string $loyaltyTransactionUUID): bool
    {
        try {
            $quote = $this->getQuote();
            if (!$quote) {
                throw new LocalizedException(__('Active quote not found'));
            }

            $appliedCoupons = $this->getAppliedCoupons($quote);
            $rewardUUID = $this->getRewardUUIDForLoyaltyTransactionUUID($loyaltyTransactionUUID, $quote);
            if (!in_array($loyaltyTransactionUUID, $appliedCoupons[$rewardUUID] ?? [])) {
                return true;
            }

            // Remove the coupon
            $appliedCoupons[$rewardUUID] = array_values(array_filter($appliedCoupons[$rewardUUID], function ($coupon) use ($loyaltyTransactionUUID) {
                return $coupon !== $loyaltyTransactionUUID;
            }));

            if (count($appliedCoupons[$rewardUUID]) === 0) {
                unset($appliedCoupons[$rewardUUID]);
                $this->resetGift($quote);
            }

            $this->eventManager->dispatch(
                'leat_coupon_removed',
                ['quote' => $quote, 'loyalty_transaction_uuid' => $loyaltyTransactionUUID]
            );

            // Save to quote
            $this->saveAppliedCoupons($quote, $appliedCoupons);

            $this->rewardResource->getLogger()->log(
                sprintf('Removed coupon %s from quote %s', $loyaltyTransactionUUID, $quote->getId())
            );

            return true;
        } catch (\Exception $e) {
            $this->rewardResource->getLogger()->log(
                sprintf('Error removing coupon: %s', $e->getMessage()),
            );
            throw new LocalizedException(__('Could not remove coupon: %1', $e->getMessage()));
        }
    }

    /**
     * @param Quote $quote
     * @return void
     */
    protected function resetGift(Quote $quote): void
    {
        // Extension attributes are now loaded automatically via plugin
        foreach ($quote->getAllItems() as $item) {
            $extensionAttributes = $item->getExtensionAttributes();

            // Check if this is a gift item
            if ($extensionAttributes && $extensionAttributes->getIsGift()) {
                $ruleId = $extensionAttributes->getGiftRuleId();
                if ($ruleId) {
                    // Process gift removal
                    $quote->setLoyaltyTransactionRewardRuleId($ruleId);
                    $this->couponRelatedGiftRemover->execute($quote);

                    // Log the gift removal
                    $this->rewardResource->getLogger()->log(sprintf(
                        'Reset gift in quote %s for rule %d',
                        $quote->getId(),
                        $ruleId
                    ));

                    // Break after removing the first gift - one execute is enough
                    break;
                }
            }
        }
    }

    /**
     * Get all applied coupons (loyalty transaction UUIDs) for the current quote
     *
     * @param bool $flat
     * @return array
     */
    public function getAllAppliedCoupons(bool $flat = false, ?Quote $quote = null): array
    {
        try {
            $quote = $quote ?? $this->getQuote();
            if (!$quote) {
                return [];
            }

            if ($flat) {
                return call_user_func_array(
                    'array_merge',
                    array_values($this->getAppliedCoupons($quote))
                );
            }

            return $this->getAppliedCoupons($quote);
        } catch (\Exception $e) {
            $this->rewardResource->getLogger()->log(
                sprintf('Error getting all applied coupons: %s', $e->getMessage()),
            );
            return [];
        }
    }


    /**
     * Get applied coupons (loyalty transaction UUIDs) from quote
     *
     * @param Quote $quote
     * @return array
     */
    protected function getAppliedCoupons(Quote $quote): array
    {
        $appliedCouponsString = $quote->getData('leat_loyalty_applied_coupons');
        if (!$appliedCouponsString) {
            return [];
        }

        try {
            $appliedCoupons = $this->json->unserialize($appliedCouponsString);
            if (!is_array($appliedCoupons)) {
                return [];
            }

            return $appliedCoupons;
        } catch (\Exception $e) {
            $this->rewardResource->getLogger()->log(
                sprintf('Error unserializing applied coupons: %s', $e->getMessage())
            );
            return [];
        }
    }


    /**
     * Get the current active quote
     *
     * @return Quote|null
     * @throws NoSuchEntityException
     */
    protected function getQuote(): ?Quote
    {
        try {
            if (!$this->customerSession->isLoggedIn()) {
                return null;
            }

            return $this->checkoutSession->getQuote();
        } catch (NoSuchEntityException $e) {
            $this->rewardResource->getLogger()->log(
                sprintf('Error getting quote: %s', $e->getMessage())
            );
            return null;
        }
    }

    /**
     * Save applied coupons to quote
     *
     * @param Quote $quote
     * @param array $appliedCoupons
     * @throws \Exception
     */
    protected function saveAppliedCoupons(Quote $quote, array $appliedCoupons): void
    {
        try {
            $appliedCouponsString = $this->json->serialize($appliedCoupons);
            $quote->setData('leat_loyalty_applied_coupons', $appliedCouponsString);

            if (empty($quote->getItems())) {
                $this->emptyCartGiftManager->checkForApplicableGifts($quote);
            }

            $this->cartRepository->save($quote);
        } catch (\Exception $e) {
            $this->rewardResource->getLogger()->log(
                sprintf('Error saving applied coupons: %s', $e->getMessage()),
            );
            throw $e;
        }
    }

    /**
     * Process the applied coupons as collected
     *
     * @param Quote $quote
     * @return void
     */
    public function markCouponsAsCollected(Quote $quote): void
    {
        $appliedCoupons = $this->getAppliedCoupons($quote);
        foreach ($appliedCoupons as $rewardUUID => $loyaltyTransactions) {
            foreach ($loyaltyTransactions as $appliedCoupon) {
                try {
                    $this->rewardResource->collectCollectableReward($appliedCoupon);
                    $this->rewardResource->getLogger()->log(sprintf(
                        'Marked coupon (Loyalty Transaction UUID: %s) as used, for customer with id: %s',
                        $appliedCoupon,
                        (string) $quote->getCustomerId()
                    ));
                } catch (\Exception $e) {
                    $this->rewardResource->getLogger('reward_error')->log(sprintf(
                        'Error marking coupon (Loyalty Transaction UUID: %s) as used: %s',
                        $appliedCoupon,
                        $e->getMessage()
                    ));
                }
            }
        }
    }

    /**
     * Returns an array of collectable reward UUIDs for the current customer
     *
     * @param Quote|null $quote
     * @param bool $hasBeenCollectedStatus
     * @return array
     * @throws LocalizedException
     */
    public function getSortedCollectableRewardUUIDs(Quote $quote = null, bool $hasBeenCollectedStatus = false): array
    {
        $quote = $quote ?? $this->getQuote();
        if (!$quote->getCustomerId()) {
            return [];
        }

        $customerId = $quote->getCustomerId();
        if (!isset($this->collectableRewardsCache[$customerId])) {
            /** @var DigitalRewardReception|PhysicalRewardReception[] $collectableRewards */
            $collectableRewards = $this->rewardResource->getCollectableRewards($quote->getCustomerId());
            $result = [];
            foreach ($collectableRewards as $collectableReward) {
                if ($collectableReward->hasBeenCollected() !== $hasBeenCollectedStatus) {
                    continue;
                }

                $result[$collectableReward->getReward()->getUuid()][] = $collectableReward->getUuid();
            }

            $this->collectableRewardsCache[$customerId] = $result;
        }


        return $this->collectableRewardsCache[$customerId];
    }

    /**
     * @param $loyaltyTransactionUUID
     * @param Quote|null $quote
     * @return string|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getRewardUUIDForLoyaltyTransactionUUID($loyaltyTransactionUUID, Quote $quote = null): ?string
    {
        $collectableRewardUUIDs = $this->getSortedCollectableRewardUUIDs($quote ?? $this->getQuote());
        foreach ($collectableRewardUUIDs as $rewardUUID => $loyaltyTransactionUUIDs) {
            if (in_array($loyaltyTransactionUUID, $loyaltyTransactionUUIDs)) {
                return $rewardUUID;
            }
        }

        return null;
    }
}
