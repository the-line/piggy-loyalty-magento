<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Api\PrepaidTransactionServiceInterface;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\GiftCard\ApplicationService;
use Leat\Loyalty\Model\GiftCard\TransactionService;
use Leat\Loyalty\Model\Order\LeatBalanceRepository;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;

/**
 * Observer for refunding prepaid balance when credit memo is created
 */
class RefundGiftcardTransaction implements ObserverInterface
{
    /**
     * @param Config $config
     * @param PrepaidTransactionServiceInterface $prepaidTransactionService
     * @param OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository
     * @param Connector $leatConnector
     */
    public function __construct(
        protected AppliedGiftCardRepositoryInterface $appliedGiftCardRepository,
        protected TransactionService $giftcardTransactionService,
        protected readonly Connector $leatConnector
    ) {
    }

    /**
     * Process credit memo refund and create prepaid transaction refund
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $logger = $this->leatConnector->getLogger(ApplicationService::LOGGER_PURPOSE);

        try {
            /** @var CreditmemoInterface $creditmemo */
            $creditmemo = $observer->getData('creditmemo');

            if (!$creditmemo) {
                return;
            }

            $order = $creditmemo->getOrder();
            if (!$order) {
                return;
            }

            // Get giftcard amount from credit memo
            $refundAmount = (float) $creditmemo->getData('leat_loyalty_giftcard_refunded');

            // Skip if no giftcard amount to refund
            if ($refundAmount <= 0) {
                return;
            }

            $logger->log(
                'Refunding giftcard balance',
                true,
                [
                    'order_id' => $order->getIncrementId(),
                    'creditmemo_id' => $creditmemo->getIncrementId(),
                    'refund_amount' => $refundAmount
                ]
            );

            $result = $this->giftcardTransactionService->refundGiftcardTransaction(
                $order,
                $this->appliedGiftCardRepository->getByOrderId((int) $order->getId()),
                $refundAmount
            );

            if ($result) {
                $logger->log(
                    'Successfully refunded giftcard balance',
                    true,
                    [
                        'order_id' => $order->getIncrementId(),
                        'creditmemo_id' => $creditmemo->getIncrementId(),
                        'refund_amount' => $refundAmount
                    ]
                );

                // Check if the order should be not closed even after full refund because of prepaid balance
                $remainingBalanceToRefund = $this->appliedGiftCardRepository->getRemainingRefundableAmount($order);
                if ($remainingBalanceToRefund > 0) {
                    // Prevent order from being closed
                    $order->setForcedCanCreditmemo(true);
                }
            } else {
                $logger->log(
                    'Failed to refund giftcard balance',
                    false,
                    [
                        'order_id' => $order->getIncrementId(),
                        'creditmemo_id' => $creditmemo->getIncrementId(),
                        'refund_amount' => $refundAmount
                    ]
                );
            }
        } catch (\Exception $e) {
            $logger->log(
                'Error in RefundGiftcardTransaction observer: ' . $e->getMessage(),
                false,
                [
                    'exception' => $e->getTraceAsString(),
                    'order_id' => $order->getIncrementId() ?? null,
                    'creditmemo_id' => $creditmemo->getIncrementId() ?? null
                ]
            );
        }
    }
}
