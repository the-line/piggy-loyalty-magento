<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Builder\Service;

use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Api\AppliedGiftCardRepositoryInterface;
use Leat\Loyalty\Api\OrderLeatBalanceRepositoryInterface;
use Leat\Loyalty\Model\AppliedCouponsManager;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\CustomerContactLink;
use Leat\Loyalty\Model\QuoteItem\ExtensionAttributes;
use Leat\Loyalty\Model\ResourceModel\QuoteItem\ExtensionAttributes as QuoteItemExtensionAttributes;
use Leat\Loyalty\Model\ResourceModel\SalesRule\ExtensionAttributes as SalesRuleExtensionAttributes;
use Leat\Loyalty\Setup\Patch\Data\AddLeatGiftcardAttribute;
use Leat\LoyaltyAsync\Model\Queue\Builder\LoyaltyJobBuilder;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Order\CreateAndProcess;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use Magento\SalesRule\Api\RuleRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Builds async queue jobs that submit order payloads to the Leat API.
 *
 * This builder translates Magento order data into the structure expected by
 * the `CreateAndProcess` queue request. It also gathers related information
 * such as:
 * - customer contact UUIDs,
 * - shop UUIDs,
 * - line items,
 * - discounts,
 * - charges,
 * - payments,
 * - timestamps.
 *
 * The builder stores the current order temporarily so helper methods can derive
 * external identifiers from it while constructing the payload.
 */
class OrderApiBuilder
{
    /** Max seconds to wait for giftcard UUID before allowing order export anyway. */
    private const GIFTCARD_UUID_WAIT_TIMEOUT_SECONDS = 7200;

    /**
     * The order currently being processed by the builder.
     *
     * @var OrderInterface|null
     */
    protected ?OrderInterface $order = null;

    public function __construct(
        protected LoyaltyJobBuilder $jobBuilder,
        protected RequestTypePool $requestTypePool,
        protected StoreManagerInterface $storeManager,
        protected Connector $connector,
        protected CustomerContactLink $customerContactLink,
        protected RuleRepositoryInterface $ruleRepository,
        protected AppliedGiftCardRepositoryInterface $appliedGiftCardRepository,
        protected OrderLeatBalanceRepositoryInterface $leatBalanceRepository,
        protected AppliedCouponsManager $appliedCouponsManager,
        protected QuoteItemExtensionAttributes $quoteItemExtensionAttributes,
        protected SalesRuleExtensionAttributes $salesRuleExtensionAttributes,
        protected Json $json,
        protected DateTime $dateTime
    ) {
    }

    /**
     * Creates a queued job for processing the given order in Leat.
     *
     * The method:
     * - resolves the customer contact UUID and business profile UUID,
     * - creates a new job for the order's customer,
     * - builds the order payload,
     * - adds a `CreateAndProcess` request to the job,
     * - returns the created job.
     *
     * @param OrderInterface $order Order to convert into a queue job.
     * @return JobInterface|null The created job, or null when required context is missing.
     */
    public function addTransactionJob(OrderInterface $order): ?JobInterface
    {
        $context = $this->resolveOrderContext($order);
        if (!$context) {
            return null;
        }

        $jobBuilder = $this->jobBuilder
            ->newJob($order->getCustomerId())
            ->setStoreId($context['store_id']);

        $this->setOrder($order);
        $orderData = $this->buildOrderPayload($order, $context['contact_uuid'], $context['shop_uuid']);
        $this->setOrder(null);

        $jobBuilder->addRequest([
            CreateAndProcess::DATA_ORDER_KEY => $orderData,
        ], CreateAndProcess::getTypeCode());

        return $jobBuilder->create();
    }

    /**
     * Resolves the execution context required to export an order.
     *
     * The context contains:
     * - the customer's contact UUID,
     * - the shop UUID for the order store,
     * - the store ID.
     *
     * If either the contact UUID or shop UUID is missing, the order cannot be exported.
     *
     * @param OrderInterface $order Order to inspect.
     * @return array<string, string|int>|null
     */
    protected function resolveOrderContext(OrderInterface $order): ?array
    {
        $contactUUID = $this->customerContactLink->getContactUuid($order->getCustomerId());
        if (!$contactUUID) {
            return null;
        }

        $storeId = (int) $order->getStoreId();
        $shopUuid = $this->connector->getConfig()->getShopUuid($storeId);
        if (!$shopUuid) {
            return null;
        }

        return ['contact_uuid' => $contactUUID, 'shop_uuid' => $shopUuid, 'store_id' => $storeId];
    }

    /**
     * Builds the full order payload expected by the Leat API.
     *
     * The payload includes:
     * - order identity and status information,
     * - pricing totals,
     * - customer and business profile UUIDs,
     * - line items,
     * - optional discounts, charges, payments, and timestamps.
     *
     * @param OrderInterface $order Magento order being exported.
     * @param string $contactUUID Leat contact UUID.
     * @param string $shopUuid Leat business profile UUID.
     * @return array<string, mixed>
     */
    protected function buildOrderPayload(
        OrderInterface $order,
        string $contactUUID,
        string $shopUuid,
    ): array {
        $storeId = (int) $order->getStoreId();
        $currency = $this->storeManager->getStore($storeId)->getBaseCurrencyCode();

        $lineItems = $this->getOrderItems($order);

        $data = [
            'external_identifier' => $this->getExternalIdentifier(),
            'reference' => $order->getIncrementId(),
            'status' => $this->getStatus($order),
            'payment_status' => $this->getPaymentStatus($order),
            'total_order_amount' => $this->getPrice($order->getBaseGrandTotal()),
            'order_amount' => $this->calculateOrderAmount($lineItems),
            'total_discount_amount' => $this->getOrderDiscountAmount($order),
            'currency' => $currency,
            'contact' => ['uuid' => $contactUUID],
            'business_profile' => ['uuid' => $shopUuid],
            'line_items' => $lineItems,
        ];

        $appliedDiscounts = $this->getAppliedDiscounts($order);
        if (!empty($appliedDiscounts)) {
            $data['applied_discounts'] = $appliedDiscounts;
        }

        $charges = $this->getCharges($order);
        if (!empty($charges)) {
            $data['charges'] = $charges;
        }

        $payments = $this->getPayments($order);
        if (!empty($payments)) {
            $data['payments'] = $payments;
        }

        $paidAt = $this->getPaidAt($order);
        if ($paidAt) {
            $data['paid_at'] = $paidAt;
        }

        $completedAt = $this->getCompletedAt($order);
        if ($completedAt) {
            $data['completed_at'] = $completedAt;
        }

        return $data;
    }

    /**
     * Calculates the total amount of all line items in minor currency units.
     *
     * This includes sub line items when present.
     *
     * @param array<int, array<string, mixed>> $lineItems
     * @return int
     */
    protected function calculateOrderAmount(array $lineItems): int
    {
        $sum = 0;
        foreach ($lineItems as $lineItem) {
            $sum += $lineItem['total_amount'] ?? 0;
            foreach ($lineItem['sub_line_items'] ?? [] as $subLineItem) {
                $sum += $subLineItem['total_amount'] ?? 0;
            }
        }

        return $sum;
    }

    /**
     * Calculates the total discount amount for the order in minor currency units.
     *
     * @param Order $order Order to inspect.
     * @return int
     */
    protected function getOrderDiscountAmount(Order $order): int
    {
        $discountInclTax = abs((float) $order->getBaseDiscountAmount());
        $discountTax = abs((float) $order->getBaseDiscountTaxCompensationAmount());
        return $this->getPrice($discountInclTax - $discountTax);
    }

    /**
     * Builds the exportable line items for the given order.
     *
     * Items without a product or dummy items are skipped by `filterOrderItems()`.
     *
     * @param OrderInterface $order Order to convert into line items.
     * @return array<int, array<string, mixed>>
     */
    protected function getOrderItems(OrderInterface $order): array
    {
        $orderItems = [];
        foreach ($this->filterOrderItems($order) as $orderItem) {
            $product = $orderItem->getProduct();
            if (!$product) {
                continue;
            }

            $orderItems[] = $this->buildLineItemForProduct($product, $orderItem);
        }

        return $orderItems;
    }

    /**
     * Builds the shared core data for a line item.
     *
     * @param OrderItemInterface $orderItem Order item being exported.
     * @param int $quantity Ordered quantity.
     * @param int $totalAmount Total amount in minor units.
     * @param int $discountAmount Discount amount in minor units.
     * @param string|null $reason Optional reason field.
     * @return array<string, mixed>
     */
    protected function buildLineItemCore(
        OrderItemInterface $orderItem,
        int $quantity,
        int $totalAmount,
        int $discountAmount,
        ?string $reason = null
    ): array {
        $item = [
            'external_identifier' => $this->getOrderItemExternalIdentifier($orderItem),
            'quantity' => $quantity,
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
        ];

        if ($reason !== null) {
            $item['reason'] = $reason;
        }

        return $item;
    }

    /**
     * Builds a line item payload for a product-backed order item.
     *
     * @param Product $product Magento product instance.
     * @param OrderItemInterface $orderItem Order item linked to the product.
     * @return array<string, mixed>
     */
    protected function buildLineItemForProduct(Product $product, OrderItemInterface $orderItem): array
    {
        $totalAmount = $this->getPrice($orderItem->getBaseRowTotal() - $this->getOrderItemDiscountAmount($orderItem, false));
        $discountAmount = $this->getPrice($this->getOrderItemDiscountAmount($orderItem));

        $lineItem = $this->buildLineItemCore($orderItem, (int) $orderItem->getQtyOrdered(), $totalAmount, $discountAmount) + [
                'name' => $product->getName(),
                'price' => $this->getPrice($orderItem->getBaseOriginalPrice()),
                'product' => [
                    'external_identifier' => $product->getSku(),
                ],
            ];

        $subLineItems = $this->getSubLineItems($orderItem);
        if (!empty($subLineItems)) {
            $lineItem['sub_line_items'] = $subLineItems;
        }

        $giftcardTransactionUuid = $orderItem->getData('leat_giftcard_object_uuid');
        if ($giftcardTransactionUuid) {
            $lineItem['offer'] = [
                'uuid' => $giftcardTransactionUuid,
                'type' => 'GIFTCARD_TRANSACTION',
            ];
        }

        return $lineItem;
    }

    /**
     * Calculates the discount amount for a single order item.
     *
     * When `$includeGift` is true and the quote item has Leat extension attributes,
     * the discount is derived from the difference between base row total and original
     * price times ordered quantity.
     *
     * @param OrderItemInterface $orderItem Order item to inspect.
     * @param bool $includeGift Whether gift logic should be applied.
     * @return float
     */
    protected function getOrderItemDiscountAmount(OrderItemInterface $orderItem, bool $includeGift = true): float
    {
        if ($includeGift && $this->quoteItemExtensionAttributes->getByItemId((int) $orderItem->getQuoteItemId())) {
            return abs($orderItem->getBaseRowTotal() - ($orderItem->getBaseOriginalPrice() * $orderItem->getQtyOrdered()));
        }

        $discountInclTax = abs((float) $orderItem->getBaseDiscountAmount());
        $discountTax = abs((float) $orderItem->getBaseDiscountTaxCompensationAmount());
        return abs($discountInclTax - $discountTax);
    }

    /**
     * Returns additional sub line items for an order item.
     *
     * The base implementation returns an empty list, but child classes may override this.
     *
     * @param OrderItemInterface $orderItem Order item to inspect.
     * @return array<int, array<string, mixed>>
     */
    protected function getSubLineItems(OrderItemInterface $orderItem): array
    {
        return [];
    }

    /**
     * Builds the combined list of applied discounts for an order.
     *
     * This merges Leat-originated discounts with Magento rule discounts and normalizes
     * them into the format expected by the Leat API.
     *
     * @param Order $order Order to inspect.
     * @return array<int, array<string, mixed>>
     */
    protected function getAppliedDiscounts(Order $order): array
    {
        $leatDiscounts = $this->getLeatDiscountsForOrder($order);
        $magentoDiscounts = $this->getMagentoDiscountsForOrder($order, array_keys($leatDiscounts));
        if (empty($leatDiscounts) && empty($magentoDiscounts)) {
            return [];
        }

        $leatFlat = array_merge(...array_values($leatDiscounts));
        $magentoFlat = array_merge(...array_values($magentoDiscounts));
        $discounts = array_merge($leatFlat, $magentoFlat);

        $result = [];
        foreach ($discounts as $discount) {
            $simpleAction = $discount['_simple_action'] ?? null;
            $applyToShipping = $discount['_apply_to_shipping'] ?? false;
            $ruleDiscountAmount = $discount['_rule_discount_amount'] ?? 0;
            $ruleId = $discount['_rule_id'] ?? null;
            unset($discount['_simple_action'], $discount['_rule_discount_amount'], $discount['_apply_to_shipping'], $discount['_rule_id']);

            $type = $this->getDiscountType($simpleAction);
            if ($type !== null) {
                $discount['type'] = $type;
            }

            $value = $this->getDiscountValue($simpleAction, $ruleDiscountAmount);
            if ($value !== null) {
                $discount['value'] = (int) $value;
            }

            $discount['applied_to'] = $discount['applied_to'] ?? $this->getDiscountAppliedTo($simpleAction);

            $lineItems = $discount['line_items'] ?? $this->getDiscountLineItems($order, $ruleId);
            if (!empty($lineItems)) {
                $discount['line_items'] = $lineItems;
            }

            if ($applyToShipping) {
                $discount['charges'] = [
                    ['external_identifier' => $this->getExternalIdentifier(sprintf('%s_SHIPPING', $order->getShippingMethod()))],
                ];
            }

            $result[] = $discount;
        }

        return $result;
    }

    /**
     * Returns Magento rule discounts that are not already represented as Leat discounts.
     *
     * @param Order $order Order to inspect.
     * @param array<int, int|string> $leatDiscountRuleIds Rule IDs already represented by Leat discounts.
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function getMagentoDiscountsForOrder(Order $order, array $leatDiscountRuleIds): array
    {
        $discounts = [];
        $appliedRuleIds = $order->getAppliedRuleIds();
        if (!$appliedRuleIds) {
            return [];
        }

        $ruleIds = explode(',', $appliedRuleIds);
        $ruleIds = array_unique(array_filter($ruleIds));
        $ruleIds = array_diff($ruleIds, $leatDiscountRuleIds);
        if (empty($ruleIds)) {
            return [];
        }

        $baseDiscountAmounts = $this->getBaseDiscountAmounts($order);
        $discountDescriptions = $this->getDiscountDescriptions($order);

        foreach ($ruleIds as $ruleId) {
            $ruleId = (int) $ruleId;

            $totalAmount = 0.0;
            foreach ($baseDiscountAmounts as $ruleAmounts) {
                if (isset($ruleAmounts[$ruleId])) {
                    $totalAmount += (float) $ruleAmounts[$ruleId];
                }
            }

            $ruleDescription = $discountDescriptions[$ruleId] ?? [];
            $simpleAction = $ruleDescription['simple_action'] ?? null;
            $ruleDiscountAmount = isset($ruleDescription['rule_discount_amount'])
                ? (float) $ruleDescription['rule_discount_amount']
                : null;
            $applyToShipping = (bool) ($ruleDescription['apply_to_shipping'] ?? false);
            $label = $ruleDescription['label'] ?? null;

            $discounts[$ruleId][] = [
                'external_identifier' => $this->getExternalIdentifier(sprintf('salesrule_%s', $ruleId)),
                'name' => !empty($label) ? $label : sprintf('Discount #%s', $ruleId),
                'amount' => $this->getPrice(abs($totalAmount)),
                '_simple_action' => $simpleAction,
                '_rule_discount_amount' => $ruleDiscountAmount,
                '_apply_to_shipping' => $applyToShipping,
                '_rule_id' => $ruleId,
            ];
        }

        return $discounts;
    }

    /**
     * Returns the discounts created from Leat applied coupons on the order.
     *
     * The method attempts to deserialize the stored coupon data, resolve the
     * corresponding sales rule, and map gift line items when needed.
     *
     * @param Order $order Order to inspect.
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function getLeatDiscountsForOrder(Order $order): array
    {
        $appliedCouponsJson = $order->getData('leat_loyalty_applied_coupons');
        if (!$appliedCouponsJson) {
            return [];
        }

        try {
            $appliedCoupons = $this->json->unserialize($appliedCouponsJson);
        } catch (\Exception $e) {
            return [];
        }

        if (!is_array($appliedCoupons) || empty($appliedCoupons)) {
            return [];
        }

        $baseDiscountAmounts = $this->getBaseDiscountAmounts($order);
        $discountDescriptions = $this->getDiscountDescriptions($order);

        $giftQuoteLineItems = [];
        $quoteItemExtensionAttributes = $this->quoteItemExtensionAttributes->getByQuoteId((int) $order->getQuoteId());
        $quoteItemMappedOrderItems = $this->mapOrderItemsByQuoteItemId($order);
        /** @var ExtensionAttributes $extensionAttribute */
        foreach ($quoteItemExtensionAttributes as $extensionAttribute) {
            if ($extensionAttribute['is_gift'] ?? false && isset($quoteItemMappedOrderItems[$extensionAttribute['item_id']])) {
                $giftQuoteLineItems[$extensionAttribute['gift_rule_id']][] = $quoteItemMappedOrderItems[$extensionAttribute['item_id']];
            }
        }

        $discounts = [];
        foreach ($appliedCoupons as $rewardUuid => $offerUuids) {
            try {
                $ruleId = $this->salesRuleExtensionAttributes->getSalesRuleIdByRewardUuid((string) $rewardUuid);
            } catch (\Exception $e) {
                $ruleId = null;
            }

            $totalAmount = 0.0;
            if ($ruleId) {
                foreach ($baseDiscountAmounts as $taxRate => $ruleAmounts) {
                    if (isset($ruleAmounts[$ruleId])) {
                        $totalAmount += (float) $ruleAmounts[$ruleId];
                    }
                }
            }

            $ruleDescription = $ruleId ? ($discountDescriptions[$ruleId] ?? []) : [];
            $simpleAction = $ruleDescription['simple_action'] ?? null;
            $ruleDiscountAmount = isset($ruleDescription['rule_discount_amount'])
                ? (float) $ruleDescription['rule_discount_amount']
                : null;
            $applyToShipping = (bool) ($ruleDescription['apply_to_shipping'] ?? false);

            $lineItems = [];
            $originalPrice = 0.0;
            if (!empty($giftQuoteLineItems[$ruleId])) {
                foreach ($giftQuoteLineItems[$ruleId] as $orderItem) {
                    $lineItems[] = [
                        'external_identifier' => $this->getOrderItemExternalIdentifier($orderItem),
                    ];
                    $originalPrice += $orderItem->getBaseOriginalPrice() - $orderItem->getBaseDiscountAmount();
                }
            }

            if ($totalAmount == 0) {
                $totalAmount = $originalPrice;
            }

            foreach ($offerUuids as $offerUuid) {
                $entry = [
                    'external_identifier' => $this->getExternalIdentifier(sprintf('%s_salesrule_%s', $offerUuid, $ruleId)),
                    'name' => 'Loyalty Reward',
                    'amount' => $this->getPrice(abs($totalAmount)),
                    'applied_to' => empty($lineItems) ? $this->getDiscountAppliedTo($simpleAction) : 'ORDER_LINES',
                    'offer' => [
                        'uuid' => $offerUuid,
                        'type' => 'COLLECTABLE_REWARD_REDEMPTION',
                    ],
                    '_rule_discount_amount' => $ruleDiscountAmount,
                    '_simple_action' => $simpleAction,
                    '_apply_to_shipping' => $applyToShipping,
                    '_rule_id' => $ruleId,
                ];

                if (!empty($lineItems)) {
                    $entry['line_items'] = $lineItems;
                }

                $discounts[$ruleId][] = $entry;
            }
        }

        return $discounts;
    }

    /**
     * Builds a map of order items by quote item ID.
     *
     * @param Order $order Order to inspect.
     * @return array<int, OrderItemInterface>
     */
    protected function mapOrderItemsByQuoteItemId(Order $order): array
    {
        $items = [];
        foreach ($this->filterOrderItems($order) as $orderItem) {
            $items[$orderItem->getQuoteItemId()] = $orderItem;
        }
        return $items;
    }

    /**
     * Returns the base discount amounts array stored on the order.
     *
     * The value is expected to be JSON-encoded and keyed by tax rate or other
     * internal grouping used by the integration.
     *
     * @param Order $order Order to inspect.
     * @return array<mixed>
     */
    protected function getBaseDiscountAmounts(Order $order): array
    {
        $baseDiscountAmounts = [];
        $baseAmountJson = $order->getData('base_discount_amount_array');
        if ($baseAmountJson) {
            try {
                $parsed = $this->json->unserialize($baseAmountJson);
                if (is_array($parsed)) {
                    $baseDiscountAmounts = $parsed;
                }
            } catch (\Exception $e) {
                // ignore — amounts fall back to 0
            }
        }
        return $baseDiscountAmounts;
    }

    /**
     * Builds a rule-to-line-item map for order items that carry applied rule IDs.
     *
     * @param Order $order Order to inspect.
     * @return array<int, array<int, array<string, string>>>
     */
    protected function buildRuleLineItemsMap(Order $order): array
    {
        $map = [];
        foreach ($this->filterOrderItems($order) as $orderItem) {
            $appliedRuleIds = $orderItem->getAppliedRuleIds();
            if (!$appliedRuleIds) {
                continue;
            }
            foreach (explode(',', $appliedRuleIds) as $ruleId) {
                $ruleId = (int) trim($ruleId);
                if ($ruleId) {
                    $map[$ruleId][] = ['external_identifier' => $this->getOrderItemExternalIdentifier($orderItem)];
                }
            }
        }
        return $map;
    }

    /**
     * Returns the order's stored discount descriptions.
     *
     * The data is expected to be JSON-encoded and indexed by rule ID.
     *
     * @param Order $order Order to inspect.
     * @return array<mixed>
     */
    protected function getDiscountDescriptions(Order $order): array
    {
        $discountDescriptions = [];
        $descJson = $order->getData('discount_description_array');
        if ($descJson) {
            try {
                $parsed = $this->json->unserialize($descJson);
                if (is_array($parsed)) {
                    $discountDescriptions = $parsed;
                }
            } catch (\Exception $e) {
                // fall back to empty
            }
        }

        return $discountDescriptions;
    }

    /**
     * Converts a Magento sales rule action into the Leat discount type.
     *
     * @param string|null $simpleAction Magento simple action code.
     * @return string|null
     */
    public function getDiscountType(?string $simpleAction): ?string
    {
        return match ($simpleAction) {
            'by_percent', 'add_gift_products' => 'PERCENTAGE',
            'by_fixed', 'cart_fixed' => 'ABSOLUTE',
            default => null,
        };
    }

    /**
     * Converts a sales rule action and rule discount amount into a value suitable for Leat.
     *
     * @param string|null $simpleAction Magento simple action code.
     * @param float|null $ruleDiscountAmount Rule discount amount.
     * @return float|null
     */
    public function getDiscountValue(?string $simpleAction, ?float $ruleDiscountAmount): ?float
    {
        if ($ruleDiscountAmount === null) {
            return null;
        }

        return match ($simpleAction) {
            'by_percent', 'by_fixed', 'cart_fixed' => $this->getPrice($ruleDiscountAmount),
            default => $ruleDiscountAmount,
        };
    }

    /**
     * Returns the target scope for the discount in the Leat payload.
     *
     * @param string|null $simpleAction Magento simple action code.
     * @return string
     */
    public function getDiscountAppliedTo(?string $simpleAction): string
    {
        return 'ORDER_LINES';
    }

    /**
     * Returns the line items that should be associated with a discount.
     *
     * @param Order $order Order to inspect.
     * @param int|null $ruleId Sales rule ID.
     * @return array<int, array<string, string>>
     */
    protected function getDiscountLineItems(Order $order, ?int $ruleId): array
    {
        $lineItemMapping = $this->buildRuleLineItemsMap($order);
        return $ruleId && isset($lineItemMapping[$ruleId]) ? $lineItemMapping[$ruleId] : [];
    }

    /**
     * Builds the charge payloads for the order.
     *
     * Depending on store configuration, shipping and tax may be exported as
     * separate charges. The method returns an empty list when no charge applies.
     *
     * @param OrderInterface $order Order to inspect.
     * @return array<int, array<string, mixed>>
     */
    protected function getCharges(OrderInterface $order): array
    {
        $charges = [];
        $config = $this->connector->getConfig();
        $storeId = (int) $order->getStoreId();

        $shippingAmount = (float) $order->getBaseShippingAmount();
        if ($shippingAmount > 0 && $config->getIncludeShippingAsCharge($storeId)) {
            $separateShippingTax = $config->getShippingTaxSeparate($storeId);
            $includeTaxAsCharge = $config->getIncludeTaxAsCharge($storeId);

            $shippingTaxAmount = (float) $order->getBaseShippingTaxAmount();
            $shippingTotal = $separateShippingTax && $includeTaxAsCharge
                ? $shippingAmount
                : $shippingAmount + $shippingTaxAmount;

            $charges[] = [
                'external_identifier' => $this->getExternalIdentifier(sprintf('%s_SHIPPING', $order->getShippingMethod())),
                'name' => sprintf('Shipping %s', $order->getShippingMethod()),
                'amount' => $this->getPrice($shippingAmount),
                'total_amount' => $this->getPrice($shippingTotal),
                'discount_amount' => $this->getPrice($order->getBaseShippingDiscountAmount()),
                'charge_definition' => [
                    'external_identifier' => 'SHIPPING',
                ],
            ];

            if ($separateShippingTax && $shippingTaxAmount > 0 && $includeTaxAsCharge) {
                $charges[] = [
                    'external_identifier' => $this->getExternalIdentifier(sprintf('%s_SHIPPING_TAX', $order->getShippingMethod())),
                    'name' => 'Shipping Tax',
                    'amount' => $this->getPrice($shippingTaxAmount),
                    'total_amount' => $this->getPrice($shippingTaxAmount),
                    'charge_definition' => [
                        'external_identifier' => 'TAX',
                    ],
                ];
            }
        }

        $taxAmount = (float) $order->getBaseTaxAmount();
        if ($taxAmount > 0 && $config->getIncludeTaxAsCharge($storeId)) {
            $separateShippingTax = $config->getShippingTaxSeparate($storeId);
            $includeShipping = $config->getIncludeShippingAsCharge($storeId);

            $shippingTaxAmount = (float) $order->getBaseShippingTaxAmount();
            $lineTaxAmount = ($separateShippingTax && $includeShipping)
                ? $taxAmount - $shippingTaxAmount
                : $taxAmount;

            if ($lineTaxAmount > 0) {
                $charges[] = [
                    'external_identifier' => $this->getExternalIdentifier('ORDER_TAX'),
                    'name' => 'Tax',
                    'amount' => $this->getPrice($taxAmount - ($separateShippingTax && $includeShipping ? $shippingTaxAmount : 0)),
                    'total_amount' => $this->getPrice($lineTaxAmount),
                    'charge_definition' => [
                        'external_identifier' => 'TAX',
                    ],
                ];
            }
        }

        return $charges;
    }

    /**
     * Builds payment payload entries from gift card and prepaid transactions.
     *
     * @param OrderInterface $order Order to inspect.
     * @return array<int, array<string, string>>
     */
    protected function getPayments(OrderInterface $order): array
    {
        $payments = [];

        $giftCards = $this->appliedGiftCardRepository->getByOrderId((int) $order->getId());
        foreach ($giftCards as $giftCard) {
            $transactionUuid = $giftCard->getLeatTransactionUuid();
            if ($transactionUuid) {
                $payments[] = [
                    'uuid' => $transactionUuid,
                    'type' => 'GIFTCARD_TRANSACTION',
                ];
            }
        }

        $prepaidUuid = $this->leatBalanceRepository->getPrepaidTransactionUuid($order);
        if ($prepaidUuid) {
            $payments[] = [
                'uuid' => $prepaidUuid,
                'type' => 'PREPAID_TRANSACTION',
            ];
        }

        return $payments;
    }

    /**
     * Returns the payment timestamp for the order if an invoice exists.
     *
     * @param OrderInterface $order Order to inspect.
     * @return string|null
     */
    protected function getPaidAt(OrderInterface $order): ?string
    {
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        if ($invoice->hasData('created_at')) {
            return $this->dateTime->gmtDate(\DateTime::ATOM, $invoice->getCreatedAt());
        }

        return null;
    }

    /**
     * Returns the completion timestamp for completed orders.
     *
     * @param OrderInterface $order Order to inspect.
     * @return string|null
     */
    protected function getCompletedAt(OrderInterface $order): ?string
    {
        if ($this->getStatus($order) === 'COMPLETED') {
            return $this->dateTime->gmtDate(\DateTime::ATOM, $order->getUpdatedAt());
        }

        return null;
    }

    /**
     * Filters out order items that should not be exported.
     *
     * This excludes:
     * - dummy order items,
     * - Leat gift card items when the store configuration says to exclude them.
     *
     * @param Order $order Order to inspect.
     * @return array<int, OrderItemInterface>
     */
    protected function filterOrderItems(Order $order): array
    {
        $filterGiftcardProducts = !$this->connector->getConfig()->getGiftcardPointExclusionStatus((int) $order->getStoreId());
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
     * Returns true when the order contains giftcard items that have not yet received a
     * transaction UUID from the Leat API (i.e. GiftcardTransaction queue job not yet run).
     *
     * Used by OrderExport to defer exporting until giftcard processing is complete so that
     * the UUID can be attached to the line item offer.
     *
     * @param OrderInterface $order Order to inspect.
     * @return bool
     */
    public function hasUnprocessedGiftcardItems(OrderInterface $order): bool
    {
        $storeId = (int) $order->getStoreId();
        if (!$this->connector->getConfig()->isGiftcardEnabled($storeId)) {
            return false;
        }

        $orderCreatedAt = strtotime((string) $order->getCreatedAt());
        if ($orderCreatedAt && (time() - $orderCreatedAt) > self::GIFTCARD_UUID_WAIT_TIMEOUT_SECONDS) {
            return false;
        }

        foreach ($order->getItems() as $orderItem) {
            $product = $orderItem->getProduct();
            if (!$product || $orderItem->isDummy()) {
                continue;
            }

            if ($this->isLeatGiftcard($product) && !$orderItem->getData('leat_giftcard_object_uuid')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the external identifier for a single order item.
     *
     * @param OrderItemInterface $orderItem Order item to inspect.
     * @return string
     */
    protected function getOrderItemExternalIdentifier(OrderItemInterface $orderItem): string
    {
        return $this->getExternalIdentifier($orderItem->getSku());
    }

    /**
     * Builds the external identifier used for orders and nested items.
     *
     * @param string|null $suffix Optional suffix to append.
     * @param string|null $prefix Optional prefix value.
     * @return string
     */
    protected function getExternalIdentifier(?string $suffix = null, ?string $prefix = null): string
    {
        $incrementIdPrefix = $prefix ?? $this->getOrder()->getStoreId();
        if ($suffix) {
            return sprintf('%s_%s_%s', $incrementIdPrefix, $this->getOrder()->getIncrementId(), $suffix);
        }

        return sprintf('%s_%s', $incrementIdPrefix, $this->getOrder()->getIncrementId());
    }

    /**
     * Determines whether the given product is marked as a Leat gift card product.
     *
     * @param ProductInterface $product Product to inspect.
     * @return bool
     */
    protected function isLeatGiftcard(ProductInterface $product): bool
    {
        $giftcardAttrValue = $product->getData(AddLeatGiftcardAttribute::GIFTCARD_ATTRIBUTE_CODE);
        return $giftcardAttrValue && $giftcardAttrValue !== '0';
    }

    /**
     * Converts a value into minor currency units.
     *
     * @param mixed $value Monetary value.
     * @return int
     */
    public function getPrice(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    /**
     * Converts a Magento order state into the Leat order status value.
     *
     * @param OrderInterface|Order $order Order to inspect.
     * @return string
     */
    public function getStatus(OrderInterface|Order $order): string
    {
        return match ($order->getState()) {
            Order::STATE_COMPLETE => 'COMPLETED',
            Order::STATE_CANCELED => 'CANCELLED',
            Order::STATE_PENDING_PAYMENT, Order::STATE_NEW => 'CREATED',
            default => 'PENDING',
        };
    }

    /**
     * Determines the payment status used in the Leat payload.
     *
     * @param OrderInterface|Order $order Order to inspect.
     * @return string
     */
    public function getPaymentStatus(OrderInterface|Order $order): string
    {
        $state = $order->getState();

        if ($state === Order::STATE_COMPLETE || $order->hasInvoices()) {
            return 'PAID';
        }

        if ($state === Order::STATE_PENDING_PAYMENT) {
            return 'PENDING';
        }

        return 'UNPAID';
    }

    /**
     * Returns the order currently being processed.
     *
     * @return ?OrderInterface
     */
    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    /**
     * Stores the current order for use by helper methods.
     *
     * @param OrderInterface|null $order Order to store, or null to clear the current order.
     * @return void
     */
    public function setOrder(?OrderInterface $order): void
    {
        $this->order = $order;
    }
}
