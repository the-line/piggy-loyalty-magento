<?php

declare(strict_types=1);

namespace Leat\Loyalty\Plugin\OrderRepository;

use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Plugin to load leat balance data when loading orders
 */
class LoadLeatBalanceData
{
    /**
     * @param OrderExtensionFactory $orderExtensionFactory
     */
    public function __construct(
        private readonly OrderExtensionFactory $orderExtensionFactory
    ) {
    }

    /**
     * Add leat balance amount to order extension attributes
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderInterface $order
     * @return OrderInterface
     */
    public function afterGet(
        OrderRepositoryInterface $subject,
        OrderInterface $order
    ): OrderInterface {
        $this->loadLeatBalanceData($order);
        return $order;
    }

    /**
     * Add leat balance data to order collection
     *
     * @param OrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $orderSearchResult
     * @return OrderSearchResultInterface
     */
    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $orderSearchResult
    ): OrderSearchResultInterface {
        foreach ($orderSearchResult->getItems() as $order) {
            $this->loadLeatBalanceData($order);
        }
        return $orderSearchResult;
    }

    /**
     * Load leat balance data from order and set to extension attributes
     *
     * @param OrderInterface $order
     * @return void
     */
    private function loadLeatBalanceData(OrderInterface $order): void
    {
        $balanceAmount = $order->getData('leat_loyalty_balance_amount');

        if ($balanceAmount === null) {
            return;
        }

        $extensionAttributes = $order->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->orderExtensionFactory->create();
        }

        $extensionAttributes->setLeatLoyaltyBalanceAmount((float)$balanceAmount);
        $order->setExtensionAttributes($extensionAttributes);
    }
}
