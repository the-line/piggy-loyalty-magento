<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Plugin\SalesRule;

use Magento\SalesRule\Model\Rule\Action\SimpleActionOptionsProvider;

class AddGiftActionPlugin
{
    const string ADD_GIFT_PRODUCTS_ACTION = 'add_gift_products';

    /**
     * Add gift products action option
     *
     * @param SimpleActionOptionsProvider $subject
     * @param array $result
     * @return array
     */
    public function afterToOptionArray(SimpleActionOptionsProvider $subject, array $result): array
    {
        $result[] = [
            'label' => __('Leat - Add gift products to cart'),
            'value' => self::ADD_GIFT_PRODUCTS_ACTION
        ];

        return $result;
    }
}
