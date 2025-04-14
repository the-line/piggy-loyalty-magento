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
use Magento\Sales\Api\Data\OrderInterface;

class CancelPrepaidTransaction implements ObserverInterface
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
        private readonly Connector $leatConnector
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $logger = $this->leatConnector->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);
        try {
            /** @var OrderInterface $order */
            $order = $observer->getData('order');

            if (!$order) {
                return;
            }

            // Check if feature is enabled
            if (!$this->config->isPrepaidBalanceEnabled((int) $order->getStoreId())) {
                return;
            }

            // Get balance amount from order
            $balanceAmount = $this->orderLeatBalanceRepository->getLeatBalanceAmount($order);
            $transactionUuid = $this->orderLeatBalanceRepository->getPrepaidTransactionUuid($order);

            // Skip if no balance is used or no transaction present
            if ($balanceAmount <= 0 || !$transactionUuid) {
                return;
            }

            $logger->log(
                'cancelling prepaid transaction for order',
                true,
                [
                    'order_id' => $order->getIncrementId(),
                    'balance_amount' => $balanceAmount
                ]
            );

            $this->prepaidTransactionService->refundPrepaidTransaction($order, $balanceAmount);
        } catch (\Exception $e) {
            $logger->log(
                'Error in CancelPrepaidTransaction observer: ' . $e->getMessage(),
                context: [
                    'exception' => $e->getTraceAsString(),
                    'order_id' => $order->getIncrementId() ?? null
                ]
            );
        }
    }
}
