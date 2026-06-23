<?php

namespace Leat\Loyalty\Observer\Discount;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class ResetDiscounts implements ObserverInterface
{
    /**
     * Observer for sales_quote_collect_totals_before
     * Resets all values before totals get calculated
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $quote = $observer->getData('quote');
        $quote->addData(
            [
                ProcessDiscount::FIELD_DESCRIPTIONS => null,
                ProcessDiscount::FIELD_DISCOUNT_AMOUNTS => null,
                ProcessDiscount::FIELD_BASE_AMOUNTS => null
            ]
        );
    }
}
