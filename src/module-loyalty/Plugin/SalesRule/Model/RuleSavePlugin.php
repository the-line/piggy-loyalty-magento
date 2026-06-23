<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\SalesRule\Model;

use Leat\Loyalty\Model\SalesRule\ExtensionAttributesRepository;
use Leat\Loyalty\Model\SalesRule\ExtensionAttributesFactory;
use Magento\SalesRule\Model\Rule;

class RuleSavePlugin
{
    /**
     * @var ExtensionAttributesRepository
     */
    private $extensionRepository;

    /**
     * @var ExtensionAttributesFactory
     */
    private $extensionFactory;

    /**
     * @param ExtensionAttributesRepository $extensionRepository
     * @param ExtensionAttributesFactory $extensionFactory
     */
    public function __construct(
        ExtensionAttributesRepository $extensionRepository,
        ExtensionAttributesFactory $extensionFactory
    ) {
        $this->extensionRepository = $extensionRepository;
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * Save extension attributes after model save
     *
     * @param Rule $subject
     * @param Rule $result
     * @return Rule
     */
    public function afterSave(Rule $subject, Rule $result): Rule
    {
        $ruleId = (int)$result->getId();
        if (!$ruleId) {
            return $result;
        }

        try {
            $extensionAttributes = $this->extensionRepository->getByRuleId($ruleId);
            $extensionAttributes->setRuleId($ruleId);

            // Get gift_skus value from model and extension attributes
            $giftSkus = $result->getData('gift_skus');
            $conditions = $result->getConditions()->asArray();
            $rewardUUID = $this->getConditionsRewardUUID($conditions);

            // Set value on extension attributes
            $extensionAttributes->setGiftSkus($giftSkus);
            $extensionAttributes->setRewardUUID($rewardUUID);

            $this->extensionRepository->save($extensionAttributes);
        } catch (\Exception $e) {
            // Log or handle the exception
        }

        return $result;
    }

    /**
     * Recursively search for the reward UUID in the conditions array
     *
     * @param array $conditions
     * @return string|null
     */
    protected function getConditionsRewardUUID(array $conditions): ?string
    {
        $rewardUUID = null;
        foreach ($conditions['conditions'] as $subCondition) {
            if (isset($subCondition['conditions']) && is_array($subCondition['conditions'])) {
                return $this->getConditionsRewardUUID($subCondition);
            }

            if ($subCondition['type'] !== \Leat\LoyaltyAdminUI\Model\Rule\Condition\Reward::class) {
                continue;
            }

            $rewardUUID = implode(',', $subCondition['value']);
        }

        return $rewardUUID;
    }
}
