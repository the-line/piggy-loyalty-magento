<?php

declare(strict_types=1);

namespace Leat\LoyaltyAsync\Model\Queue\Builder\Service;

use Leat\AsyncQueue\Api\Data\JobInterface;
use Leat\LoyaltyAsync\Model\Queue\Type\Contact\Order\Return\CreateAndProcess;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use \Piggy\Api\Models\Orders\Order as LeatOrder;

/**
 * Builds async queue jobs and payloads for order return exports.
 *
 * This class extends {@see OrderApiBuilder} and reuses its order-context and
 * order-item mapping logic to construct return-specific payloads from Magento
 * credit memos.
 *
 * The generated payload includes:
 * - a return external identifier,
 * - the return status,
 * - the associated original order identifier,
 * - optional return line items,
 * - optional positive adjustments,
 * - optional return-level discounts,
 * - optional shipping/tax charges.
 */
class ReturnApiBuilder extends OrderApiBuilder
{
    private ?LeatOrder $leatOrder = null;

    /**
     * Creates a queued job for processing a return against the given order.
     *
     * The method resolves the order context, builds the return payload, and
     * attaches it to a `CreateAndProcess` request for the async queue.
     *
     * @param OrderInterface $order Original order associated with the credit memo.
     * @param CreditmemoInterface $creditmemo Credit memo representing the return.
     * @return JobInterface|null The created job, or null if the order context cannot be resolved.
     */
    public function addReturnJob(OrderInterface $order, CreditmemoInterface $creditmemo): ?JobInterface
    {
        $context = $this->resolveOrderContext($order);
        if (!$context) {
            return null;
        }

        $jobBuilder = $this->jobBuilder
            ->newJob($order->getCustomerId())
            ->setStoreId($context['store_id']);

        $this->setOrder($order);
        $this->getLeatOrder();

        /** @var Order $order */
        $returnData = $this->buildReturnPayload($order, $creditmemo);

        $this->setLeatOrder(null);
        $this->setOrder(null);

        $jobBuilder->addRequest([
            CreateAndProcess::DATA_RETURN_KEY => $returnData,
        ], CreateAndProcess::getTypeCode());

        return $jobBuilder->create();
    }

    /**
     * Builds the return payload expected by Leat.
     *
     * The payload is derived from the original order and its credit memo and may
     * include optional adjustment line items, discounts, and charges.
     *
     * @param Order $order Original Magento order.
     * @param CreditmemoInterface $creditmemo Credit memo to export.
     * @return array<string, mixed>
     */
    protected function buildReturnPayload(Order $order, CreditmemoInterface $creditmemo): array
    {
        $data = [
            'external_identifier' => $this->getReturnExternalIdentifier($creditmemo),
            'status' => $this->getReturnStatus($creditmemo),
            'order' => ['external_identifier' => $this->getExternalIdentifier()],
        ];

        $lineItems = $this->getReturnLineItems($order, $creditmemo);

        $storeId = (int) $order->getStoreId();
        $adjustmentPositive = (float) $creditmemo->getBaseAdjustmentPositive();
        if ($adjustmentPositive > 0 && $this->connector->getConfig()->getAdjustmentPositiveExportEnabled($storeId)) {
            $existingExtIds = array_column($lineItems, 'external_identifier');
            $adjustmentLineItems = $this->buildAdjustmentLineItems($adjustmentPositive, $existingExtIds);
            $lineItems = array_merge($lineItems, $adjustmentLineItems);
        }

        if (!empty($lineItems)) {
            $data['line_items'] = $lineItems;
        }

        $appliedDiscounts = $this->getReturnAppliedDiscounts($order, $creditmemo);
        if (!empty($appliedDiscounts)) {
            $data['applied_discounts'] = $appliedDiscounts;
        }

        $charges = $this->getReturnCharges($order, $creditmemo);
        if (!empty($charges)) {
            $data['charges'] = $charges;
        }

        return $data;
    }

    /**
     * Builds additional line items to represent a positive adjustment amount.
     *
     * Existing external identifiers are excluded so the adjustment does not
     * duplicate already exported line items.
     *
     * @param float $adjustmentAmount Positive adjustment amount in major currency units.
     * @param array<int, string> $existingExtIds External identifiers already present in the return payload.
     * @return array<int, array<string, mixed>>
     */
    protected function buildAdjustmentLineItems(float $adjustmentAmount, array $existingExtIds = []): array
    {
        $leatOrder = $this->getLeatOrder();
        if (empty($leatOrder->getLineItems())) {
            throw new \Exception('No line items found for the Leat order');
        }

        $remaining = $this->getPrice($adjustmentAmount);
        $lineItems = [];

        foreach ($leatOrder->getLineItems() as $leatLineItem) {
            if ($remaining <= 0) {
                break;
            }

            if (in_array($leatLineItem->getExternalIdentifier(), $existingExtIds, true)) {
                continue;
            }

            $available = (int) $leatLineItem->getTotalAmount();
            $amount = min($available, $remaining);

            if ($amount <= 0) {
                continue;
            }

            $lineItems[] = [
                'external_identifier' => $leatLineItem->getExternalIdentifier(),
                'quantity' => 0,
                'total_amount' => $amount,
                'discount_amount' => 0,
            ];

            $remaining -= $amount;
        }

        // If $remaining > 0 here, the adjustment exceeds the total of all Piggy line items.
        // Partial distribution is shipped as-is; the Piggy API will receive what could be mapped.

        return $lineItems;
    }

    /**
     * Builds line items for a credit memo by matching its items to original order items.
     *
     * When a matching Leat line item is found, amounts are taken from Leat and scaled
     * proportionally by (creditmemo qty / leat qty) to respect partial returns.
     * Falls back to creditmemo-derived amounts when no Leat line item matches.
     *
     * @param Order $order Original order.
     * @param CreditmemoInterface $creditmemo Credit memo being exported.
     * @return array<int, array<string, mixed>>
     */
    protected function getReturnLineItems(Order $order, CreditmemoInterface $creditmemo): array
    {
        $orderItemsById = $this->mapOrderItemsById($order);

        $leatLineItemsByExtId = [];
        foreach ($this->getLeatOrder()->getLineItems() as $leatLineItem) {
            $leatLineItemsByExtId[$leatLineItem->getExternalIdentifier()] = $leatLineItem;
        }

        $lineItems = [];

        foreach ($creditmemo->getItems() as $creditmemoItem) {
            if ((float) $creditmemoItem->getQty() <= 0) {
                $this->connector->getLogger('order-returns')->log(sprintf(
                    'Skipping credit memo item with non-positive quantity: %s (qty: %s)',
                    $creditmemoItem->getId(),
                    $creditmemoItem->getQty()
                ));
                continue;
            }

            $orderItem = $orderItemsById[$creditmemoItem->getOrderItemId()] ?? null;
            if (!$orderItem) {
                $this->connector->getLogger('order-returns')->log(sprintf(
                    'Credit memo item %s has no matching order item; skipping.',
                    $creditmemoItem->getId()
                ));
                continue;
            }

            $returnQty = (int) $creditmemoItem->getQty();
            $discountAmount = $this->getCreditmemoItemDiscountAmount($creditmemoItem);
            $totalAmount = $this->getPrice($creditmemoItem->getBaseRowTotal()) - $discountAmount;

            $leatLineItem = $leatLineItemsByExtId[$this->getOrderItemExternalIdentifier($orderItem)] ?? null;
            $leatItemQty = (int) ($leatLineItem?->getQuantity() ?? 0);
            $leatItemTotalAmount = (int) ($leatLineItem?->getTotalAmount() ?? 0);
            $leatItemDiscountAmount = (int) ($leatLineItem?->getDiscountAmount() ?? 0);

            if ($leatItemQty <= 0 || $leatItemTotalAmount <= 0 && $leatItemDiscountAmount <= 0) {
                $this->connector->getLogger('order-returns')->log(sprintf(
                    'No value left to subtract from Leat line item for order item ID: %s | external_identifier: %s',
                    $orderItem->getItemId(),
                    $this->getOrderItemExternalIdentifier($orderItem)
                ));
                continue;
            }

            $lineItems[] = $this->buildLineItemCore(
                $orderItem,
                min($returnQty, $leatItemQty),
                min($totalAmount, $leatItemTotalAmount),
                min($discountAmount, $leatItemDiscountAmount)
            );
        }

        return $lineItems;
    }

    /**
     * Calculates the discount amount for a credit memo item in minor currency units.
     *
     * @param CreditmemoItemInterface $item Credit memo item to inspect.
     * @return int
     */
    protected function getCreditmemoItemDiscountAmount(CreditmemoItemInterface $item): int
    {
        $discountInclTax = abs((float) $item->getBaseDiscountAmount());
        $discountTax = abs((float) $item->getBaseDiscountTaxCompensationAmount());
        return $this->getPrice($discountInclTax - $discountTax);
    }

    /**
     * Returns return-level discount payloads mapped from the original Leat order's applied discounts,
     * with amounts proportionally distributed from the credit memo's actual discount amount.
     *
     * @param Order $order Original order.
     * @param CreditmemoInterface $creditmemo Credit memo being exported.
     * @return array<int, array<string, mixed>>
     */
    protected function getReturnAppliedDiscounts(Order $order, CreditmemoInterface $creditmemo): array
    {
        $totalReturnDiscount = $this->getPrice(abs((float) $creditmemo->getBaseDiscountAmount()));
        if ($totalReturnDiscount <= 0) {
            return [];
        }

        $leatOrder = $this->getLeatOrder();
        $appliedDiscounts = $leatOrder->getAppliedDiscounts();
        if (empty($appliedDiscounts)) {
            return [];
        }

        $totalLeatDiscount = array_sum(array_map(fn($d) => (int) $d->getAmount(), $appliedDiscounts));
        if ($totalLeatDiscount <= 0) {
            return [];
        }

        $discounts = [];
        foreach ($appliedDiscounts as $appliedDiscount) {
            $originalAmount = (int) $appliedDiscount->getAmount();
            if ($originalAmount <= 0) {
                continue;
            }

            $amount = (int) round($totalReturnDiscount * $originalAmount / $totalLeatDiscount);
            if ($amount <= 0) {
                continue;
            }

            $discounts[] = [
                'external_identifier' => $appliedDiscount->getExternalIdentifier(),
                'amount' => $amount,
            ];
        }

        return $discounts;
    }

    /**
     * Builds charge payload entries for the return from the original Leat order's charges,
     * using creditmemo amounts keyed by charge type suffix.
     *
     * @param Order $order Original order.
     * @param CreditmemoInterface $creditmemo Credit memo being exported.
     * @return array<int, array<string, mixed>>
     */
    protected function getReturnCharges(Order $order, CreditmemoInterface $creditmemo): array
    {
        $leatOrder = $this->getLeatOrder();
        $leatCharges = $leatOrder->getCharges();
        if (empty($leatCharges)) {
            return [];
        }

        $creditMemoAmounts = $this->getCreditMemoChargeAmounts($order, $creditmemo);

        $charges = [];
        foreach ($leatCharges as $leatCharge) {
            $extId = $leatCharge->getExternalIdentifier();
            $leatChargeAmount = $leatCharge->getTotalAmount();

            $matchedAmount = null;
            foreach ($creditMemoAmounts as $suffix => $amount) {
                if (str_ends_with($extId, '_' . $suffix)) {
                    $matchedAmount = $amount;
                    unset($creditMemoAmounts[$suffix]);
                    break;
                }
            }

            if ($matchedAmount === null || $matchedAmount <= 0) {
                continue;
            }

            $charges[] = [
                'external_identifier' => $extId,
                'total_amount' => min($matchedAmount, $leatChargeAmount)
            ];
        }

        return $charges;
    }

    /**
     * @param Order $order
     * @param CreditmemoInterface $creditmemo
     * @return array
     */
    protected function getCreditMemoChargeAmounts(Order $order, CreditmemoInterface $creditmemo): array
    {
        $separateShippingTax = $this->connector->getConfig()->getShippingTaxSeparate((int) $order->getStoreId());
        $includeShipping = $this->connector->getConfig()->getIncludeShippingAsCharge((int) $order->getStoreId());

        $taxAmount = (float) $creditmemo->getBaseTaxAmount();
        $shippingAmount = (float) $creditmemo->getBaseShippingAmount();
        $shippingTaxAmount = (float) $creditmemo->getBaseShippingTaxAmount();

        $creditmemoShippingAmount = $separateShippingTax
            ? $shippingAmount
            : $shippingAmount + $shippingTaxAmount;

        $creditmemoShippingTaxAmount = $separateShippingTax ? $shippingTaxAmount : 0;

        $creditmemoTaxAmount = ($separateShippingTax && $includeShipping)
            ? $taxAmount - $shippingTaxAmount
            : $taxAmount;

        return [
            'SHIPPING'     => $this->getPrice($creditmemoShippingAmount),
            'SHIPPING_TAX' => $this->getPrice($creditmemoShippingTaxAmount),
            'ORDER_TAX'          => $this->getPrice($creditmemoTaxAmount),
        ];
    }

    /**
     * Builds the external identifier for the return entity.
     *
     * @param CreditmemoInterface $creditmemo Credit memo being exported.
     * @return string
     */
    protected function getReturnExternalIdentifier(CreditmemoInterface $creditmemo): string
    {
        return $this->getExternalIdentifier(sprintf('RETURN_%s', $creditmemo->getIncrementId()));
    }

    /**
     * Returns the Leat status for a return.
     *
     * Returns a fixed value because return exports are considered completed once
     * the credit memo has been accepted for processing.
     *
     * @param CreditmemoInterface $creditmemo Credit memo being exported.
     * @return string
     */
    protected function getReturnStatus(CreditmemoInterface $creditmemo): string
    {
        return 'COMPLETED';
    }

    /**
     * Creates a lookup map of original order items keyed by item ID.
     *
     * @param Order $order Original order.
     * @return array<int, \Magento\Sales\Api\Data\OrderItemInterface>
     */
    protected function mapOrderItemsById(Order $order): array
    {
        $items = [];
        foreach ($this->filterOrderItems($order) as $orderItem) {
            $items[$orderItem->getItemId()] = $orderItem;
        }
        return $items;
    }

    /**
     * @return LeatOrder
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Piggy\Api\Exceptions\PiggyRequestException
     * @throws \Throwable
     */
    protected function getLeatOrder(): LeatOrder
    {
        if (isset($this->leatOrder)) {
            return $this->leatOrder;
        }

        try {
            $leatOrder = $this->connector->getConnection()->orders->find([
                'external_identifier' => $this->getExternalIdentifier(),
            ]);
            $this->setLeatOrder($leatOrder);
            return $leatOrder;
        } catch (\Throwable $e) {
            $this->connector->getLogger('order-returns')->log(sprintf(
                'Failed to fetch Leat order for external_identifier: %s | error: %s',
                $this->getExternalIdentifier(),
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * @param LeatOrder|null $leatOrder
     * @return void
     */
    public function setLeatOrder(?LeatOrder $leatOrder): void
    {
        $this->leatOrder = $leatOrder;
    }
}
