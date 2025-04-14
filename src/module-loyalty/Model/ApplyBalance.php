<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Leat\Loyalty\Api\ApplyBalanceInterface;
use Leat\Loyalty\Api\BalanceValidatorInterface;
use Leat\Loyalty\Api\Data\ApplyBalanceResultInterface;
use Leat\Loyalty\Api\Data\ApplyBalanceResultInterfaceFactory;
use Leat\Loyalty\Model\Order\LeatBalanceRepository;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Apply prepaid balance to quote
 */
class ApplyBalance implements ApplyBalanceInterface
{
    /**
     * @param Config $config
     * @param BalanceValidatorInterface $balanceValidator
     * @param CartRepositoryInterface $quoteRepository
     * @param ApplyBalanceResultInterfaceFactory $resultFactory
     * @param Connector $leatConnector
     */
    public function __construct(
        private readonly Config $config,
        private readonly BalanceValidatorInterface $balanceValidator,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly ApplyBalanceResultInterfaceFactory $resultFactory,
        private readonly Connector $leatConnector
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(string $cartId, float $balanceAmount): ApplyBalanceResultInterface
    {
        $result = $this->resultFactory->create();
        $result->setSuccess(false);
        $result->setBalanceAmount(0);
        $logger = $this->leatConnector->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);

        try {
            // Check if feature is enabled
            if (!$this->config->isPrepaidBalanceEnabled()) {
                throw new LocalizedException(__('Prepaid balance feature is not enabled.'));
            }

            // Get quote
            $quote = $this->quoteRepository->getActive($cartId);

            // Validate balance amount
            if (!$this->balanceValidator->isValid($quote, $balanceAmount)) {
                throw new LocalizedException(
                    __($this->balanceValidator->getErrorMessage() ?: 'Invalid balance amount.')
                );
            }

            // Set balance amount on quote
            $extensionAttributes = $quote->getExtensionAttributes();
            if (!$extensionAttributes) {
                throw new LocalizedException(__('Unable to get quote extension attributes.'));
            }

            // Set leat balance amount
            $extensionAttributes->setLeatLoyaltyBalanceAmount($balanceAmount);
            $quote->setExtensionAttributes($extensionAttributes);

            // Make sure the value is also set on the quote object directly for persistence
            $quote->setData('leat_loyalty_balance_amount', $balanceAmount);

            // Force quote to collect totals after we've set the balance
            $quote->collectTotals();

            // Save the quote with the collected totals
            $this->quoteRepository->save($quote);

            // Set result
            $result->setSuccess(true);
            $result->setBalanceAmount($balanceAmount);

            return $result;
        } catch (\Exception $e) {
            $logger->log(
                'Error applying prepaid balance: ' . $e->getMessage(),
                context: [
                    'exception' => $e->getTraceAsString(),
                    'cartId' => $cartId,
                    'balanceAmount' => $balanceAmount
                ]
            );

            $result->setErrorMessage($e->getMessage());
            return $result;
        }
    }
}
