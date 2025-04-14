<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Controller\Checkout;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\Order\LeatBalanceRepository;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;

class ApplyBalance implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param FormKeyValidator $formKeyValidator
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param CartRepositoryInterface $quoteRepository
     * @param CartTotalRepositoryInterface $cartTotalRepository
     * @param Config $config
     * @param Connector $leatConnector
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly CartTotalRepositoryInterface $cartTotalRepository,
        private readonly Config $config,
        private readonly Connector $leatConnector
    ) {
    }

    /**
     * Execute apply balance action
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $logger = $this->leatConnector->getLogger(LeatBalanceRepository::LOGGER_PURPOSE);

        try {
            // Check if feature is enabled
            if (!$this->config->isPrepaidBalanceEnabled()) {
                throw new LocalizedException(__('Prepaid balance feature is not enabled.'));
            }

            // Validate form key
            if (!$this->formKeyValidator->validate($this->request)) {
                throw new LocalizedException(__('Invalid form key.'));
            }

            // Get balance amount from request
            $balanceAmount = (float)$this->request->getParam('balance_amount', 0);
            if ($balanceAmount < 0) {
                throw new LocalizedException(__('Invalid balance amount.'));
            }

            // Get quote
            $quote = $this->getQuote();
            if (!$quote->getCustomerId()) {
                throw new LocalizedException(__('Customer is not logged in.'));
            }

            // Set balance amount on quote
            $extensionAttributes = $quote->getExtensionAttributes();
            if (!$extensionAttributes) {
                throw new LocalizedException(__('Unable to get quote extension attributes.'));
            }

            // Set leat balance amount
            $extensionAttributes->setLeatLoyaltyBalanceAmount($balanceAmount);

            // Save quote
            $this->quoteRepository->save($quote);

            // Get updated totals
            $cartTotals = $this->cartTotalRepository->get($quote->getId());

            return $result->setData([
                'success' => true,
                'balance_amount' => $balanceAmount,
                'totals' => $cartTotals->getData()
            ]);
        } catch (\Exception $e) {
            $logger->log(
                'Error applying prepaid balance: ' . $e->getMessage(),
                context: [
                    'exception' => $e->getTraceAsString(),
                    'request' => $this->request->getParams()
                ]
            );

            return $result->setData([
                'success' => false,
                'error_message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get active quote
     *
     * @return \Magento\Quote\Api\Data\CartInterface
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getQuote()
    {
        if (!$this->customerSession->isLoggedIn()) {
            throw new LocalizedException(__('Customer must be logged in to use prepaid balance.'));
        }

        return $this->checkoutSession->getQuote();
    }
}
