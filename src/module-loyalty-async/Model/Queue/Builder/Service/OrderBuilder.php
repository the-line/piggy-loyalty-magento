<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Builder\Service;

use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\AsyncQueue\Model\Builder\JobBuilder;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Setup\Patch\Data\AddLeatGiftcardAttribute;
use Leat\LoyaltyAsync\Model\Queue\Builder\LoyaltyJobBuilder;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Credit\Transaction;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Order\Balance\Transaction\OrderItemTransaction;
use Magento\Catalog\Api\Data\ProductInterface;
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
        protected Config $config
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

        $count = $this->addOrderItemsToJob($jobBuilder, $order);
        if ($count === 0) {
            return null; // No valid order items to process
        }

        return $jobBuilder->create();
    }

    /**
     * Add all order items as a transaction into Leat.
     * - All transactions do not contain any points as points are awarded
     *
     * @param JobBuilder $jobBuilder
     * @param Order $order
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function addOrderItemsToJob(JobBuilder $jobBuilder, OrderInterface $order): int
    {
        $filteredOrderItems = $this->filterOrderItems($order);
        foreach ($filteredOrderItems as $orderItem) {
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

        return count($filteredOrderItems);
    }

    /**
     * Filter dummy products from order items
     *
     * @param Order $order
     * @return OrderItemInterface[]
     */
    protected function filterOrderItems(Order $order): array
    {
        $filterGiftcardProducts = !$this->config->getGiftcardPointExclusionStatus((int) $order->getStoreId());
        $orderItems = [];
        foreach ($order->getItems() as $orderItem) {
            $product = $orderItem->getProduct();
            if ($orderItem->isDummy() || ($filterGiftcardProducts && $product && $this->isLeatGiftcard($product))) {
                continue;
            }

            $orderItems[$orderItem->getItemId()] = $orderItem;
        }

        return $orderItems;
    }

    /**
     * Check if the product is a gift card product.
     *
     * @param ProductInterface $product
     * @return bool
     */
    protected function isLeatGiftcard(ProductInterface $product): bool
    {
        $giftcardAttrValue = $product->getData(AddLeatGiftcardAttribute::GIFTCARD_ATTRIBUTE_CODE);
        return $giftcardAttrValue && $giftcardAttrValue !== '0';
    }
}
