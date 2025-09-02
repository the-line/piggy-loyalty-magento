<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Builder\Service;

use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\AsyncQueue\Model\Builder\JobBuilder;
use Leat\AsyncQueue\Model\Queue\Request\GenericType;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Helper\GiftcardHelper;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\CustomerContactLink;
use Leat\Loyalty\Model\ResourceModel\Loyalty\GiftcardResource;
use Leat\LoyaltyAsync\Model\Queue\Builder\LoyaltyJobBuilder;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\ContactCreate;
use Leat\LoyaltyAsync\Model\Queue\Type\Giftcard\GiftcardCreate;
use Leat\LoyaltyAsync\Model\Queue\Type\Giftcard\GiftcardEmail;
use Leat\LoyaltyAsync\Model\Queue\Type\Giftcard\GiftcardTransaction;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Model\Order;

class GiftcardPurchaseBuilder
{
    protected Order|OrderInterface $order;

    public function __construct(
        protected LoyaltyJobBuilder $jobBuilder,
        protected RequestTypePool $requestTypePool,
        protected Product $productResource,
        protected GiftcardHelper $giftcardHelper,
        protected CustomerContactLink $customerContactLink,
        protected Config $config,
        protected OrderItemRepositoryInterface $orderItemRepository
    ) {
    }

    /**
     * Add a job for the giftcard orderitem.
     *
     * @param Order $order
     * @param OrderItemInterface $orderItem
     * @return JobInterface[]
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function buildGiftcardPurchaseByOrderItem(OrderInterface $order, OrderItemInterface $orderItem): array
    {
        $jobs = [];
        $this->setOrder($order);

        if ($order->canInvoice()) {
            $qty = $orderItem->getQtyOrdered() - $orderItem->getQtyCanceled();
        } else {
            $qty = $orderItem->getQtyInvoiced() - ($orderItem->getQtyCanceled() + $orderItem->getQtyRefunded());
        }

        for ($index = 0; $index < $qty; $index++) {
            $jobBuilder = $this->jobBuilder
                ->newJob($order->getCustomerId())
                ->setStoreId((int) $order->getStoreId());
            $jobs[] = $this->addGiftcardPurchaseToJob($jobBuilder, $orderItem)->create(false);
        }

        return $jobs;
    }

    /**
     * Add all order items as a transaction into Leat.
     * - All transactions do not contain any points as points are awarded
     *
     * @param JobBuilder $jobBuilder
     * @param array $orderItems
     * @param $storeId
     * @return JobBuilder
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function addGiftcardPurchaseToJob(JobBuilder $jobBuilder, OrderItemInterface $orderItem): JobBuilder
    {
        $buyRequestKeyValue = $this->giftcardHelper->getGiftcardOptionKeyValue($orderItem);
        $isGift = $buyRequestKeyValue[GiftcardResource::BUYREQUEST_OPTION_IS_GIFT] ?? false;
        $customerEmail = $isGift
            ? $buyRequestKeyValue[GiftcardResource::BUYREQUEST_OPTION_RECIPIENT_EMAIL]
            : $this->getOrder()->getCustomerEmail();

        $contactCreate = $this->getContactCreateRequest($customerEmail);
        $giftcardCreate = $this->getGiftcardCreateRequest();
        $transaction = $this->getGiftcardTransactionRequest($orderItem);
        $giftcardEmailRequest = $this->getGiftcardEmailRequest($orderItem, $customerEmail);
        return $jobBuilder
            ->addRequest(
                $contactCreate->getData(),
                ContactCreate::getTypeCode()
            )->addRequest(
                $giftcardCreate->getPayload(),
                GiftcardCreate::getTypeCode()
            )->addRequest(
                $transaction->getPayload(),
                GiftcardTransaction::getTypeCode()
            )->addRequest(
                $giftcardEmailRequest->getData(),
                GiftcardEmail::getTypeCode()
            );
    }

    /**
     * @param OrderItemInterface $orderItem
     * @return GenericType|null
     */
    public function getGiftcardTransactionRequest(OrderItemInterface $orderItem): ?GenericType
    {
        $transaction = $this->requestTypePool->getRequestType(GiftcardTransaction::getTypeCode());
        $transaction
            ->setData(GiftcardTransaction::DATA_ORDER_ITEM_ID_KEY, $orderItem->getItemId())
            ->setData(GiftcardTransaction::DATA_AMOUNT_KEY, $this->getGiftcardAmount($orderItem))
            ->setData(GiftcardTransaction::DATA_INCREMENT_ID_KEY, $this->getOrder()->getIncrementId());
        return $transaction;
    }

    /**
     * @param mixed $isGift
     * @param $buyRequestKeyValue
     * @return GenericType|null
     */
    public function getContactCreateRequest(string $customerEmail): ?GenericType
    {
        $customerCreate = $this->requestTypePool->getRequestType(ContactCreate::getTypeCode());
        return $customerCreate->setData('email', $customerEmail);
    }

    /**
     * @return GenericType|null
     */
    public function getGiftcardCreateRequest(): ?GenericType
    {
        $giftcardCreate = $this->requestTypePool->getRequestType(GiftcardCreate::getTypeCode());
        $giftcardCreate->setData(
            GiftcardCreate::DATA_GIFTCARD_PROGRAM_UUID,
            $this->config->getGiftcardProgramUUID((int) $this->getOrder()->getStoreId())
        )->setData(
            GiftcardCreate::DATA_GIFTCARD_TYPE,
            GiftcardCreate::GIFTCARD_TYPE_DIGITAL
        );
        return $giftcardCreate;
    }

    /**
     * @param OrderItemInterface $orderItem
     * @param mixed $customerEmail
     * @return GenericType|null
     */
    public function getGiftcardEmailRequest(OrderItemInterface $orderItem, mixed $customerEmail): ?GenericType
    {
        $giftcardEmailTags = $this->giftcardHelper->getGiftcardEmailMergeTags($orderItem);
        $sendGiftcardEmail = $this->requestTypePool->getRequestType(GiftcardEmail::getTypeCode());
        $sendGiftcardEmail->setData(
            GiftcardEmail::DATA_GIFTCARD_RECIPIENT_EMAIL,
            $customerEmail
        )->setData(
            GiftcardEmail::DATA_GIFTCARD_EMAIL_UUID,
            null
        )->setData(
            GiftcardEmail::DATA_GIFTCARD_MERGE_TAGS,
            $giftcardEmailTags
        );

        return $sendGiftcardEmail;
    }

    /**
     * @return OrderInterface|Order
     */
    public function getOrder(): OrderInterface|Order
    {
        return $this->order;
    }

    /**
     * Set order currently being processed
     *
     * @param OrderInterface|Order $order
     * @return void
     */
    protected function setOrder(OrderInterface|Order $order)
    {
        $this->order = $order;
    }

    /**
     * Process the order item and get the giftcard amount
     *
     * @param OrderItemInterface $orderItem
     * @return int
     */
    public function getGiftcardAmount(OrderItemInterface $orderItem)
    {
        if (!($productPrice = $orderItem->getPriceInclTax()) && $orderItem->getParentItemId()) {
            $parentItemId = $orderItem->getParentItemId();
            $parentItem = $this->orderItemRepository->get($parentItemId);
            $productPrice = $parentItem->getPriceInclTax();
        }

        // Multiply price by 100 to get cent value
        return (int) (((float) $productPrice) * 100);
    }
}
