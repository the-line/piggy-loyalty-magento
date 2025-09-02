<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Leat\LoyaltyAdminUI\Plugin\SalesRule\AddGiftActionPlugin;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\SalesRule\Api\Data\RuleInterface;

class AddGiftProductFieldsToRule implements ObserverInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var ExtensionAttributesFactory
     */
    private $extensionAttributesFactory;

    /**
     * @param RequestInterface $request
     * @param ExtensionAttributesFactory $extensionAttributesFactory
     */
    public function __construct(
        RequestInterface $request,
        ExtensionAttributesFactory $extensionAttributesFactory
    ) {
        $this->request = $request;
        $this->extensionAttributesFactory = $extensionAttributesFactory;
    }

    /**
     * Add gift_skus field to the rule being saved
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $rule = $observer->getRule();

        // Only process gift product action rules
        if ($rule->getSimpleAction() !== AddGiftActionPlugin::ADD_GIFT_PRODUCTS_ACTION) {
            return;
        }

        $data = $this->request->getPostValue();
        if (isset($data['gift_skus'])) {
            // Set the data directly on the rule for immediate use
            $rule->setData('gift_skus', $data['gift_skus']);
        }
    }
}
