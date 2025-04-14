<?php

declare(strict_types=1);

namespace Leat\Loyalty\Observer;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Api\BalanceValidatorInterface;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Order\LeatBalanceRepository;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Observer to transfer leat balance amount from quote to order
 */
class AddLeatBalanceToOrder implements ObserverInterface
{
    /**
     * @param Config $config
     * @param OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository
     * @param BalanceValidatorInterface $balanceValidator
     * @param Connector $leatConnector
     */
    public function __construct(
        private readonly Config                              $config,
        private readonly OrderLeatBalanceRepositoryInterface $orderLeatBalanceRepository,
        private readonly BalanceValidatorInterface           $balanceValidator,
        private readonly Connector $leatConnector,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        try {
            // Check if feature is enabled
            if (!$this->config->isPrepaidBalanceEnabled()) {
                return;
            }

            /** @var CartInterface $quote */
            $quote = $observer->getData('quote');
            /** @var OrderInterface $order */
            $order = $observer->getData('order');

            if (!$quote || !$order) {
                return;
            }

            // First, check the quote data directly
            $balanceAmount = 0.0;

            // Try to get balance from quote data attribute first
            if ($quote->getData('leat_loyalty_balance_amount') !== null) {
                $balanceAmount = (float)$quote->getData('leat_loyalty_balance_amount');
            }

            // If not found, try extension attributes
            if ($balanceAmount <= 0) {
                $extensionAttributes = $quote->getExtensionAttributes();
                if ($extensionAttributes && $extensionAttributes->getLeatLoyaltyBalanceAmount() !== null) {
                    $balanceAmount = (float)$extensionAttributes->getLeatLoyaltyBalanceAmount();
                }
            }

            if ($balanceAmount <= 0) {
                return;
            }

            $logger = $this->leatConnector->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);


            // Final validation before applying to order
            if (!$this->balanceValidator->isValid($quote, $balanceAmount)) {
                $logger->log(
                    'Leat balance validation failed during order placement',
                    context: [
                        'quote_id' => $quote->getId(),
                        'order_id' => $order->getEntityId(),
                        'balance_amount' => $balanceAmount,
                        'error' => $this->balanceValidator->getErrorMessage()
                    ]
                );
                throw new LocalizedException(__($this->balanceValidator->getErrorMessage()));
            }

            // Set balance amount on order extension attributes
            $this->orderLeatBalanceRepository->setLeatBalanceAmount($order, $balanceAmount);

            // Set balance amount on order data (for direct database access)
            $order->setData('leat_loyalty_balance_amount', $balanceAmount);

            // Make sure the extension attribute gets persisted when order is saved
            $orderExtension = $order->getExtensionAttributes();
            if ($orderExtension) {
                $orderExtension->setLeatLoyaltyBalanceAmount($balanceAmount);
                $order->setExtensionAttributes($orderExtension);
            }

            $logger->log(
                'Leat balance applied to order',
                true,
                [
                    'quote_id' => $quote->getId(),
                    'order_id' => $order->getEntityId(),
                    'balance_amount' => $balanceAmount
                ]
            );
        } catch (\Exception $e) {
            // Log error but don't stop order placement
            $logger->log(
                'Error transferring leat balance to order: ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString(),
            );
        }
    }
}
