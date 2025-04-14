<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\SalesRule;

use Leat\LoyaltyAdminUI\Plugin\SalesRule\Model\Rule\CouponTypeOptionsPlugin;
use Leat\Loyalty\Model\SalesRule\ExtensionAttributesRepository;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Api\RuleRepositoryInterface;

class ExtensionAttributesPlugin
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
     * Add extension attributes after rule is loaded
     *
     * @param RuleRepositoryInterface $subject
     * @param RuleInterface $rule
     * @return RuleInterface
     */
    public function afterGet(
        RuleRepositoryInterface $subject,
        RuleInterface $rule
    ): RuleInterface {
        $ruleId = (int)$rule->getRuleId();

        if (!$ruleId) {
            return $rule;
        }

        try {
            $extensionAttributes = $rule->getExtensionAttributes();
            if (!$extensionAttributes) {
                $extensionAttributes = $this->extensionFactory->create(RuleInterface::class);
            }

            $ruleExtension = $this->extensionRepository->getByRuleId($ruleId);

            // Set extension attributes for gift_skus
            $extensionAttributes->setGiftSkus($ruleExtension->getGiftSkus());

            $rule->setExtensionAttributes($extensionAttributes);

            // Also set the data directly for usage in forms
            if ($ruleExtension->getGiftSkus()) {
                $rule->setData('gift_skus', $ruleExtension->getGiftSkus());
            }
        } catch (\Exception $e) {
            // Log error but don't break the rule loading
        }

        return $rule;
    }

    /**
     * Save extension attributes before rule is saved
     *
     * @param RuleRepositoryInterface $subject
     * @param RuleInterface $rule
     * @return array
     * @throws LocalizedException
     */
    public function beforeSave(
        RuleRepositoryInterface $subject,
        RuleInterface $rule
    ): array {
        $extensionAttributes = $rule->getExtensionAttributes();
        $ruleId = (int)$rule->getRuleId();

        // Get gift_skus data
        $giftSkus = $rule->getData('gift_skus');

        // If extension attributes exist and have gift_skus, they take precedence
        if ($extensionAttributes && $extensionAttributes->getGiftSkus() !== null) {
            $giftSkus = $extensionAttributes->getGiftSkus();
        }

        // We'll save the extension attributes after the rule is saved
        // Store the value temporarily in the rule object
        $rule->setData('_tmp_extension_gift_skus', $giftSkus);

        return [$rule];
    }

    /**
     * Save extension attributes after rule is saved
     *
     * @param RuleRepositoryInterface $subject
     * @param RuleInterface $result
     * @param RuleInterface $rule
     * @return RuleInterface
     */
    public function afterSave(
        RuleRepositoryInterface $subject,
        RuleInterface $result,
        RuleInterface $rule
    ): RuleInterface {
        $ruleId = (int)$result->getRuleId();

        if (!$ruleId) {
            return $result;
        }

        try {
            $extensionAttributes = $this->extensionRepository->getByRuleId($ruleId);
            $extensionAttributes->setRuleId($ruleId);

            // Get the temporarily stored gift_skus value
            $giftSkus = $rule->getData('_tmp_extension_gift_skus');

            // Set the gift_skus value on extension attributes
            $extensionAttributes->setGiftSkus($giftSkus);

            // Save the extension attributes
            $this->extensionRepository->save($extensionAttributes);

            // Update the result with the extension attributes
            $resultExtensionAttributes = $result->getExtensionAttributes();
            if (!$resultExtensionAttributes) {
                $resultExtensionAttributes = $this->extensionFactory->create(RuleInterface::class);
            }

            $resultExtensionAttributes->setGiftSkus($giftSkus);
            $result->setExtensionAttributes($resultExtensionAttributes);

            // Also set directly on the model for form handling
            if ($giftSkus !== null) {
                $result->setData('gift_skus', $giftSkus);
            }
        } catch (\Exception $e) {
            // Log error but don't break the rule saving
        }

        return $result;
    }
}
