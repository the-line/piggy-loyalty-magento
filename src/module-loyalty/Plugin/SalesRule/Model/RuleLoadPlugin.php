<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\SalesRule\Model;

use Leat\Loyalty\Model\SalesRule\ExtensionAttributesRepository;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Model\Rule;

class RuleLoadPlugin
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
     * After the rule is loaded, load extension attributes
     *
     * @param Rule $subject
     * @param Rule $result
     * @return Rule
     */
    public function afterLoad(Rule $subject, Rule $result): Rule
    {
        $ruleId = (int)$result->getId();
        if (!$ruleId) {
            return $result;
        }

        try {
            $extensionAttributes = $result->getExtensionAttributes();
            if (!$extensionAttributes) {
                $extensionAttributes = $this->extensionFactory->create(RuleInterface::class);
            }

            $ruleExtension = $this->extensionRepository->getByRuleId($ruleId);

            if (is_array($extensionAttributes)) {
                $extensionAttributes['gift_skus'] = $ruleExtension->getGiftSkus();
            } else {
                $result->setExtensionAttributes($extensionAttributes);
            }

            // Also set the data directly for use in forms
            if ($ruleExtension->getGiftSkus()) {
                $result->setData('gift_skus', $ruleExtension->getGiftSkus());
            }
        } catch (\Exception $e) {
            // Log error but don't break the rule loading
        }

        return $result;
    }
}
