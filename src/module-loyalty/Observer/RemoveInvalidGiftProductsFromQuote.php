<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Leat\Loyalty\Model\SalesRule\GiftProductManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RemoveInvalidGiftProductsFromQuote implements ObserverInterface
{
    /**
     * @var GiftProductManager
     */
    private $giftProductManager;

    /**
     * @param GiftProductManager $giftProductManager
     */
    public function __construct(
        GiftProductManager $giftProductManager
    ) {
        $this->giftProductManager = $giftProductManager;
    }

    /**
     * Remove gift products when rules no longer apply
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $quote = $observer->getQuote();

        if (!$quote) {
            return;
        }

        // Get the currently applied rule IDs
        $appliedRuleIds = $quote->getAppliedRuleIds();
        $activeRuleIds = $appliedRuleIds ? explode(',', $appliedRuleIds) : [];

        // Convert to integers
        $activeRuleIds = array_map('intval', $activeRuleIds);

        // Remove gifts that no longer have active rules
        $this->giftProductManager->removeInvalidGiftProducts($quote, $activeRuleIds);
    }
}
