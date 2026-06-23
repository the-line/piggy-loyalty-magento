<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer\Discount;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\Coupon;

class ProcessDiscount implements ObserverInterface
{
    public const DEFAULT_TAX_RATE = 21;
    public const FIELD_DESCRIPTIONS = 'discount_description_array';
    public const FIELD_DISCOUNT_AMOUNTS = 'discount_amount_array';
    public const FIELD_BASE_AMOUNTS = 'base_discount_amount_array';
    public const SUB_FIELD_COUPON_TYPE = 'type';
    public const SUB_FIELD_COUPON_DESCRIPTION = 'label';
    public const SUB_FIELD_SIMPLE_ACTION = 'simple_action';
    public const SUB_FIELD_RULE_DISCOUNT_AMOUNT = 'rule_discount_amount';
    public const SUB_FIELD_APPLY_TO_SHIPPING = 'apply_to_shipping';

    private $couponCache = [];

    /**
     * ProcessDiscount constructor.
     * @param SerializerInterface $serializer
     * @param Coupon $couponModel
     */
    public function __construct(
        protected SerializerInterface $serializer,
        protected Coupon $couponModel
    ) {
    }

    /**
     * Observer for salesrule_validator_process
     * Gets called everytime a rule applies discount to a cart item. This can be used to create a nice breakdown of
     * all applied discount rules. This data is added to the quote object.
     *
     * discount_description_array will contain an array with the rule id as key and an array containing the description
     * and coupon type (use coupon yes/no) as value
     *
     * discount_amount_array will contain an array with the taxrates found in the cart as array keys and the discount
     * amounts for each applied rule on products in that taxrate as value
     *
     * base_discount_amount_array contains the same as discount_amount_array except this one is based on base_discount_amount
     * instead of discount_amount
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $rule = $observer->getData('rule');
        $quote = $observer->getData('quote');
        $quoteItem = $observer->getData('item');
        $discountData = $observer->getData('result');

        $ruleId = $rule->getId();
        $taxPercent = $quoteItem->getTaxPercent() ?? self::DEFAULT_TAX_RATE;
        $discountAmount = $discountData->getAmount();
        $baseDiscountAmount = $discountData->getBaseAmount();

        $discountDescriptions = $this->getField($quote, self::FIELD_DESCRIPTIONS);
        $discountAmountArray = $this->getField($quote, self::FIELD_DISCOUNT_AMOUNTS);
        $baseDiscountAmountArray = $this->getField($quote, self::FIELD_BASE_AMOUNTS);

        $discountDescriptions[$ruleId] = [
            self::SUB_FIELD_COUPON_TYPE => $rule->getCouponType(),
            self::SUB_FIELD_COUPON_DESCRIPTION => !empty($rule->getDescription()) ? $rule->getDescription() : $rule->getName(),
            self::SUB_FIELD_SIMPLE_ACTION => $rule->getSimpleAction(),
            self::SUB_FIELD_RULE_DISCOUNT_AMOUNT => $rule->getDiscountAmount(),
            self::SUB_FIELD_APPLY_TO_SHIPPING => (bool) $rule->getApplyToShipping(),
        ];
        $discountAmountArray = $this->incrementField($discountAmountArray, $taxPercent, $ruleId, $discountAmount);
        $baseDiscountAmountArray = $this->incrementField($baseDiscountAmountArray, $taxPercent, $ruleId, $baseDiscountAmount);

        $quote->setDiscountDescriptionArray($this->serializer->serialize($discountDescriptions));
        $quote->setDiscountAmountArray($this->serializer->serialize($discountAmountArray));
        $quote->setBaseDiscountAmountArray($this->serializer->serialize($baseDiscountAmountArray));
    }

    /**
     * Get field from quote and if set unserialize it
     *
     * @param Quote $quote
     * @param string $field
     * @return array|bool|float|int|string|null
     */
    private function getField(Quote $quote, string $field)
    {
        $data = $quote->getData($field);
        if (!$data) {
            return [];
        }
        return $this->serializer->unserialize($data);
    }

    /**
     * initialize a nested field in the array, if needed, and increments the current value with the value passed as argument
     *
     * @param array $data
     * @param $taxRate
     * @param $ruleId
     * @param $amount
     * @return array
     */
    private function incrementField(array $data, $taxRate, $ruleId, $amount)
    {
        if (!isset($data[$taxRate])) {
            $data[$taxRate] = [];
        }
        if (!isset($data[$taxRate][$ruleId])) {
            $data[$taxRate][$ruleId] = 0;
        }
        $data[$taxRate][$ruleId] += $amount;

        return $data;
    }
}
