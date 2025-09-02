<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Plugin\SalesRule\Model\Rule;

use Leat\LoyaltyAdminUI\Model\Config\Source\Reward;
use Leat\LoyaltyAdminUI\Plugin\SalesRule\Model\Rule\CouponTypeOptionsPlugin;
use Magento\Framework\App\RequestInterface;
use Magento\SalesRule\Model\Rule\DataProvider;

class DataProviderPlugin
{
    public function __construct(
        protected Reward $rewardOptions,
        protected RequestInterface $request
    ) {
    }

    /**
     * Add Leat reward UUID and gift SKUs to form data
     *
     * @param DataProvider $subject
     * @param array|null $result
     * @return array|null
     */
    public function afterGetData(DataProvider $subject, null|array $result): null|array
    {
        if ($result === null) {
            return $result;
        }

        $collection = $subject->getCollection();

        foreach ($result as $ruleId => &$item) {
            // Check if we have any rules with extension attributes
            $rule = $collection->getItemById($ruleId);
            if (!$rule) {
                continue;
            }

            // Get extension attributes if they exist
            $extensionAttributes = $rule->getExtensionAttributes();
            if (!$extensionAttributes) {
                continue;
            }

            // Set gift SKUs if available
            if (is_object($extensionAttributes) && $extensionAttributes->getGiftSkus()) {
                $item['gift_skus'] = $extensionAttributes->getGiftSkus();
            }
        }

        return $result;
    }
}
