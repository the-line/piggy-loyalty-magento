<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\ViewModel;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Service\CreditCalculator;
use Magento\Customer\Model\Context;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

class LoyaltyStoreCheck implements ArgumentInterface
{
    public function __construct(
        protected Config                $config,
        protected HttpContext           $httpContext,
        protected StoreManagerInterface $storeManager,
        protected CreditCalculator      $calculator
    ) {
    }

    /**
     * Whether to display the additional credits field on the PDP or not.
     *
     * @return bool
     * @throws NoSuchEntityException|LocalizedException
     */
    public function showLeatLoyalty(): bool
    {
        $isEnabled = $this->config->getIsEnabled();
        $groupId = (int) $this->httpContext->getValue(Context::CONTEXT_GROUP);
        return $isEnabled && in_array($groupId ?? 0, $this->config->getCustomerGroupMapping());
    }

    /**
     * @param float $finalPrice
     * @return int
     * @throws NoSuchEntityException
     */
    public function getPointsEstimation(float $finalPrice, ?string $contactUuid = null): int
    {
        return $this->calculator->calculateCreditsByPurchaseAmount(
            $finalPrice,
            (int) $this->storeManager->getStore()->getId(),
            $contactUuid
        );
    }

    /**
     * @return string|null
     */
    public function getCreditLabel(): ?string
    {
        return $this->config->getCreditName();
    }

    /**
     * @return bool
     */
    public function isShowOnCart(): bool
    {
        return $this->config->isShowOnCartPage();
    }

    /**
     * @return bool
     */
    public function isShowOnCheckoutSuccess()
    {
        return $this->config->isShowOnCheckoutSuccessPage();
    }
}
