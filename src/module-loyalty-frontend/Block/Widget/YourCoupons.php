<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Widget;

use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\Loyalty\Model\ResourceModel\Loyalty\RewardResource;
use Leat\LoyaltyFrontend\Block\GenericWidgetBlock;
use Leat\Loyalty\Model\Config;
use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Block\BlockInterface;
use Piggy\Api\Models\Loyalty\Rewards\CollectableReward;
use Piggy\Api\Models\Loyalty\Rewards\DigitalReward;
use Piggy\Api\Models\Loyalty\Rewards\PhysicalReward;

class YourCoupons extends GenericWidgetBlock
{
    protected const string LOGGER_PURPOSE = 'your_coupons_widget';

    protected $_template = 'Leat_LoyaltyFrontend::widget/your_coupons.phtml';

    /**
     * @var string
     */
    protected string $defaultId = 'leat-coupons';

    /**
     * @var string
     */
    protected string $defaultCssClass = 'leat-coupons-container';

    /**
     * @var CollectableReward[]|null
     */
    protected ?array $collectableRewards = null;

    public function __construct(
        StoreManagerInterface $storeManager,
        Config $config,
        Session $customerSession,
        ContactResource $contactResource,
        Connector $connector,
        RequestTypePool $requestTypePool,
        protected RewardResource $rewardResource,
        Context $context,
        protected \Leat\Loyalty\Model\Config $leatConfig
    ) {
        parent::__construct(
            $storeManager,
            $config,
            $customerSession,
            $contactResource,
            $connector,
            $requestTypePool,
            $context
        );
    }

    /**
     * Get collectable rewards for the current customer
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getCollectableRewards(): array
    {
        if ($this->collectableRewards === null) {
            try {
                if (!$this->show()) {
                    return [];
                }

                $customerId = (int)$this->customerSession->getCustomerId();
                $storeId = $this->getStoreId();

                // Get collectable rewards using RewardResource
                $this->collectableRewards = $this->rewardResource->getCollectableRewards($customerId, $storeId);

                // Filter to only show collectable rewards
                $this->collectableRewards = array_filter($this->collectableRewards, function ($reward) {
                    return !$reward->hasBeenCollected();
                });
            } catch (\Throwable $e) {
                $this->getLogger()->log($e);
                $this->collectableRewards = [];
            }
        }

        return $this->collectableRewards;
    }

    /**
     * Get the JSON representation of collectible rewards for the template
     *
     * @return string
     */
    public function getCollectableRewardsJson(): string
    {
        try {
            /** @var CollectableReward[] $rewards */
            $rewards = $this->getCollectableRewards();

            // Create a simplified array for JS
            $rewardsData = [];
            foreach ($rewards as $collectableReward) {
                /** @var PhysicalReward|DigitalReward $reward */
                $reward = $collectableReward->getReward();
                $rewardData = [
                    'id' => $collectableReward->getUuid(),
                    'name' => $reward->getTitle(),
                    'description' => $reward->getDescription(),
                    'expires_at' => $collectableReward->getExpiresAt(),
                    'is_active' => !$collectableReward->hasBeenCollected(),
                ];

                // Add image URL if available
                if ($reward->getMedia()) {
                    $rewardData['image_url'] = $reward->getMedia()->getValue();
                }

                $rewardsData[] = $rewardData;
            }

            return json_encode($rewardsData);
        } catch (\Throwable $e) {
            $this->getLogger()->log($e);
            return json_encode([]);
        }
    }

    /**
     * Alias for getCollectableRewardsJson to be used in the template
     *
     * @return string
     */
    public function getCouponsJson(): string
    {
        return $this->getCollectableRewardsJson();
    }

    /**
     * Get widget heading
     *
     * @return string
     */
    public function getWidgetHeading(): string
    {
        return (string) ($this->getData('widget_heading') ?: $this->leatConfig->getYourCouponsWidgetHeading() ?: __('Your Coupons'));
    }
}
