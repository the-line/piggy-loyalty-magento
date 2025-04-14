<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Plugin\Sales;

use Leat\Loyalty\Model\Config;
use Leat\LoyaltyFrontend\Model\ErrorHandler\OrderPlacement;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;

/**
 * Plugin for OrderManagementInterface to handle errors with prepaid balance
 */
class OrderManagement
{
    /**
     * @param Config $config
     * @param CartRepositoryInterface $quoteRepository
     * @param OrderPlacement $errorHandler
     */
    public function __construct(
        private readonly Config $config,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly OrderPlacement $errorHandler
    ) {
    }

    /**
     * Handle errors during order placement if prepaid balance is used
     *
     * @param OrderManagementInterface $subject
     * @param callable $proceed
     * @param int $cartId
     * @return mixed
     * @throws \Exception
     */
    /**
     * @param OrderManagementInterface $subject
     * @param callable $proceed
     * @param OrderInterface $order
     * @return OrderInterface
     */
    public function aroundPlace(OrderManagementInterface $subject, callable $proceed, OrderInterface $order): OrderInterface
    {
        // Skip if feature is not enabled
        if (!$this->config->isPrepaidBalanceEnabled()) {
            return $proceed($order);
        }

        try {
            // Get quote
            $quote = $this->quoteRepository->get($order->getQuoteId());
            $extensionAttributes = $quote->getExtensionAttributes();

            // Skip if no prepaid balance is used
            if (!$extensionAttributes ||
                !$extensionAttributes->getLeatLoyaltyBalanceAmount() ||
                (float)$extensionAttributes->getLeatLoyaltyBalanceAmount() <= 0) {
                return $proceed($order);
            }

            // Proceed with order placement
            try {
                return $proceed($order);
            } catch (\Exception $e) {
                // If order placement fails, revert the balance
                $this->errorHandler->revertBalanceOnFailure($quote);
                throw $e;
            }
        } catch (\Exception $e) {
            // Handle validation failures or other exceptions
            $quote = $this->quoteRepository->get($order->getQuoteId());
            $this->errorHandler->handleValidationFailure($quote, $e);

            // This will not be reached as handleValidationFailure throws an exception
            throw $e;
        }
    }
}
