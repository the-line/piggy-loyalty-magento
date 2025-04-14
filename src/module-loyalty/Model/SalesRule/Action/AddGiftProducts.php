<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\SalesRule\Action;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\SalesRule\GiftProductManager;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\Rule\Action\Discount\AbstractDiscount;
use Magento\SalesRule\Model\Rule\Action\Discount\DataFactory;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\Validator;

class AddGiftProducts extends AbstractDiscount
{
    /**
     * @param Validator $validator
     * @param DataFactory $discountDataFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param GiftProductManager $giftProductManager
     * @param Connector $leatConnector
     */
    public function __construct(
        Validator $validator,
        DataFactory $discountDataFactory,
        PriceCurrencyInterface $priceCurrency,
        protected GiftProductManager $giftProductManager,
        protected Connector $leatConnector,
    ) {
        parent::__construct($validator, $discountDataFactory, $priceCurrency);
    }

    /**
     * {@inheritdoc}
     */
    public function calculate($rule, $item, $qty)
    {
        $logger = $this->leatConnector->getLogger('reward');
        $discountData = $this->discountFactory->create();

        // We're not providing a discount, just adding a gift
        $discountData->setAmount(0);
        $discountData->setBaseAmount(0);
        $discountData->setOriginalAmount(0);
        $discountData->setBaseOriginalAmount(0);

        $logger->debug(sprintf(
            'AddGiftProducts discount action calculated for rule ID %d, item %s (SKU: %s), qty: %f',
            $rule->getId(),
            $item->getId(),
            $item->getSku(),
            $qty
        ));

        // Add gift products based on the rule action
        $this->addGiftProducts($rule, $item->getQuote());

        return $discountData;
    }

    /**
     * Add gift products based on the rule
     *
     * @param Rule $rule
     * @param Quote $quote
     * @return void
     */
    public function addGiftProducts(Rule $rule, Quote $quote): void
    {
        $logger = $this->leatConnector->getLogger('reward');

        $ruleId = (int)$rule->getId();

        // Check if gifts have already been processed for this quote and rule
        // Store a unique identifier for this quote + rule combination
        $quoteId = $quote->getId();
        $processedKey = 'processed_gift_rule_' . $ruleId;

        // If we've already processed this rule for this quote during this request, skip
        if ($quote->hasData($processedKey) && $quote->getData($processedKey) === true) {
            $logger->debug(sprintf(
                'Rule %d has already been processed for quote %s in this request, skipping',
                $ruleId,
                $quoteId
            ));
            return;
        }

        // Mark as processed to prevent duplicate additions
        $quote->setData($processedKey, true);

        // Get the discount amount (percentage) from the rule
        $discountAmount = (float)$rule->getDiscountAmount();

        // Get discount quantity (max number of gift items allowed)
        // If discount_qty is empty or 0, default to 1
        $discountQty = (int)$rule->getDiscountQty() ?: 1;

        $logger->debug(sprintf(
            'Processing gift rule %d with discount_amount: %f, discount_qty: %d for quote %s',
            $ruleId,
            $discountAmount,
            $discountQty,
            $quoteId
        ));

        // Get the SKUs from the rule
        $giftSkus = $rule->getData('gift_skus');

        // If not found directly, try to get from extension attributes
        if (empty($giftSkus)) {
            $extensionAttributes = $rule->getExtensionAttributes();
            if ($extensionAttributes && $extensionAttributes->getGiftSkus()) {
                $giftSkus = $extensionAttributes->getGiftSkus();
            }
        }

        if (empty($giftSkus)) {
            return;
        }

        // Count existing gift items from this rule
        $existingGiftCount = 0;
        $existingGiftItems = [];
        foreach ($quote->getAllItems() as $quoteItem) {
            // Look for existing gifts from this rule
            $isGiftFromThisRule = false;

            // Check by extension attributes
            $extensionAttrs = $quoteItem->getExtensionAttributes();
            if ($extensionAttrs &&
                $extensionAttrs->getIsGift() &&
                $extensionAttrs->getGiftRuleId() === $ruleId) {
                $isGiftFromThisRule = true;
            }

            if ($isGiftFromThisRule) {
                $existingGiftCount++;
                $existingGiftItems[] = $quoteItem->getSku();
            }
        }

        $logger->debug(sprintf(
            'Found %d existing gift items for rule %d in quote %s: %s',
            $existingGiftCount,
            $ruleId,
            $quoteId,
            implode(', ', $existingGiftItems)
        ));

        // If we already have the maximum number of gift items, don't add more
        if ($existingGiftCount >= $discountQty) {
            $logger->debug(sprintf(
                'Already have %d gift items (max: %d) for rule %d in quote %s, skipping',
                $existingGiftCount,
                $discountQty,
                $ruleId,
                $quoteId
            ));
            return;
        }

        // Calculate how many more gift items we can add
        $remainingQty = $discountQty - $existingGiftCount;

        try {
            $logger->debug(sprintf(
                'Adding %d more gift items for rule %d to quote %s',
                $remainingQty,
                $ruleId,
                $quoteId
            ));

            $this->giftProductManager->addGiftProductsToQuote(
                $quote,
                $ruleId,
                $giftSkus,
                $remainingQty,
                $discountAmount
            );
        } catch (\Exception $e) {
            $logger->log(sprintf(
                'Error adding gift products for rule %d to quote %s: %s',
                $ruleId,
                $quoteId,
                $e->getMessage()
            ));
        }
    }
}
