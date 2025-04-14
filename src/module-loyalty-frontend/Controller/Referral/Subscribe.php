<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Controller\Referral;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class Subscribe implements HttpPostActionInterface
{
    /**
     * @param RequestInterface $request
     * @param JsonFactory $resultJsonFactory
     * @param Validator $formKeyValidator
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param ContactResource $contactResource
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $resultJsonFactory,
        private readonly Validator $formKeyValidator,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly ContactResource $contactResource,
    ) {
    }

    /**
     * Execute referral subscribe action
     *
     * @return Json
     * @throws NoSuchEntityException
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        $storeId = (int)$this->storeManager->getStore()->getId();
        $logger = $this->contactResource->getLogger('referral');

        try {
            if (!$this->formKeyValidator->validate($this->request)) {
                throw new LocalizedException(__('Invalid form key'));
            }

            $email = $this->request->getParam('email');
            $referralCode = $this->request->getParam('referral_code');

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new LocalizedException(__('Invalid email address'));
            }

            if (!$referralCode || !is_string($referralCode)) {
                throw new LocalizedException(__('Invalid referral code'));
            }

            $this->contactResource->submitReferredContactCreation($email, $referralCode, $storeId);

            // Get the success message from configuration
            $successMessage = $this->config->getReferralPopupSuccessMessage($storeId);

            return $resultJson->setData([
                'success' => true,
                'message' => $successMessage
            ]);
        } catch (LocalizedException $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $logger->log('Error processing referral subscription: ' . $e->getMessage());

            return $resultJson->setData([
                'success' => false,
                'message' => __('An error occurred. Please try again later.')
            ]);
        }
    }
}
