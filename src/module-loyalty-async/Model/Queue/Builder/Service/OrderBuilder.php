<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Builder\Service;

use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\AsyncQueue\Model\Builder\JobBuilder;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\LoyaltyAsync\Model\Queue\Builder\LoyaltyJobBuilder;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Credit\Transaction;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Order\Balance\Transaction\OrderItemTransaction;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;

class OrderBuilder
{
    public function __construct(
        protected LoyaltyJobBuilder $jobBuilder,
        protected RequestTypePool $requestTypePool,
        protected Product $productResource,
    ) {
    }

    /**
     * Add a job for the order.
     * - Each order item is added as a transaction into Leat.
     *
     * @param Order $order
     * @return JobInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function addTransactionJob(OrderInterface $order): ?JobInterface
    {
        $jobBuilder = $this->jobBuilder
            ->newJob($order->getCustomerId())
            ->setStoreId((int) $order->getStoreId());

        $this->addOrderItemsToJob($jobBuilder, $order);

        return $jobBuilder->create();
    }

    /**
     * Add all order items as a transaction into Leat.
     * - All transactions do not contain any points as points are awarded
     *
     * @param JobBuilder $jobBuilder
     * @param Order $order
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function addOrderItemsToJob(JobBuilder $jobBuilder, OrderInterface $order): void
    {
        foreach ($this->filterOrderItems($order) as $orderItem) {
            $product = $orderItem->getProduct();
            if (!$product) {
                continue;
            }

            $transaction = $this->requestTypePool->getRequestType(OrderItemTransaction::getTypeCode());
            $transaction
                ->setData(Transaction::DATA_SKU_KEY, $product->getSku())
                ->setData(Transaction::DATA_ROW_TOTAL_KEY, $orderItem->getBaseRowTotal())
                ->setData(Transaction::DATA_PRODUCT_NAME_KEY, $product->getName())
                ->setData(Transaction::DATA_ORDER_ITEM_ID_KEY, $orderItem->getItemId())
                ->setData(Transaction::DATA_INCREMENT_ID_KEY, $order->getIncrementId())
                ->setData(Transaction::DATA_UNIT_NAME_KEY, Transaction::DEFAULT_UNIT_NAME);

            if ($brandAttribute = $this->productResource->getAttribute('brand')) {
                $brandName = $brandAttribute->getFrontend()?->getValue($product) ?? '';
                $transaction->setData(Transaction::DATA_BRAND_KEY, $brandName);
            }

            $jobBuilder->addRequest(
                $transaction->getPayload(),
                OrderItemTransaction::getTypeCode()
            );
        }
    }

    /**
     * Filter dummy products from order items
     *
     * @param Order $order
     * @return OrderItemInterface[]
     */
    protected function filterOrderItems(Order $order): array
    {
        $orderItems = [];
        foreach ($order->getItems() as $orderItem) {
            if ($orderItem->isDummy()) {
                continue;
            }

            $orderItems[$orderItem->getItemId()] = $orderItem;
        }

        return $orderItems;
    }
}
