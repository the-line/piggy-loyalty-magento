<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Model\ErrorHandler;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Order\LeatBalanceRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Error handler for order placement with prepaid balance
 */
class OrderPlacement
{
    /**
     * @param Config $config
     * @param CartRepositoryInterface $quoteRepository
     * @param Connector $leatConnector
     */
    public function __construct(
        private readonly Config $config,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly Connector $leatConnector
    ) {
    }

    /**
     * Handle validation failures during order placement
     *
     * @param CartInterface $quote
     * @param \Exception $exception
     * @return void
     * @throws \Exception
     */
    public function handleValidationFailure(CartInterface $quote, \Exception $exception): void
    {
        $logger = $this->leatConnector->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);

        $logger->log(
            'Validation failure during order placement with prepaid balance',
            context: [
                'quote_id' => $quote->getId(),
                'customer_id' => $quote->getCustomerId(),
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]
        );

        try {
            // Reset balance amount on quote
            $extensionAttributes = $quote->getExtensionAttributes();
            if ($extensionAttributes && $extensionAttributes->getLeatLoyaltyBalanceAmount() > 0) {
                $extensionAttributes->setLeatLoyaltyBalanceAmount(0);
                $this->quoteRepository->save($quote);

                $logger->log(
                    'Reset prepaid balance on quote due to validation failure',
                    context: ['quote_id' => $quote->getId()]
                );
            }
        } catch (\Exception $e) {
            $logger->log(
                'Error resetting prepaid balance on quote',
                context: [
                    'quote_id' => $quote->getId(),
                    'exception' => $e->getMessage()
                ]
            );
        }

        throw new LocalizedException(
            __('Unable to place order with prepaid balance: %1', $exception->getMessage())
        );
    }

    /**
     * Revert any applied balance if order placement fails
     *
     * @param CartInterface $quote
     * @return void
     */
    public function revertBalanceOnFailure(CartInterface $quote): void
    {
        $logger = $this->leatConnector->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);

        try {
            $extensionAttributes = $quote->getExtensionAttributes();
            if ($extensionAttributes && $extensionAttributes->getLeatLoyaltyBalanceAmount() > 0) {
                $balanceAmount = (float)$extensionAttributes->getLeatLoyaltyBalanceAmount();

                $logger->log(
                    'Reverting prepaid balance due to order placement failure',
                    context: [
                        'quote_id' => $quote->getId(),
                        'balance_amount' => $balanceAmount
                    ]
                );

                // Reset balance amount on quote
                $extensionAttributes->setLeatLoyaltyBalanceAmount(0);
                $this->quoteRepository->save($quote);
            }
        } catch (\Exception $e) {
            $logger->log(
                'Error reverting prepaid balance after order failure',
                context: [
                    'quote_id' => $quote->getId(),
                    'exception' => $e->getMessage()
                ]
            );
        }
    }
}
