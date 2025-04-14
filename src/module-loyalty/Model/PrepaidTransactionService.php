<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Api\PrepaidTransactionServiceInterface;
use Leat\Loyalty\Model\Data\PrepaidTransactionContext;
use Leat\Loyalty\Model\Order\LeatBalanceRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Service for creating prepaid transactions in Leat
 */
class PrepaidTransactionService implements PrepaidTransactionServiceInterface
{
    private ?Logger $logger = null;

    /**
     * @param Connector $connector
     * @param Config $config
     * @param CustomerContactLink $customerContactLink
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository
     */
    public function __construct(
        private readonly Connector                           $connector,
        private readonly Config                              $config,
        private readonly CustomerContactLink                 $customerContactLink,
        private readonly OrderRepositoryInterface            $orderRepository,
        private readonly OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createPrepaidTransaction(OrderInterface $order, float $amount): ?string
    {
        $this->logger = $this->connector->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);

        try {
            // Validate transaction
            $context = $this->validatePrepaidTransaction($order, $amount);
            if (!$context) {
                return null;
            }

            // Log transaction creation
            $this->logger->log(
                'Creating prepaid transaction',
                true,
                [
                    'order_id' => $order->getIncrementId(),
                    'amount' => $amount,
                    'contact_uuid' => $context->contactUuid
                ]
            );

            // Make API call to create prepaid transaction
            $client = $this->connector->getConnection((int) $order->getStoreId());
            $transaction = $client->prepaidTransactions->create(
                $context->contactUuid,
                ((int) ($amount * 100)) * -1, // Negative amount for deduction, converted into cents
                $context->shopUuid,
            );

            // Save transaction UUID to order
            $transactionUuid = $transaction->getUuid() ?? null;
            if ($transactionUuid) {
                $this->orderLeatBalanceRepository->setPrepaidTransactionUuid($order, $transactionUuid);
                $this->orderRepository->save($order);

                $this->logger->log(
                    'Successfully created prepaid transaction',
                    true,
                    [
                        'order_id' => $order->getIncrementId(),
                        'transaction_uuid' => $transactionUuid,
                        'amount' => $amount
                    ]
                );
            } else {
                $this->logger->log(
                    'Prepaid transaction created but no UUID returned',
                    false,
                    [
                        'order_id' => $order->getIncrementId()
                    ]
                );
            }

            return $transactionUuid;
        } catch (\Exception $e) {
            $this->handleTransactionException($e, $order, 'creating');
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function refundPrepaidTransaction(OrderInterface $order, float $amount): bool
    {
        $this->logger = $this->connector->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);

        try {
            // Check for existing transaction and validate
            $transactionUuid = $this->orderLeatBalanceRepository->getPrepaidTransactionUuid($order);
            if (!$transactionUuid) {
                $this->logger->log(
                    'No prepaid transaction exists for order',
                    true,
                    [
                        'order_id' => $order->getIncrementId()
                    ]
                );
                return false;
            }

            // Validate transaction
            $context = $this->validatePrepaidTransaction($order, $amount, true);
            if (!$context) {
                return false;
            }

            // Log refund transaction
            $this->logger->log(
                'Refunding prepaid transaction',
                true,
                [
                    'order_id' => $order->getIncrementId(),
                    'amount' => $amount,
                    'transaction_uuid' => $transactionUuid,
                    'contact_uuid' => $context->contactUuid
                ]
            );

            // Create refund transaction (positive amount)
            $refundTransaction = $this->connector->getConnection((int) $order->getStoreId())->prepaidTransactions->create(
                $context->contactUuid,
                (int) ($amount * 100), // Positive amount for refund, converted to cents
                $context->shopUuid,
            );

            if ($refundTransaction && $refundTransaction->getUuid()) {
                $this->logger->log(
                    'Successfully refunded prepaid transaction',
                    true,
                    [
                        'order_id' => $order->getIncrementId(),
                        'original_transaction_uuid' => $transactionUuid,
                        'refund_transaction_uuid' => $refundTransaction->getUuid(),
                        'amount' => $amount
                    ]
                );
                return true;
            } else {
                $this->logger->log(
                    'Refund transaction created but no UUID returned',
                    false,
                    [
                        'order_id' => $order->getIncrementId()
                    ]
                );
                return false;
            }
        } catch (\Exception $e) {
            $this->handleTransactionException($e, $order, 'refunding');
            return false;
        }
    }

    /**
     * Handle transaction exceptions with consistent logging
     *
     * @param \Exception $e
     * @param OrderInterface $order
     * @param string $operation
     */
    private function handleTransactionException(\Exception $e, OrderInterface $order, string $operation): void
    {
        $this->logger->log(
            "Error $operation prepaid transaction",
            false,
            [
                'order_id' => $order->getIncrementId(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]
        );
    }

    /**
     * Check if prepaid balance feature is enabled and validate transaction
     *
     * @param OrderInterface $order
     * @param float $amount
     * @param bool $isRefund
     * @return PrepaidTransactionContext|null
     * @throws LocalizedException
     */
    private function validatePrepaidTransaction(
        OrderInterface $order,
        float $amount,
        bool $isRefund = false
    ): ?PrepaidTransactionContext {
        // Skip if feature is not enabled
        if (!$this->config->isPrepaidBalanceEnabled()) {
            $this->logger->debug('Prepaid balance feature is not enabled, skipping transaction ' . ($isRefund ? 'refund' : 'creation'));
            return null;
        }

        // Skip if no amount is to be processed
        if ($amount <= 0) {
            $this->logger->log(
                'No prepaid balance to ' . ($isRefund ? 'refund' : 'deduct') . ', skipping transaction',
                true,
                [
                    'order_id' => $order->getIncrementId(),
                    'amount' => $amount
                ]
            );
            return null;
        }

        // Check if customer is logged in
        $customerId = $order->getCustomerId();
        if (!$customerId) {
            $this->logger->log(
                'Cannot ' . ($isRefund ? 'refund' : 'create') . ' prepaid transaction for guest order',
                true,
                [
                    'order_id' => $order->getIncrementId()
                ]
            );
            return null;
        }

        // Skip if checking for existing transaction on create
        $transactionUuid = null;
        if (!$isRefund) {
            $transactionUuid = $this->orderLeatBalanceRepository->getPrepaidTransactionUuid($order);
            if ($transactionUuid) {
                $this->logger->log(
                    'Prepaid transaction already exists for order',
                    false,
                    [
                        'order_id' => $order->getIncrementId(),
                        'transaction_uuid' => $transactionUuid
                    ]
                );
                return new PrepaidTransactionContext($transactionUuid);
            }
        }

        // Get customer contact UUID
        $contactUuid = $this->customerContactLink->getContactUuid((int) $customerId);
        if (!$contactUuid) {
            $this->logger->log(
                'Cannot find contact UUID for customer',
                false,
                [
                    'customer_id' => $customerId,
                    'order_id' => $order->getIncrementId()
                ]
            );
            return null;
        }

        // Get shop UUID
        $shopUuid = $this->config->getShopUuid((int) $order->getStoreId());
        if (!$shopUuid) {
            $this->logger->log('Shop UUID is not configured');
            return null;
        }

        return new PrepaidTransactionContext(
            $transactionUuid,
            $contactUuid,
            $shopUuid,
            $amount
        );
    }
}
