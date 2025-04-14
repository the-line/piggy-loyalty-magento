<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Controller\Reward;

use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\RewardResource;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class Redeem implements HttpPostActionInterface
{
    protected const string LOGGER_PURPOSE = 'redeem_reward';

    protected const string REWARD_ID_PARAM = 'reward_id';

    protected const string REDEEM_SUCCESS_MESSAGE = 'Reward redeemed successfully.';
    protected const string REDEEM_ERROR_MESSAGE = 'An error occurred while redeeming the reward, please check again in a few minutes to see if the has successfully redeemed.';

    public function __construct(
        protected JsonFactory $resultJsonFactory,
        protected RequestInterface $request,
        protected Session $customerSession,
        protected StoreManagerInterface $storeManager,
        protected RewardResource $rewardResource,
        protected Connector $connector,
        protected ManagerInterface $messageManager
    ) {
    }

    /**
     * Execute action
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        if (!$this->customerSession->isLoggedIn()) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Please log in to redeem rewards.')
            ]);
        }

        $rewardId = $this->request->getParam(self::REWARD_ID_PARAM);
        if (!$rewardId) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('No reward ID provided.')
            ]);
        }

        $success = false;
        try {
            $customerId = (int)$this->customerSession->getCustomerId();
            $storeId = (int)$this->storeManager->getStore()->getId();

            // Use RewardResource to directly redeem the reward
            $result = $this->rewardResource->createRewardReception($customerId, $rewardId, $storeId);

            // If we get here, the redemption was successful
            $success = true;
            $message = self::REDEEM_SUCCESS_MESSAGE;
        } catch (\Throwable $e) {
            $this->connector->getLogger(self::LOGGER_PURPOSE)->log(sprintf(
                "Error redeeming reward: %s \n %s",
                $e->getMessage(),
                $e->getTraceAsString()
            ));
            $message = self::REDEEM_ERROR_MESSAGE;
        } finally {
            if ($success) {
                $this->messageManager->addSuccessMessage($message);
            } else {
                $this->messageManager->addErrorMessage($message);
            }

            return $resultJson->setData([
                'success' => $success,
                'message' => $message
            ]);
        }
    }
}
