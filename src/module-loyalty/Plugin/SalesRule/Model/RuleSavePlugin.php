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

            // Set value on extension attributes
            $extensionAttributes->setGiftSkus($giftSkus);

            $this->extensionRepository->save($extensionAttributes);
        } catch (\Exception $e) {
            // Log or handle the exception
        }

        return $result;
    }
}
