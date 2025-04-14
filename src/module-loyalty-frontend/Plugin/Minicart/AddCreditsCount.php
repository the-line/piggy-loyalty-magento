<?php


declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Plugin\Minicart;

use Leat\Loyalty\Model\CustomerContactLink;
use Leat\LoyaltyFrontend\ViewModel\LoyaltyStoreCheck;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class AddCreditsCount
{
    public function __construct(
        protected ProductRepositoryInterface $productRepository,
        protected LoyaltyStoreCheck $leatStoreCheck,
        protected CustomerContactLink $contact,
        protected Session $checkoutSession,
    ) {
    }

    /**
     * Add a rough estimate of total credits earned to section data
     *
     * @param Cart $subject
     * @param array $result
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function afterGetSectionData(Cart $subject, array $result): array
    {
        if ($this->leatStoreCheck->showLeatLoyalty() && $this->leatStoreCheck->isShowOnCart()) {
            $result['leatCreditCount'] = $this->calculateLeatCredits();
        }

        return $result;
    }

    /**
     * Make a rough estimation of the total amount of credits earned from this cart
     *
     * @return int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function calculateLeatCredits(): int
    {
        $contactUuid = $this->contact->getContactUuid();
        $quote = $this->checkoutSession->getQuote();

        $totalValue = (float) $quote->getData('base_subtotal_with_discount');

        return $this->leatStoreCheck->getPointsEstimation($totalValue, $contactUuid);
    }
}
