<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Leat\Loyalty\Model\AppliedCouponsManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

class ProcessRewards implements ObserverInterface
{
    /**
     * @param AppliedCouponsManager $appliedCouponsManager
     */
    public function __construct(
        protected AppliedCouponsManager $appliedCouponsManager
    ) {
    }

    /**
     * Observer for checkout_submit_all_after
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $event = $observer->getEvent();
        $quote = $event->getData('quote');

        $this->appliedCouponsManager->markCouponsAsCollected($quote);
    }
}
