<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

use Leat\Loyalty\Api\ApplyGiftCardInterface;
use Leat\Loyalty\Api\Data\ApplyGiftCardResultInterface;
use Leat\Loyalty\Api\Data\ApplyGiftCardResultInterfaceFactory;
use Leat\Loyalty\Api\Data\AppliedGiftCardDetailsInterfaceFactory; // Added this
use Leat\Loyalty\Model\GiftCard\ApplicationService;
use Leat\Loyalty\Model\GiftCard\ValidatorService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Quote\Model\QuoteFactory;

/**
 * Apply gift card to quote
 */
class ApplyGiftCard implements ApplyGiftCardInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly ValidatorService $validatorService,
        private readonly ApplicationService $applicationService,
        private readonly ApplyGiftCardResultInterfaceFactory $resultFactory,
        private readonly AppliedGiftCardDetailsInterfaceFactory $appliedGiftCardDetailsFactory, // Added this
        private readonly Connector $leatConnector,
        private readonly PricingHelper $pricingHelper,
        private readonly QuoteFactory $quoteFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(string $cartId, string $code): ApplyGiftCardResultInterface
    {
        /** @var \Leat\Loyalty\Api\Data\ApplyGiftCardResultInterface $result */
        $result = $this->resultFactory->create();
        $result->setSuccess(false);
        $logger = $this->leatConnector->getLogger(ApplicationService::LOGGER_PURPOSE);

        try {
            if (empty(trim($code))) {
                $result->setErrorMessage((string) __('Missing gift card code.'));
                return $result;
            }

            $quote = $this->quoteRepository->getActive((int)$cartId);
            $storeId = (int) $quote->getStoreId();

            if (!$this->validatorService->isValid($code, $storeId)) {
                $result->setErrorMessage((string) __('GiftCard is invalid or expired.'));
                return $result;
            }

            $originalCardBalance = $this->validatorService->getAvailableBalance($code, $storeId);
            if ($originalCardBalance <= 0) {
                $result->setErrorMessage((string) __('There is no available balance on the gift card.'));
                return $result;
            }

            // The ApplicationService now expects originalCardBalance.
            // The actual amount applied will be determined by collectors.
            // For the purpose of this API call, we pass the originalCardBalance.
            $appliedGiftCardObject = $this->applicationService->applyToQuote(
                (int) $cartId,
                $code,
                $originalCardBalance
            );

            $result->setSuccess(true);

            // Force quote to collect totals after we've set the balance
            $quote->collectTotals();

            // Save the quote with the collected totals
            $this->quoteRepository->save($quote);

            // Prepare the nested card details array
            $maskedCode = '••••' . substr($code, -4);
            // For the API response, format the original card balance as the "applied amount"
            $formattedAmount = $this->pricingHelper->currency($originalCardBalance, true, false);

            /** @var \Leat\Loyalty\Api\Data\AppliedGiftCardDetailsInterface $cardDetailsDto */
            $cardDetailsDto = $this->appliedGiftCardDetailsFactory->create();
            $cardDetailsDto->setId((int)$appliedGiftCardObject->getId());
            $cardDetailsDto->setMaskedCode($maskedCode);
            $cardDetailsDto->setAppliedAmountFormatted($formattedAmount);

            $result->setAppliedCard($cardDetailsDto);
            return $result;
        } catch (LocalizedException $e) {
            $logger->log(
                'Localized error applying giftcard: ' . $e->getMessage(),
                false,
                [
                    'exception_trace' => $e->getTraceAsString(),
                    'cartId' => $cartId,
                    'card' => $code
                ]
            );
            $result->setErrorMessage($e->getMessage());
            return $result;
        } catch (\Exception $e) {
            $logger->log(
                'Generic error applying giftcard: ' . $e->getMessage(),
                false,
                [
                    'exception_trace' => $e->getTraceAsString(),
                    'cartId' => $cartId,
                    'card' => $code
                ]
            );
            $result->setErrorMessage((string) __('An unexpected error occurred while applying the gift card.'));
            return $result;
        }
    }
}
