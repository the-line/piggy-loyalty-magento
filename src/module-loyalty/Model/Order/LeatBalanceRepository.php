<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model\Order;

use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Model\Connector;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Repository for Order Leat Balance
 */
class LeatBalanceRepository implements OrderLeatBalanceRepositoryInterface
{
    public const string LOGGER_PURPOSE = 'prepaid_balance';

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderExtensionFactory $orderExtensionFactory
     * @param Connector $leatConnector
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderExtensionFactory $orderExtensionFactory,
        private readonly Connector $leatConnector
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getLeatBalanceAmount(OrderInterface $order): float
    {
        // First try from direct data
        if ($order->getData('leat_loyalty_balance_amount') !== null) {
            return (float)$order->getData('leat_loyalty_balance_amount');
        }

        // Then try from extension attributes
        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes && $extensionAttributes->getLeatLoyaltyBalanceAmount() !== null) {
            return (float)$extensionAttributes->getLeatLoyaltyBalanceAmount();
        }

        return 0.0;
    }

    /**
     * @inheritDoc
     */
    public function setLeatBalanceAmount(OrderInterface $order, float $amount): void
    {
        // Set extension attributes
        $extensionAttributes = $order->getExtensionAttributes();
        if (!$extensionAttributes) {
            $extensionAttributes = $this->orderExtensionFactory->create();
            $order->setExtensionAttributes($extensionAttributes);
        }

        $extensionAttributes->setLeatLoyaltyBalanceAmount($amount);

        // Also set on the order data directly to ensure database persistence
        $order->setData('leat_loyalty_balance_amount', $amount);
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(int $orderId): float
    {
        $logger = $this->leatConnector->getLogger(self::LOGGER_PURPOSE);

        try {
            $order = $this->orderRepository->get($orderId);
            return $this->getLeatBalanceAmount($order);
        } catch (NoSuchEntityException $e) {
            $logger->log(
                'Order not found',
                context: ['orderId' => $orderId, 'exception' => $e->getMessage() . PHP_EOL . $e->getTraceAsString()]
            );
            return 0.0;
        } catch (\Exception $e) {
            $logger->log(
                'Error getting leat balance',
                context: ['orderId' => $orderId, 'exception' => $e->getMessage() . PHP_EOL . $e->getTraceAsString()]
            );
            return 0.0;
        }
    }

    /**
     * @inheritDoc
     */
    public function getPrepaidTransactionUuid(OrderInterface $order): ?string
    {
        // First try to get from order data directly
        $uuid = $order->getData('leat_loyalty_prepaid_transaction_uuid');
        if ($uuid) {
            return $uuid;
        }

        // Then try extension attributes if available
        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes && method_exists($extensionAttributes, 'getLeatPrepaidTransactionUuid')) {
            return $extensionAttributes->getLeatPrepaidTransactionUuid();
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function setPrepaidTransactionUuid(OrderInterface $order, string $uuid): void
    {
        // Set on order data directly for database persistence
        $order->setData('leat_loyalty_prepaid_transaction_uuid', $uuid);

        // Also set in extension attributes when implemented
        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes && method_exists($extensionAttributes, 'setLeatPrepaidTransactionUuid')) {
            $extensionAttributes->setLeatPrepaidTransactionUuid($uuid);
            $order->setExtensionAttributes($extensionAttributes);
        }

        $logger = $this->leatConnector->getLogger(self::LOGGER_PURPOSE);
        $logger->log(
            'Set Leat prepaid transaction UUID on order',
            true,
            [
                'order_id' => $order->getEntityId(),
                'transaction_uuid' => $uuid
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getLeatRefundedAmount(OrderInterface $order): float
    {
        // First try from direct data
        if ($order->getData('leat_loyalty_balance_refunded') !== null) {
            return (float)$order->getData('leat_loyalty_balance_refunded');
        }

        // Then try from extension attributes
        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes && method_exists($extensionAttributes, 'getLeatBalanceRefunded')) {
            return (float)$extensionAttributes->getLeatBalanceRefunded();
        }

        return 0.0;
    }

    /**
     * @inheritDoc
     */
    public function addToLeatRefundedAmount(OrderInterface $order, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $currentAmount = $this->getLeatRefundedAmount($order);
        $newAmount = $currentAmount + $amount;

        // Set on order data directly for database persistence
        $order->setData('leat_loyalty_balance_refunded', $newAmount);

        // Also set in extension attributes when implemented
        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes && method_exists($extensionAttributes, 'setLeatBalanceRefunded')) {
            $extensionAttributes->setLeatBalanceRefunded($newAmount);
            $order->setExtensionAttributes($extensionAttributes);
        }

        $order->addCommentToStatusHistory('leat prepaid balance refunded: ' . $amount);

        $logger = $this->leatConnector->getLogger(self::LOGGER_PURPOSE);

        $logger->log(
            'Added to Leat refunded balance on order',
            true,
            [
                'order_id' => $order->getEntityId(),
                'amount' => $amount,
                'new_total' => $newAmount
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function getRemainingRefundableAmount(OrderInterface $order): float
    {
        $originalAmount = $this->getLeatBalanceAmount($order);
        $refundedAmount = $this->getLeatRefundedAmount($order);

        $remaining = $originalAmount - $refundedAmount;
        return max(0.0, $remaining);
    }
}
