<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\SalesRule;

use Leat\Loyalty\Model\SalesRule\Action\AddGiftProducts;
use Leat\LoyaltyAdminUI\Plugin\SalesRule\AddGiftActionPlugin;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\Rule\Action\Discount\CalculatorFactory;
use Magento\SalesRule\Model\Utility;
use Magento\SalesRule\Model\Validator;

class EmptyCartGiftManager
{
    /**
     * @param Validator $calculator
     * @param Utility $utility
     * @param CalculatorFactory $calculatorFactory
     */
    public function __construct(
        protected Validator $calculator,
        protected Utility $utility,
        protected CalculatorFactory $calculatorFactory,
    ) {
    }

    /**
     * By default, no salesrules are applied on empty quotes
     * This function circumvents that and applies our add gift rule even if quote is empty
     *
     * @param Quote $quote
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Zend_Db_Select_Exception
     */
    public function checkForApplicableGifts(Quote $quote): void
    {
        $this->calculator->initFromQuote($quote);
        $address = $quote->getShippingAddress();
        $rules = $this->calculator->getRules($address);

        foreach ($rules as $rule) {
            if ($rule->getSimpleAction() === AddGiftActionPlugin::ADD_GIFT_PRODUCTS_ACTION && $this->utility->canProcessRule($rule, $address)) {
                /** @var AddGiftProducts $discountCalculator */
                $discountCalculator = $this->calculatorFactory->create($rule->getSimpleAction());
                $discountCalculator->addGiftProducts($rule, $quote);
                break;
            }
        }
    }
}
