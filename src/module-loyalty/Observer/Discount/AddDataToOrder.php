<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer\Discount;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class AddDataToOrder implements ObserverInterface
{
    /**
     * transfer the order comment from the quote object to the order object during the
     * sales_model_service_quote_submit_before event
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /* @var Order $order */
        $order = $observer->getEvent()->getOrder();

        /** @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();

        $order->setData(ProcessDiscount::FIELD_DESCRIPTIONS, $quote->getData(ProcessDiscount::FIELD_DESCRIPTIONS));
        $order->setData(ProcessDiscount::FIELD_DISCOUNT_AMOUNTS, $quote->getData(ProcessDiscount::FIELD_DISCOUNT_AMOUNTS));
        $order->setData(ProcessDiscount::FIELD_BASE_AMOUNTS, $quote->getData(ProcessDiscount::FIELD_BASE_AMOUNTS));
    }
}
