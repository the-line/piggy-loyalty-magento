<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Observer;

use Leat\LoyaltyAdminUI\Model\Rule\Condition\Reward;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class AddLeatRuleToSalesRuleConditions implements ObserverInterface
{
    /**
     * Observer for salesrule_rule_condition_combine
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $additional = $observer->getEvent()->getAdditional();
        if (!$additional) {
            return;
        }

        $conditions = $additional->getConditions();
        if (!$conditions) {
            $conditions = [];
        }

        // Add our custom Leat reward condition to the list
        $conditions[] = [
            'label' => __('Customer has active Leat Loyalty reward'),
            'value' => Reward::class
        ];

        $additional->setConditions($conditions);
    }
}
