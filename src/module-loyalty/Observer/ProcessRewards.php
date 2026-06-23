<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Leat\Loyalty\Model\AppliedCouponsManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Api\OrderRepositoryInterface;

class ProcessRewards implements ObserverInterface
{
    public function __construct(
        protected AppliedCouponsManager $appliedCouponsManager,
        protected OrderRepositoryInterface $orderRepository
    ) {
    }

    public function execute(Observer $observer)
    {
        $event = $observer->getEvent();
        $quote = $event->getData('quote');
        $order = $event->getData('order');

        $response = $this->appliedCouponsManager->markCouponsAsCollected($quote);

        if ($response && !empty($response)) {
            $order->setData('leat_loyalty_applied_coupons', json_encode($response));
            $this->orderRepository->save($order);
        }
    }
}
