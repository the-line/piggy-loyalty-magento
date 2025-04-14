<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Leat\Loyalty\Api\BalanceValidatorInterface;
use Leat\Loyalty\Model\Order\LeatBalanceRepository;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Validates if a requested prepaid balance amount is valid
 */
class BalanceValidator implements BalanceValidatorInterface
{
    /**
     * @var string|null
     */
    private ?string $errorMessage = null;

    private ?Logger $logger = null;

    /**
     * @param Config $config
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param ContactResource $contactResource
     */
    public function __construct(
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly ContactResource $contactResource
    ) {
    }

    /**
     * Check if requested balance amount is valid
     *
     * @param CartInterface $quote
     * @param float $amount
     * @return bool
     */
    public function isValid(CartInterface $quote, float $amount): bool
    {
        $this->logger = $this->contactResource->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);

        $this->errorMessage = null;
        $storeId = (int) $quote->getStoreId();

        try {
            // Check if feature is enabled
            if (!$this->config->isPrepaidBalanceEnabled($storeId)) {
                $this->errorMessage = (string) __('Prepaid balance feature is not enabled.');
                return false;
            }

            // Validate amount is positive
            if ($amount < 0) {
                $this->errorMessage = (string) __('Balance amount cannot be negative.');
                return false;
            }

            // Skip validation if amount is zero (cancelling applied balance)
            if ($amount == 0) {
                return true;
            }

            // Check if customer is logged in
            if (!$quote->getCustomerId()) {
                $this->errorMessage = (string) __('Customer must be logged in to use prepaid balance.');
                return false;
            }

            // Get customer prepaid balance
            $availableBalance = $this->getAvailableBalance((int) $quote->getCustomerId());
            if ($availableBalance <= 0) {
                $this->errorMessage = (string) __('No prepaid balance available.');
                return false;
            }

            // Check if amount exceeds available balance
            if ($amount > $availableBalance) {
                $this->errorMessage = (string) __('Requested amount exceeds available prepaid balance.');
                return false;
            }

            // Check if amount exceeds quote grand total
            $grandTotal = (float) $quote->getBaseGrandTotal();
            $alreadyAppliedBalance = (float) $quote->getData('leat_loyalty_balance_amount');
            if ($amount > ($grandTotal + $alreadyAppliedBalance)) {
                $this->errorMessage = (string) __('Requested amount exceeds order total.');
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->log(
                'Error validating prepaid balance: ' . $e->getMessage(),
                context: [
                    'exception' => $e->getTraceAsString()
                ]
            );
            $this->errorMessage = (string) __('An error occurred while validating prepaid balance: %1', $e->getMessage());
            return false;
        }
    }

    /**
     * Get error message if validation failed
     *
     * @return string|null
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Get the quote's saved leat balance amount
     *
     * @param CartInterface $quote
     * @return float
     */
    public function getSavedBalanceAmount(CartInterface $quote): float
    {
        // First check direct data field
        $directAmount = $quote->getData('leat_loyalty_balance_amount');
        if ($directAmount) {
            return (float)$directAmount;
        }

        // Then try extension attributes
        $extensionAttributes = $quote->getExtensionAttributes();
        if ($extensionAttributes && $extensionAttributes->getLeatLoyaltyBalanceAmount()) {
            return (float)$extensionAttributes->getLeatLoyaltyBalanceAmount();
        }

        return 0.0;
    }

    /**
     * Get customer's available prepaid balance
     *
     * @param int $customerId
     * @return float
     * @throws NoSuchEntityException
     */
    private function getAvailableBalance(int $customerId): float
    {
        try {
            // First check if customer is in session with pre-loaded balance data
            if ($this->customerSession->isLoggedIn() && $this->customerSession->getCustomerId() == $customerId) {
                // If customer is in session, check for session data first
                $leatData = $this->customerSession->getLeatData();
                if (isset($leatData['prepaidBalance'])) {
                    return (float)$leatData['prepaidBalance'];
                }
            }

            // If not in session or not loaded, get from Leat API through ContactResource
            if (!$this->contactResource->hasContactUuid($customerId)) {
                // No contact linked to customer yet
                return 0.0;
            }

            $contact = $this->contactResource->getCustomerContact($customerId);
            if (!$contact) {
                return 0.0;
            }

            // Get balance in cents and convert to dollars
            $prepaidBalanceCents = $contact->getPrepaidBalance()->getBalanceInCents() ?? 0;
            return (float)($prepaidBalanceCents / 100);
        } catch (\Exception $e) {
            $this->logger->log(
                'Error fetching prepaid balance: ' . $e->getMessage(),
                context: [
                'exception' => $e->getTraceAsString(),
                'customerId' => $customerId
                ]
            );
            throw new LocalizedException(__('Unable to fetch prepaid balance.'));
        }
    }
}
