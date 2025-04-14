<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\Quote;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Logger;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Api\RuleRepositoryInterface;

class EnforceGiftItemQuantitiesPlugin
{
    /**
     * @var RuleRepositoryInterface
     */
    private RuleRepositoryInterface $ruleRepository;

    private Logger $logger;

    /**
     * @var array Cached rule discount quantities
     */
    private array $ruleDiscountQty = [];

    /**
     * @param RuleRepositoryInterface $ruleRepository
     * @param Connector $leatConnector
     */
    public function __construct(
        RuleRepositoryInterface $ruleRepository,
        Connector $leatConnector
    ) {
        $this->ruleRepository = $ruleRepository;
        $this->logger = $leatConnector->getLogger('reward');
    }

    /**
     * Before saving the quote, enforce gift item quantities
     *
     * @param Quote $subject
     * @return null
     */
    public function beforeCollectTotals(Quote $subject)
    {
        $this->enforceGiftItemQuantities($subject);
        return null;
    }

    /**
     * Enforce gift item quantities based on rules
     *
     * @param Quote $quote
     * @return void
     */
    private function enforceGiftItemQuantities(Quote $quote): void
    {
        // Group gift items by rule ID
        $giftItemsByRule = [];
        $giftItemsChanged = false;

        foreach ($quote->getAllItems() as $item) {
            $extensionAttributes = $item->getExtensionAttributes();
            if (!$extensionAttributes || !$extensionAttributes->getIsGift()) {
                continue;
            }

            $ruleId = $extensionAttributes->getGiftRuleId();
            if (!$ruleId) {
                continue;
            }

            if (!isset($giftItemsByRule[$ruleId])) {
                $giftItemsByRule[$ruleId] = [];
            }

            $giftItemsByRule[$ruleId][] = $item;
        }

        // Nothing to enforce if no gift items found
        if (empty($giftItemsByRule)) {
            return;
        }

        // For each rule, enforce quantity limits
        foreach ($giftItemsByRule as $ruleId => $items) {
            $maxQty = $this->getRuleDiscountQty($ruleId);
            $totalQty = array_sum(array_map(function ($item) {
                return (float) $item->getQty();
            }, $items));

            $this->logger->debug(sprintf(
                'Rule %d: Found %d gift items with total qty %.2f, max allowed: %d',
                $ruleId,
                count($items),
                $totalQty,
                $maxQty
            ));

            // If the total quantity exceeds the rule's limit
            if ($totalQty > $maxQty) {
                $this->logger->debug(sprintf(
                    'Rule %d: Total gift qty %.2f exceeds limit %d, adjusting',
                    $ruleId,
                    $totalQty,
                    $maxQty
                ));

                $giftItemsChanged = true;
                $this->adjustGiftItemQuantities($items, $maxQty);
            }
        }

        // If gift items were changed, recollect totals to update prices
        if ($giftItemsChanged) {
            $quote->setTriggerRecollect(true);
        }
    }

    /**
     * Adjust gift item quantities to match the maximum allowed
     *
     * @param array $items
     * @param int $maxTotalQty
     * @return void
     */
    private function adjustGiftItemQuantities(array $items, int $maxTotalQty): void
    {
        // If max qty is 0 or negative, remove all items
        if ($maxTotalQty <= 0) {
            foreach ($items as $item) {
                $item->setQty(0);
            }
            return;
        }

        // Sort items by added date (oldest first)
        usort($items, function ($a, $b) {
            return $a->getCreatedAt() <=> $b->getCreatedAt();
        });

        // Distribute the max qty among the items
        $remainingQty = $maxTotalQty;

        foreach ($items as $item) {
            $itemQty = min((float) $item->getQty(), $remainingQty);

            if ($itemQty != $item->getQty()) {
                $this->logger->debug(sprintf(
                    'Adjusting gift item %d (SKU: %s) quantity from %.2f to %.2f',
                    $item->getItemId(),
                    $item->getSku(),
                    $item->getQty(),
                    $itemQty
                ));

                $item->setQty($itemQty);
            }

            $remainingQty -= $itemQty;

            if ($remainingQty <= 0) {
                break;
            }
        }
    }

    /**
     * Get the discount_qty value for a rule
     *
     * @param int $ruleId
     * @return int
     */
    private function getRuleDiscountQty(int $ruleId): int
    {
        if (isset($this->ruleDiscountQty[$ruleId])) {
            return $this->ruleDiscountQty[$ruleId];
        }

        try {
            $rule = $this->ruleRepository->getById($ruleId);
            $discountQty = (int) $rule->getDiscountQty() ?: 1; // Default to 1 if not set
            $this->ruleDiscountQty[$ruleId] = $discountQty;
            return $discountQty;
        } catch (\Exception $e) {
            $this->logger->log(sprintf(
                'Error getting discount_qty for rule %d: %s',
                $ruleId,
                $e->getMessage()
            ));
            return 1; // Default to 1 if there's an error
        }
    }
}
