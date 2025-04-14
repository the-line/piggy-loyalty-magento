<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\Quote\Item;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Logger;
use Leat\Loyalty\Model\QuoteItem\ExtensionAttributesFactory;
use Leat\Loyalty\Model\QuoteItem\ExtensionAttributesRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Item;

class SaveExtensionAttributesPlugin
{
    /**
     * @var Logger
     */
    private Logger $logger;

    /**
     * @var array Track items that need extension attributes saved
     */
    private array $pendingItems = [];

    /**
     * @param ExtensionAttributesFactory $extensionAttributesFactory
     * @param ExtensionAttributesRepository $extensionAttributesRepository
     * @param Connector $leatConnector
     */
    public function __construct(
        protected ExtensionAttributesFactory $extensionAttributesFactory,
        protected ExtensionAttributesRepository $extensionAttributesRepository,
        protected Connector $leatConnector,
    ) {
        $this->logger = $this->leatConnector->getLogger('reward');
    }

    /**
     * Track items with extension attributes but no ID yet
     *
     * @param Item $subject
     * @param callable $proceed
     * @param mixed $key
     * @param mixed $value
     * @return Item
     */
    public function aroundSetData(Item $subject, callable $proceed, $key, $value = null)
    {
        // Execute the original method
        $result = $proceed($key, $value);

        // Check for both conditions:
        // 1. If setting extension attributes, mark the item for future saving
        if ($key === 'extension_attributes' && $value !== null) {
            $this->pendingItems[spl_object_hash($subject)] = true;
            $this->logger->debug('Marking item for future extension attributes save due to extension_attributes update');
        }

        // 2. If setting item_id and the item is a gift (check extension attributes)
        if ($key === 'item_id' && !empty($value)) {
            $extensionAttributes = $subject->getExtensionAttributes();
            if ($extensionAttributes && $extensionAttributes->getIsGift()) {
                $this->logger->debug(sprintf(
                    'Item received ID %d, checking if extension attributes need to be saved',
                    (int)$value
                ));
                $this->saveExtensionAttributes($subject, (int)$value);
            }
        }

        return $result;
    }

    /**
     * After the save operation, ensure extension attributes are saved if needed
     *
     * @param Item $subject
     * @param Item $result
     * @return Item
     */
    public function afterSave(Item $subject, Item $result)
    {
        $itemId = $subject->getItemId();

        // If this is a saved item with an ID
        if ($itemId) {
            $extensionAttributes = $subject->getExtensionAttributes();

            // Check if it's a gift item
            if ($extensionAttributes && $extensionAttributes->getIsGift()) {
                $this->saveExtensionAttributes($subject, (int)$itemId);
                $this->logger->debug(sprintf(
                    'Saved extension attributes for gift item %d after save',
                    (int)$itemId
                ));
            }

            // Also check pending items for cleanup
            if (isset($this->pendingItems[spl_object_hash($subject)])) {
                // Remove from pending items
                unset($this->pendingItems[spl_object_hash($subject)]);
            }
        }

        return $result;
    }

    /**
     * After the item is added to the quote, ensure extension attributes are applied
     *
     * @param Item $subject
     * @param Item $result
     * @return Item
     */
    public function afterSetQuote(Item $subject, Item $result)
    {
        $itemId = $subject->getItemId();
        $extensionAttributes = $subject->getExtensionAttributes();

        // If this is a gift item with an ID
        if ($itemId && $extensionAttributes && $extensionAttributes->getIsGift()) {
            $this->logger->debug(sprintf(
                'Item %d added to quote, ensuring extension attributes are saved',
                (int)$itemId
            ));
            $this->saveExtensionAttributes($subject, (int)$itemId);
        }

        return $result;
    }

    /**
     * Save extension attributes for item
     *
     * @param Item $item
     * @param int $itemId
     * @return void
     */
    private function saveExtensionAttributes(Item $item, int $itemId): void
    {
        $extensionAttributes = $item->getExtensionAttributes();
        if (!$extensionAttributes) {
            return;
        }

        $isGift = $extensionAttributes->getIsGift();

        // We only care about gift items
        if ($isGift === null || $isGift === false) {
            return;
        }

        $giftRuleId = $extensionAttributes->getGiftRuleId();
        $originalSku = $extensionAttributes->getOriginalProductSku();

        if ($giftRuleId === null) {
            $this->logger->debug(sprintf(
                'Item %d has isGift=true but no giftRuleId, skipping save',
                $itemId
            ));
            return;
        }

        // Check if item is being deleted - quantity of 0 means it's going to be removed
        if ($item->getQty() <= 0 || $item->getIsDeleted() || $item->isDeleted()) {
            $this->logger->debug(sprintf(
                'Item %d is scheduled for deletion (qty: %f, deleted: %d), skipping extension attributes save',
                $itemId,
                $item->getQty(),
                $item->getIsDeleted() ? 1 : 0
            ));
            return;
        }

        try {
            $this->logger->debug(sprintf(
                'Saving extension attributes for item %d (SKU: %s, gift=%d, rule=%d, originalSku=%s)',
                $itemId,
                $item->getSku(),
                $isGift ? 1 : 0,
                $giftRuleId,
                $originalSku ?: 'null'
            ));

            try {
                // Try to get existing record
                $attrs = $this->extensionAttributesRepository->getByItemId($itemId);
            } catch (NoSuchEntityException $e) {
                // Create a new record if it doesn't exist
                $attrs = $this->extensionAttributesFactory->create();
                $attrs->setItemId($itemId);
            }

            // Set values on the DB entity
            $attrs->setIsGift((bool)$isGift);
            $attrs->setGiftRuleId($giftRuleId);
            $attrs->setOriginalProductSku($originalSku);

            // Save to database
            $this->extensionAttributesRepository->save($attrs);

            $this->logger->debug(sprintf(
                'Successfully saved extension attributes for item %d',
                $itemId
            ));
        } catch (\Exception $e) {
            $this->logger->log(sprintf(
                'Error saving extension attributes for item %d: %s',
                $itemId,
                $e->getMessage()
            ));
        }
    }
}
