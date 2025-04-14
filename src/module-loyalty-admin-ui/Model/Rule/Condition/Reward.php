<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Model\Rule\Condition;

use Leat\LoyaltyAdminUI\Model\Config\Source\Reward as RewardSource;
use Leat\Loyalty\Model\AppliedCouponsManager;
use Leat\Loyalty\Model\ResourceModel\Loyalty\RewardResource;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Model\AbstractModel;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;

class Reward extends AbstractCondition
{
    /**
     * Cache for customer's collectable rewards
     *
     * @var array
     */
    private array $collectableRewardsCache = [];

    /**
     * @param Context $context
     * @param RewardSource $rewardSource
     * @param RewardResource $rewardResource
     * @param AppliedCouponsManager $appliedCouponsManager
     * @param Session $customerSession
     * @param CustomerFactory $customerFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        protected RewardSource $rewardSource,
        protected RewardResource $rewardResource,
        protected AppliedCouponsManager $appliedCouponsManager,
        protected Session $customerSession,
        protected CustomerFactory $customerFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Load attribute options
     *
     * @return $this
     */
    public function loadAttributeOptions()
    {
        $this->setAttributeOption([
            'leat_reward' => __('Customer Has Leat Reward')
        ]);

        return $this;
    }

    /**
     * Get input type
     *
     * @return string
     */
    public function getInputType()
    {
        return 'multiselect';
    }

    /**
     * Get value element type
     *
     * @return string
     */
    public function getValueElementType()
    {
        return 'multiselect';
    }

    /**
     * Get value select options
     *
     * @return array
     */
    public function getValueSelectOptions()
    {
        if (!$this->hasData('value_select_options')) {
            $this->setData('value_select_options', $this->rewardSource->toOptionArray());
        }

        return $this->getData('value_select_options');
    }

    /**
     * Get HTML of condition string
     *
     * @return string
     */
    public function asHtml()
    {
        return $this->getTypeElementHtml()
            . __('Customer has active Leat reward')
            . $this->getOperatorElementHtml()
            . $this->getValueElementHtml()
            . $this->getRemoveLinkHtml();
    }

    /**
     * Validate the condition against a quote
     *
     * @param AbstractModel $model
     * @return bool
     */
    public function validate(AbstractModel $model)
    {
        $quote = $model;
        if (!$quote instanceof \Magento\Quote\Model\Quote) {
            $quote = $model->getQuote();
        }

        $customerId = $quote->getCustomerId();
        if (!$customerId) {
            return false;
        }

        $appliedCoupons = $this->appliedCouponsManager->getAllAppliedCoupons(false, $quote);
        $values = [];
        foreach ($appliedCoupons as $rewardUUID => $loyaltyTransactionUUID) {
            $values = array_merge($values, array_fill_keys($loyaltyTransactionUUID, $rewardUUID));
        }

        return $this->validateAttribute($values);
    }
}
