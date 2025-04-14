<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Totals\Quote;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\CustomerContactLink;
use Leat\LoyaltyFrontend\ViewModel\LoyaltyStoreCheck;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartExtensionFactory;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

class LeatCredits extends AbstractTotal
{
    public function __construct(
        protected Config               $config,
        protected LoyaltyStoreCheck    $loyaltyStoreCheck,
        protected CartExtensionFactory $cartExtensionFactory,
        protected CustomerContactLink  $contact,
    ) {
    }

    /**
     * Sets the extension attribute for quote that will be later fetched in the fetch function
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this|AbstractTotal
     * @throws NoSuchEntityException
     */
    public function collect(
        Quote                       $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total                       $total,
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        if (empty($shippingAssignment->getItems())) {
            return $this;
        }

        // Add totalCredit amount from only the cart items
        $totalCredit = (float)$this->calculateCredits($quote, (float) $total->getData('base_subtotal_with_discount'));

        $extensionAttributes = $quote->getExtensionAttributes() ?? $this->cartExtensionFactory->create();
        $extensionAttributes->setLeatLoyaltyCreditCount($totalCredit);
        $quote->setExtensionAttributes($extensionAttributes);

        return $this;
    }

    /**
     * Make a rough estimation of the total amount of credits earned from this cart
     *
     * @param Quote $quote
     * @param float $totalAmount
     * @return int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function calculateCredits(Quote $quote, float $totalAmount): int
    {
        if (!$quote->getCustomer()->getId() ||
            !in_array($quote->getCustomer()?->getGroupId() ?? 0, $this->config->getCustomerGroupMapping()) ||
            !$this->loyaltyStoreCheck->isShowOnCart()) {
            return 0;
        }

        $credits = $this->loyaltyStoreCheck->getPointsEstimation(
            $totalAmount,
            $quote->getCustomerId() ? $this->contact->getContactUuid($quote->getCustomerId()): null
        );

        return (int) $credits;
    }


    /**
     * Fetch the extension attribute that is set in the collect() function
     *
     * @param Quote $quote
     * @param Total $total
     * @return array
     * @throws NoSuchEntityException
     */
    public function fetch(
        Quote $quote,
        Total $total
    ) {
        $creditTotal = null;
        if ($extensionAttributes = $quote->getExtensionAttributes()) {
            $creditTotal = $extensionAttributes->getLeatLoyaltyCreditCount();
        }

        if ($creditTotal === null) {
            $creditTotal = $this->calculateCredits($quote, (float) $total->getData('base_subtotal_with_discount'));
        }

        return [
            'code' => 'leat_loyalty',
            'title' => __($this->config->getCreditName()),
            'value' => $creditTotal
        ];
    }
}
