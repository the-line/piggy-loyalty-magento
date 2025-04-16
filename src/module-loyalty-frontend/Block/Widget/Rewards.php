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
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Block\BlockInterface;
use Piggy\Api\Models\Loyalty\Rewards\Reward;

class Rewards extends GenericWidgetBlock
{
    protected $_template = "Leat_LoyaltyFrontend::widget/rewards.phtml";

    /**
     * @var string
     */
    protected string $defaultId = 'leat-rewards';

    /**
     * @var string
     */
    protected string $defaultCssClass = 'leat-rewards-container';

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
     * Should show points balance
     *
     * @return bool
     */
    public function shouldShowPoints(): bool
    {
        return (bool)($this->getData('show_points') ?? true);
    }

    /**
     * Get customer points balance
     *
     * @return int
     */
    public function getPointsBalance(): int
    {
        if ($this->show()) {
            try {
                $contact = $this->getContactForCustomer();
                return $contact?->getCreditBalance()->getBalance() ?? 0;
            } catch (\Throwable $e) {
                $this->getLogger()->log($e->getMessage());
                return 0;
            }
        }

        return 0;
    }

    /**
     * Get customer prepaid points balance
     *
     * @return int
     */
    public function getPrepaidBalance(): int
    {
        if ($this->show()) {
            try {
                $contact = $this->getContactForCustomer();
                return $contact?->getPrepaidBalance()->getBalanceInCents() ?? 0;
            } catch (\Throwable $e) {
                $this->getLogger()->log($e->getMessage());
                return 0;
            }
        }

        return 0;
    }

    /**
     * Get available rewards for the customer
     *
     * @return Reward[]
     */
    public function getAvailableRewards(): array
    {
        if ($this->show()) {
            try {
                $customerId = $this->customerSession->getCustomerId();
                if (!$customerId) {
                    return [];
                }

                // Use the RewardResource to get rewards
                return $this->rewardResource->getAvailableRewards(
                    (int)$customerId,
                    (int)$this->getStoreId()
                );
            } catch (\Throwable $e) {
                // Do nothing but log the error
                $this->getLogger()->log($e->getMessage());
            }
        }

        return [];
    }

    /**
     * Get widget heading
     *
     * @return string
     */
    public function getWidgetHeading(): string
    {
        return (string) ($this->getData('widget_heading') ?: $this->leatConfig->getRewardsWidgetHeading() ?: __('Rewards'));
    }
}
