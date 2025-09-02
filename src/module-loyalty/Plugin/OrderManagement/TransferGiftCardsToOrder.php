<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\OrderManagement;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\GiftCard\ApplicationService;
use Leat\Loyalty\Model\GiftCard\TransactionService;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin to transfer gift cards from quote to order after order placement
 */
class TransferGiftCardsToOrder
{
    /**
     * @param AppliedGiftCardRepositoryInterface $appliedGiftCardRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected AppliedGiftCardRepositoryInterface $appliedGiftCardRepository,
        protected TransactionService $giftcardTransactionService,
        protected Connector $leatConnector,
    ) {
    }

    /**
     * After placing an order, transfer any applied gift cards from the quote to the order
     *
     * @param OrderManagementInterface $subject
     * @param OrderInterface $result
     * @return OrderInterface
     */
    public function afterPlace(
        OrderManagementInterface $subject,
        OrderInterface $result
    ): OrderInterface {
        $logger = $this->leatConnector->getLogger(ApplicationService::LOGGER_PURPOSE);

        try {
            // Get the quote ID from the order
            $quoteId = $result->getQuoteId();
            if (!$quoteId) {
                $logger->log(
                    'Cannot transfer gift cards: No quote ID found in order',
                    false,
                    [
                        'order_id' => $result->getIncrementId()
                    ]
                );
                return $result;
            }

            // Get applied gift cards from quote
            $appliedGiftCards = $this->appliedGiftCardRepository->getByQuoteId((int)$quoteId);

            if (empty($appliedGiftCards)) {
                // No gift cards to transfer
                return $result;
            }

            // Transfer each gift card to the order
            foreach ($appliedGiftCards as $giftCard) {
                $giftCard->setQuoteId(null);
                $giftCard->setOrderId((int)$result->getEntityId());

                // Save the gift card associated with the order
                $this->appliedGiftCardRepository->save($giftCard);
            }

            $this->giftcardTransactionService->createGiftcardTransaction($result, $appliedGiftCards);

            $logger->log(
                'Successfully transferred gift cards to order',
                true,
                [
                    'order_id' => $result->getIncrementId(),
                    'quote_id' => $quoteId,
                    'count' => count($appliedGiftCards)
                ]
            );
        } catch (\Exception $e) {
            $logger->log(
                'Error transferring gift cards to order: ' . $e->getMessage(),
                false,
                [
                'order_id' => $result->getIncrementId(),
                'exception' => $e->getTraceAsString()
                ]
            );
        }

        return $result;
    }
}
