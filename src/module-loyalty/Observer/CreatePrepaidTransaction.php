<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Api\PrepaidTransactionServiceInterface;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Logger;
use Leat\Loyalty\Model\Order\LeatBalanceRepository;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Observer to create prepaid transaction after order is placed
 */
class CreatePrepaidTransaction implements ObserverInterface
{
    private ?Logger $logger = null;

    /**
     * @param Config $config
     * @param PrepaidTransactionServiceInterface $prepaidTransactionService
     * @param OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderManagementInterface $orderManagement
     * @param Connector $leatConnector
     */
    public function __construct(
        private readonly Config                              $config,
        private readonly PrepaidTransactionServiceInterface  $prepaidTransactionService,
        private readonly OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository,
        private readonly OrderRepositoryInterface            $orderRepository,
        private readonly OrderManagementInterface            $orderManagement,
        private readonly Connector                           $leatConnector
    ) {
    }

    /**
     * Create prepaid transaction after order is placed
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->logger = $this->leatConnector->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);

        /** @var OrderInterface $order */
        $order = $observer->getData('order');

        if (!$order) {
            return;
        }

        try {
            // Check if feature is enabled
            if (!$this->config->isPrepaidBalanceEnabled((int) $order->getStoreId())) {
                return;
            }

            // Get balance amount from order
            $balanceAmount = $this->orderLeatBalanceRepository->getLeatBalanceAmount($order);

            // Skip if no balance is used
            if ($balanceAmount <= 0) {
                return;
            }

            // Create prepaid transaction
            $transactionUuid = $this->prepaidTransactionService->createPrepaidTransaction($order, $balanceAmount);

            if (!$transactionUuid) {
                // If transaction creation fails, cancel the order
                $this->cancelOrderWithReason($order, 'Failed to create prepaid transaction in Leat');
                return;
            }
        } catch (\Exception $e) {
            $this->logger->log(
                'Error in CreatePrepaidTransaction observer: ' . $e->getMessage(),
                context: [
                    'exception' => $e,
                    'order_id' => $order->getIncrementId() ?? null
                ]
            );

            // If an exception occurs, cancel the order
            $this->cancelOrderWithReason($order, 'Error processing prepaid balance transaction: ' . $e->getMessage());
        }
    }

    /**
     * Cancel order with specified reason
     *
     * @param OrderInterface $order
     * @param string $reason
     * @return void
     */
    private function cancelOrderWithReason(OrderInterface $order, string $reason): void
    {
        try {
            if ($order->canCancel()) {
                // Add comment first
                $order->addCommentToStatusHistory($reason, 'canceled');

                // Cancel the order
                $this->orderManagement->cancel((int)$order->getEntityId());
            } else {
                // Add comment even if we can't cancel
                $order->addCommentToStatusHistory(
                    'Cannot cancel order but prepaid transaction failed: ' . $reason,
                    $order->getStatus()
                );
                $this->orderRepository->save($order);
            }
        } catch (\Exception $e) {
            $this->logger->log(
                'Error canceling order: ' . $e->getMessage(),
                context: [
                    'exception' => $e,
                    'order_id' => $order->getIncrementId(),
                ]
            );
        }
    }
}
