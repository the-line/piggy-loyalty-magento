<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Controller\Balance;

use Leat\Loyalty\Api\BalanceValidatorInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class GetApplied implements HttpGetActionInterface
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param JsonFactory $resultJsonFactory
     * @param BalanceValidatorInterface $balanceValidator
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly JsonFactory $resultJsonFactory,
        private readonly BalanceValidatorInterface $balanceValidator
    ) {
    }

    /**
     * Returns currently applied balance amount
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $quote = $this->checkoutSession->getQuote();
            $appliedBalance = $this->balanceValidator->getSavedBalanceAmount($quote);

            return $resultJson->setData([
                'success' => true,
                'applied_balance' => $appliedBalance
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
