<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\Quote;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\QuoteItem\ExtensionAttributesRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartItemExtensionFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

class LoadItemExtensionAttributesPlugin
{
    /**
     * @var array Cached processed item IDs to avoid reloading
     */
    private $processedItems = [];

    /**
     * @param CartItemExtensionFactory $extensionFactory
     * @param ExtensionAttributesRepository $extensionRepository
     * @param Connector $leatConnector
     */
    public function __construct(
        private CartItemExtensionFactory $extensionFactory,
        private ExtensionAttributesRepository $extensionRepository,
        private Connector $leatConnector
    ) {
    }

    /**
     * Load extension attributes for all items after getAllItems is called
     *
     * @param Quote $subject
     * @param array $result
     * @return array
     */
    public function afterGetAllItems(Quote $subject, array $result): array
    {
        foreach ($result as $item) {
            $this->loadExtensionAttributes($item);
        }
        return $result;
    }

    /**
     * Load extension attributes for a single item
     *
     * @param Item $item
     * @return void
     */
    private function loadExtensionAttributes(Item $item): void
    {
        $itemId = $item->getItemId();
        if (!$itemId || isset($this->processedItems[$itemId])) {
            return;
        }

        // Mark this item as processed
        $this->processedItems[$itemId] = true;

        // Get existing extension attributes or create new ones
        $extensionAttributes = $item->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->extensionFactory->create();
            $item->setExtensionAttributes($extensionAttributes);
        }

        $logger = $this->leatConnector->getLogger('reward');

        try {
            // Try to load extension attributes from database
            $attrs = $this->extensionRepository->getByItemId((int)$itemId);

            // Set the loaded values on the extension attributes
            $extensionAttributes->setIsGift($attrs->getIsGift());
            $extensionAttributes->setGiftRuleId($attrs->getGiftRuleId());
            $extensionAttributes->setOriginalProductSku($attrs->getOriginalProductSku());

            // Log successful load
            $logger->debug(sprintf(
                'Loaded extension attributes for item %d: isGift=%d, ruleId=%d, sku=%s',
                $itemId,
                $attrs->getIsGift() ? 1 : 0,
                $attrs->getGiftRuleId() ?: 0,
                $attrs->getOriginalProductSku() ?: 'null'
            ));
        } catch (NoSuchEntityException $e) {
            // No extension attributes exist for this item, set defaults
            $extensionAttributes->setIsGift(false);
            $extensionAttributes->setGiftRuleId(0);
            $extensionAttributes->setOriginalProductSku(null);
        } catch (\Exception $e) {
            // Log errors but continue
            $logger->log(sprintf(
                'Error loading extension attributes for item %d: %s',
                $itemId,
                $e->getMessage()
            ));
        }
    }
}
