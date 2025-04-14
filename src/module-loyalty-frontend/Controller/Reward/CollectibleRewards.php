<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Controller\Reward;

use Leat\Loyalty\Model\AppliedCouponsManager;
use Leat\LoyaltyFrontend\Block\Widget\YourCoupons;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class CollectibleRewards implements HttpGetActionInterface
{
    public function __construct(
        protected JsonFactory $resultJsonFactory,
        protected Session $customerSession,
        protected YourCoupons $yourCoupons,
        protected AppliedCouponsManager $appliedCouponsManager,
    ) {
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();
        if (!$this->customerSession->isLoggedIn()) {
            return $resultJson->setData([
                'success' => false,
                'message' => __('Customer is not logged in')
            ]);
        }

        $couponsArray = [];
        $success = false;
        try {
            // We need to decode the JSON string from the block and pass it directly as an array
            $couponsJson = $this->yourCoupons->getCollectableRewardsJson();
            $couponsArray = json_decode($couponsJson, true) ?: [];

            // Add 'is_applied' flag to each coupon
            $appliedCoupons = $this->appliedCouponsManager->getAllAppliedCoupons(true);
            foreach ($couponsArray as &$coupon) {
                if (isset($coupon['id']) && in_array($coupon['id'], $appliedCoupons)) {
                    $coupon['is_applied'] = true;
                } else {
                    $coupon['is_applied'] = false;
                }
            }
            $success = true;
        } catch (\Throwable $e) {
            $this->yourCoupons->getLogger()->log($e->getMessage());
        } finally {
            return $resultJson->setData([
                'success' => $success,
                'coupons' => $couponsArray
            ]);
        }
    }
}
