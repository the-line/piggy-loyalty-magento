<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Api\PrepaidTransactionServiceInterface;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Order\LeatBalanceRepository;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;

/**
 * Observer for refunding prepaid balance when credit memo is created
 */
class RefundPrepaidTransaction implements ObserverInterface
{
    /**
     * @param Config $config
     * @param PrepaidTransactionServiceInterface $prepaidTransactionService
     * @param OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository
     * @param Connector $leatConnector
     */
    public function __construct(
        private readonly Config                              $config,
        private readonly PrepaidTransactionServiceInterface  $prepaidTransactionService,
        private readonly OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository,
        private readonly Connector                           $leatConnector
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
        $logger = $this->leatConnector->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);

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

            // Check if feature is enabled
            if (!$this->config->isPrepaidBalanceEnabled((int)$order->getStoreId())) {
                return;
            }

            // Get balance amount from credit memo
            $refundAmount = 0.0;
            if ($creditmemo->getData('leat_loyalty_balance_amount') !== null) {
                $refundAmount = (float)$creditmemo->getData('leat_loyalty_balance_amount');
            } elseif ($creditmemo->getData('leat_loyalty_balance_refund_amount') !== null) {
                $refundAmount = (float)$creditmemo->getData('leat_loyalty_balance_refund_amount');
            }

            // Skip if no balance to refund
            if ($refundAmount <= 0) {
                return;
            }

            $logger->log(
                'Refunding prepaid balance',
                true,
                [
                    'order_id' => $order->getIncrementId(),
                    'creditmemo_id' => $creditmemo->getIncrementId(),
                    'refund_amount' => $refundAmount
                ]
            );

            $result = $this->prepaidTransactionService->refundPrepaidTransaction($order, $refundAmount);

            if ($result) {
                $logger->log(
                    'Successfully refunded prepaid balance',
                    true,
                    [
                        'order_id' => $order->getIncrementId(),
                        'creditmemo_id' => $creditmemo->getIncrementId(),
                        'refund_amount' => $refundAmount
                    ]
                );

                // Track refunded amount by saving it to the credit memo
                $creditmemo->setData('leat_loyalty_balance_refunded', $refundAmount);
                // Also add to order's total refunded amount
                $this->orderLeatBalanceRepository->addToLeatRefundedAmount($order, $refundAmount);

                // Check if the order should be not closed even after full refund because of prepaid balance
                $remainingBalanceToRefund = $this->orderLeatBalanceRepository->getRemainingRefundableAmount($order);
                if ($remainingBalanceToRefund > 0) {
                    // Prevent order from being closed
                    $order->setForcedCanCreditmemo(true);
                }
            } else {
                $logger->log(
                    'Failed to refund prepaid balance',
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
                'Error in RefundPrepaidTransaction observer: ' . $e->getMessage(),
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
