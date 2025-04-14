<?php
declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Controller\Coupon;

use Leat\Loyalty\Model\AppliedCouponsManager;
use Leat\Loyalty\Model\Connector;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Controller for removing vouchers/coupons
 */
class Remove implements HttpPostActionInterface
{
    /**
     * Logger purpose for the Leat connector
     */
    protected const string LOGGER_PURPOSE = 'coupon_remove';

    public function __construct(
        protected RequestInterface $request,
        protected JsonFactory $jsonFactory,
        protected Connector $leatConnector,
        protected AppliedCouponsManager $appliedCouponsManager,
    ) {
    }

    /**
     * Remove a reward/coupon from the cart
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultJson = $this->jsonFactory->create();

        try {
            $rewardId = $this->request->getParam('reward_id');

            if (!$rewardId) {
                throw new LocalizedException(__('Missing reward ID parameter'));
            }

            // Remove the coupon from the customer's account
            $this->appliedCouponsManager->removeCoupon($rewardId);

            return $resultJson->setData([
                'success' => true,
                'message' => __('Voucher successfully removed from your cart.'),
                'reward_id' => $rewardId
            ]);
        } catch (LocalizedException $e) {
            $this->leatConnector->getLogger(self::LOGGER_PURPOSE)->log(
                'Error removing voucher: ' . $e->getMessage(),
            );

            return $resultJson->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            $this->leatConnector->getLogger(self::LOGGER_PURPOSE)->log(
                'Unexpected error removing voucher: ' . $e->getMessage(),
            );

            return $resultJson->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => __('An unexpected error occurred while removing the voucher.')
            ]);
        }
    }
}
