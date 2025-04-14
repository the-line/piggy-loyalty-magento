<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\SalesRule;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\QuoteItem\ExtensionAttributesRepository;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartItemExtensionFactory;
use Magento\Quote\Model\Quote;

class LoyaltyTransactionRelatedGiftRemover
{
    /**
     * @param ProductRepository $productRepository
     * @param CartItemExtensionFactory $extensionFactory
     * @param ExtensionAttributesRepository $extensionAttributesRepository
     * @param Connector $leatConnector
     */
    public function __construct(
        private ProductRepository $productRepository,
        private CartItemExtensionFactory $extensionFactory,
        private ExtensionAttributesRepository $extensionAttributesRepository,
        private Connector $leatConnector
    ) {
    }

    /**
     * @param Quote $quote
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(Quote $quote)
    {
        $ruleId = $quote->getLoyaltyTransactionRewardRuleId();
        $relatedItemSkus = $this->removeGiftsFromQuote($quote, $ruleId);
        $this->removeRuleFromRelatedItems($quote, $relatedItemSkus, $ruleId);
    }

    /**
     * @param Quote $quote
     * @param $couponRuleId
     * @return array
     */
    protected function removeGiftsFromQuote(Quote $quote, $couponRuleId)
    {
        $logger = $this->leatConnector->getLogger('reward');
        $parentItemSkus = [];

        $logger->debug(sprintf(
            'Removing gifts for coupon rule ID %s from quote %s',
            $couponRuleId,
            $quote->getId()
        ));

        // Extension attributes are now loaded automatically via plugin
        foreach ($quote->getAllItems() as $item) {
            $itemId = $item->getItemId();
            if (!$itemId) {
                continue;
            }

            $extensionAttributes = $item->getExtensionAttributes();

            // Check if this is a gift item for the specific coupon rule
            if ($extensionAttributes &&
                $extensionAttributes->getIsGift() &&
                $extensionAttributes->getGiftRuleId() == $couponRuleId) {
                $originalSku = $extensionAttributes->getOriginalProductSku();
                if ($originalSku) {
                    $parentItemSkus[] = $originalSku;
                }

                // Log removal of gift
                $logger->debug(sprintf(
                    'Removing gift item %d (SKU: %s) for rule ID %s',
                    $itemId,
                    $item->getSku(),
                    $couponRuleId
                ));

                // Remove the item from the quote
                $quote->removeItem($itemId);

                // Delete the extension attributes record
                try {
                    $this->extensionAttributesRepository->deleteByItemId((int)$itemId);
                } catch (\Exception $e) {
                    $logger->log(sprintf(
                        'Error deleting extension attributes for item %d: %s',
                        $itemId,
                        $e->getMessage()
                    ));
                }
            }
        }

        return $parentItemSkus;
    }

    /**
     * @param Quote $quote
     * @param array $relatedItemSkus
     * @param $couponRule
     * @return void
     * @throws NoSuchEntityException
     */
    protected function removeRuleFromRelatedItems(Quote $quote, array $relatedItemSkus, $couponRule)
    {
        foreach ($relatedItemSkus as $itemSku) {
            $product = $this->productRepository->get($itemSku);
            $quoteItem = $quote->getItemByProduct($product);

            if ($quoteItem === false) {
                continue;
            }

            $appliedRules = $this->removeRuleIdFromString($couponRule, $quote->getAppliedRuleIds());
            $quoteItem->setAppliedRuleIds($appliedRules);
        }
    }

    /**
     * @param $ruleId
     * @param $appliedRules
     * @return string
     */
    protected function removeRuleIdFromString($ruleId, $appliedRules)
    {
        if ($appliedRules === null) {
            return '';
        }

        $appliedRules = explode(',', $appliedRules);
        unset($appliedRules[array_search($ruleId, $appliedRules)]);

        return implode(',', $appliedRules);
    }
}
