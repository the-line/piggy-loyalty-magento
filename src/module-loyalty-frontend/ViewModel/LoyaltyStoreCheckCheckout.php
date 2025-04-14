<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\ViewModel;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\CustomerContactLink;
use Leat\Loyalty\Service\CreditCalculator;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;

class LoyaltyStoreCheckCheckout extends LoyaltyStoreCheck
{
    /**
     * @param Config $config
     * @param HttpContext $httpContext
     * @param StoreManagerInterface $storeManager
     * @param CreditCalculator $calculator
     * @param Session $checkoutSession
     */
    public function __construct(
        Config $config,
        HttpContext $httpContext,
        StoreManagerInterface $storeManager,
        CreditCalculator $calculator,
        protected CustomerContactLink $contactLink,
        protected Session $checkoutSession
    ) {
        parent::__construct($config, $httpContext, $storeManager, $calculator);
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }

    public function getCustomerUuid(int $customerId)
    {
        return $this->contactLink->getContactUuid($customerId);
    }

    /**
     * @param Order $order
     * @return int
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getPointsEstimationByOrder(Order $order): int
    {
        return $this->getPointsEstimation(
            (float) $order->getSubtotalInclTax() - abs((float) $order->getBaseDiscountAmount()),
            $order->getCustomerId() ? $this->contactLink->getContactUuid($order->getCustomerId()) : null
        );
    }
}
