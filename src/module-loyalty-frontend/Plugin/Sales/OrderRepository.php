<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Plugin\Sales;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Plugin for OrderRepository to handle leat_loyalty_balance_amount extension attribute
 */
class OrderRepository
{
    /**
     * @param OrderExtensionFactory $orderExtensionFactory
     */
    public function __construct(
        private readonly OrderExtensionFactory $orderExtensionFactory
    ) {
    }

    /**
     * Add leat_loyalty_balance_amount extension attribute to order data after get
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $order
     * @return OrderInterface
     */
    public function afterGet(
        OrderRepositoryInterface $subject,
        OrderInterface $order
    ): OrderInterface {
        return $this->processExtensionAttributes($order);
    }

    /**
     * Add leat_loyalty_balance_amount extension attribute to order data collection after getList
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $searchResult
     * @return OrderSearchResultInterface
     */
    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $searchResult
    ): OrderSearchResultInterface {
        $orders = $searchResult->getItems();

        foreach ($orders as $order) {
            $this->processExtensionAttributes($order);
        }

        return $searchResult;
    }

    /**
     * Process extension attributes for an order
     *
     * @param OrderInterface $order
     * @return OrderInterface
     */
    private function processExtensionAttributes(OrderInterface $order): OrderInterface
    {
        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->orderExtensionFactory->create();
        }

        // Set leat_loyalty_balance_amount if not already set
        if ($extensionAttributes->getLeatLoyaltyBalanceAmount() === null) {
            $leatBalanceAmount = $order->getData('leat_loyalty_balance_amount');
            if ($leatBalanceAmount !== null) {
                $extensionAttributes->setLeatLoyaltyBalanceAmount((float)$leatBalanceAmount);
            } else {
                $extensionAttributes->setLeatLoyaltyBalanceAmount(0.0);
            }
        }

        $order->setExtensionAttributes($extensionAttributes);

        return $order;
    }
}
