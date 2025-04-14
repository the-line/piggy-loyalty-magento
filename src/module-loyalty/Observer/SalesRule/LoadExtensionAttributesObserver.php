<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer\SalesRule;

use Leat\Loyalty\Model\SalesRule\ExtensionAttributesRepository;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\SalesRule\Api\Data\RuleInterface;
use Magento\SalesRule\Model\ResourceModel\Rule\Collection;

class LoadExtensionAttributesObserver implements ObserverInterface
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
     * Add extension attributes after collection loads
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $collection = $observer->getEvent()->getRuleCollection();

        // Only process SalesRule collections
        if (!$collection instanceof Collection) {
            return;
        }

        // Skip if the collection is empty
        if ($collection->count() === 0) {
            return;
        }

        foreach ($collection->getItems() as $rule) {
            $ruleId = (int)$rule->getId();
            if (!$ruleId) {
                continue;
            }

            try {
                $extensionAttributes = $rule->getExtensionAttributes();
                if (!$extensionAttributes) {
                    $extensionAttributes = $this->extensionFactory->create(RuleInterface::class);
                }

                $ruleExtension = $this->extensionRepository->getByRuleId($ruleId);

                // Set extension attributes for gift_skus
                $giftSkus = $ruleExtension->getGiftSkus();
                if ($giftSkus) {
                    $extensionAttributes->setGiftSkus($giftSkus);

                    // Also set directly on the model for form handling
                    $rule->setData('gift_skus', $giftSkus);
                }

                $rule->setExtensionAttributes($extensionAttributes);
            } catch (\Exception $e) {
                // Log error but don't break the collection loading
            }
        }
    }
}
