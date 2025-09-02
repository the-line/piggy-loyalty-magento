<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Psr\Log\LoggerInterface;

class AddGiftCardsToOrder implements ObserverInterface
{
    /**
     * @var AppliedGiftCardRepositoryInterface
     */
    private $appliedGiftCardRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param AppliedGiftCardRepositoryInterface $appliedGiftCardRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        AppliedGiftCardRepositoryInterface $appliedGiftCardRepository,
        LoggerInterface $logger
    ) {
        $this->appliedGiftCardRepository = $appliedGiftCardRepository;
        $this->logger = $logger;
    }

    /**
     * Transfer applied gift cards from quote to order
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();

        // Skip if no quote or order
        if (!$quote || !$order) {
            return;
        }

        try {
            // Get applied gift cards from quote
            $appliedGiftCards = $this->appliedGiftCardRepository->getByQuoteId((int)$quote->getId());

            // Transfer each gift card to the order
            foreach ($appliedGiftCards as $giftCard) {
                // Clone the gift card and associate with order
                $orderGiftCard = clone $giftCard;
                $orderGiftCard->setId(null); // Reset ID for new record
                $orderGiftCard->setQuoteId(null);
                $orderGiftCard->setOrderId((int)$order->getId());

                // Save the gift card associated with the order
                $this->appliedGiftCardRepository->save($orderGiftCard);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error transferring gift cards to order: ' . $e->getMessage(), [
                'quote_id' => $quote->getId(),
                'order_id' => $order->getId(),
                'exception' => $e
            ]);
        }
    }
}
