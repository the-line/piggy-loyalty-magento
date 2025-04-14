<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Logger;
use Leat\Loyalty\Model\QuoteItem\ExtensionAttributesFactory;
use Leat\Loyalty\Model\QuoteItem\ExtensionAttributesRepository;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;

class SaveQuoteItemExtensionAttributes implements ObserverInterface
{
    private ?Logger $logger = null;

    /**
     * @param ExtensionAttributesFactory $extensionAttributesFactory
     * @param ExtensionAttributesRepository $extensionAttributesRepository
     * @param Connector $leatConnector
     */
    public function __construct(
        private readonly ExtensionAttributesFactory $extensionAttributesFactory,
        private readonly ExtensionAttributesRepository $extensionAttributesRepository,
        private readonly Connector $leatConnector,
    ) {
    }

    /**
     * Save extension attributes for all quote items
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->logger = $this->leatConnector->getLogger('reward');

        /** @var Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        if (!$quote) {
            return;
        }

        foreach ($quote->getAllItems() as $item) {
            $itemId = $item->getItemId();
            if (!$itemId) {
                continue;
            }

            $extensionAttributes = $item->getExtensionAttributes();
            if (!$extensionAttributes) {
                continue;
            }

            $isGift = $extensionAttributes->getIsGift();
            // Only process gift items
            if (!$isGift) {
                continue;
            }

            try {
                $giftRuleId = $extensionAttributes->getGiftRuleId();
                $originalSku = $extensionAttributes->getOriginalProductSku();

                // Save to database
                $this->saveExtensionAttributes(
                    (int)$itemId,
                    (bool)$isGift,
                    $giftRuleId,
                    $originalSku
                );
            } catch (\Exception $e) {
                $this->logger->log(sprintf(
                    'Failed to save extension attributes for item %d: %s',
                    $itemId,
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Save extension attributes to the database
     *
     * @param int $itemId
     * @param bool $isGift
     * @param int|null $giftRuleId
     * @param string|null $originalSku
     * @return void
     * @throws \Exception
     */
    private function saveExtensionAttributes(
        int $itemId,
        bool $isGift,
        ?int $giftRuleId,
        ?string $originalSku
    ): void {
        try {
            try {
                $extensionAttributes = $this->extensionAttributesRepository->getByItemId($itemId);
            } catch (NoSuchEntityException $e) {
                $extensionAttributes = $this->extensionAttributesFactory->create();
                $extensionAttributes->setItemId($itemId);
            }

            $extensionAttributes->setIsGift($isGift);
            $extensionAttributes->setGiftRuleId($giftRuleId);
            $extensionAttributes->setOriginalProductSku($originalSku);

            $this->extensionAttributesRepository->save($extensionAttributes);

            $this->logger->debug(sprintf(
                'Saved extension attributes for item %d after quote save: isGift=%d, giftRuleId=%s, originalSku=%s',
                $itemId,
                $isGift ? 1 : 0,
                $giftRuleId ?? 'null',
                $originalSku ?? 'null'
            ));
        } catch (\Exception $e) {
            throw new \Exception(sprintf('Failed to save extension attributes: %s', $e->getMessage()), 0, $e);
        }
    }
}
