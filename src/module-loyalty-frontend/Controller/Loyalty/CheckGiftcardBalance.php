<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Controller\Loyalty;

use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Json;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Logger;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface;

class CheckGiftcardBalance implements HttpPostActionInterface
{
    private ?Logger $leatLogger = null;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param GiftcardResource $giftcardResource
     * @param Config $config
     * @param Connector $leatConnector
     * @param DateTime $dateTime
     * @param PriceHelper $priceHelper
     * @param TimezoneInterface $localeDate
     * @param SessionManagerInterface $session
     */
    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly RequestInterface $request,
        private readonly GiftcardResource $giftcardResource,
        private readonly Config $config,
        private readonly Connector $leatConnector,
        private readonly DateTime $dateTime,
        private readonly PriceHelper $priceHelper,
        private readonly TimezoneInterface $localeDate,
        private readonly SessionManagerInterface $session
    ) {
         $this->leatLogger = $this->leatConnector->getLogger('leat_giftcard_balance_check');
    }

    /**
     * Execute action based on request and return result
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        $response = ['success' => false, 'message' => __('An error occurred.')];

        // Use renamed config path check
        if (!$this->config->isGiftcardEnabled()) {
             $response['message'] = __('Gift card functionality is disabled.');
             return $resultJson->setData($response);
        }

        // Check rate limit based on session
        if (!$this->checkRateLimit()) {
            $response['message'] = __('Please try again later.');
            $this->leatLogger->log('[CheckGiftcardBalance] Rate limit exceeded for session.', false);
            return $resultJson->setData($response);
        }

        $content = $this->request->getContent();
        $giftcardCode = null;
        if ($content) {
            $params = json_decode($content, true);
            $giftcardCode = $params['giftcard_code'] ?? null;
        }

        if (empty($giftcardCode)) {
            $response['message'] = __('Please enter a gift card code.');
            return $resultJson->setData($response);
        }

        try {
            // --- Call Leat API to get gift card details ---
            $this->leatLogger->log('[CheckGiftcardBalance] Checking hash: ' . $giftcardCode, true);
            $giftcardData = $this->giftcardResource->findGiftcardByHash($giftcardCode);

            if (empty($giftcardData->getUuid())) {
                 throw new LocalizedException(__('Gift card not found.'));
            }

            // --- Prepare Response Data ---
            $isActive = (bool)($giftcardData->isActive() ?? false);
            $availableAmountCents = (int)($giftcardData->getAmountInCents() ?? 0);
            $availableAmount = $availableAmountCents / 100.0;
            $expirationDate = $giftcardData->getExpirationDate();
            $formattedExpiration = null;

            $isExpired = false;
            if ($expirationDate) {
                if ($this->dateTime->gmtTimestamp($expirationDate) < $this->dateTime->gmtTimestamp()) {
                    $isExpired = true;
                    $isActive = false;
                }
                try {
                    $formattedExpiration = $this->localeDate->formatDateTime(
                        $expirationDate,
                        \IntlDateFormatter::MEDIUM,
                        \IntlDateFormatter::NONE,
                        null,
                        'UTC'
                    );
                } catch (\Exception $dateFormatError) {
                    $this->leatLogger->log('Could not format expiration date: ' . $dateFormatError->getMessage(), false);
                    $formattedExpiration = $expirationDate;
                }
            }

            $response['success'] = true;
            $response['message'] = __('Balance retrieved successfully.');
            $response['is_active'] = $isActive && !$isExpired;
            $response['amount_cents'] = $availableAmountCents;
            $response['amount'] = $availableAmount;
            $response['formatted_amount'] = $this->priceHelper->currency($availableAmount, true, false);
            $response['expiration_date'] = $formattedExpiration;
            $response['is_expired'] = $isExpired;

            $this->leatLogger->log('[CheckGiftcardBalance] Balance check successful.', true, ['code' => $giftcardCode, 'response' => $response]);
        } catch (LocalizedException $e) {
            $this->leatLogger->log('[CheckGiftcardBalance] Check failed: ' . $e->getMessage(), false, ['code' => $giftcardCode]);
            $response['message'] = $e->getMessage();
        } catch (\Exception $e) {
            $this->leatLogger->log('[CheckGiftcardBalance] Unexpected error: ' . $e->getMessage(), false, ['exception' => $e->getTraceAsString(), 'code' => $giftcardCode]);
            $response['message'] = __('An unexpected error occurred while checking the balance.');
        }

        return $resultJson->setData($response);
    }

    /**
     * Check if the current session has exceeded the rate limit for balance checks
     *
     * @return bool
     */
    private function checkRateLimit(): bool
    {
        $maxChecks = $this->config->getMaxGiftcardBalanceChecks();
        $today = date('Y-m-d');

        $rateLimitData = $this->session->getLeatGiftcardBalanceChecks() ?: [];

        // Reset counter if it's a new day
        if (!isset($rateLimitData['date']) || $rateLimitData['date'] !== $today) {
            $rateLimitData = [
                'date' => $today,
                'count' => 0
            ];
        }

        // Check if limit is exceeded
        if ($rateLimitData['count'] >= $maxChecks) {
            return false;
        }

        // Increment the counter
        $rateLimitData['count']++;

        // Save updated data back to session
        $this->session->setLeatGiftcardBalanceChecks($rateLimitData);

        return true;
    }
}
