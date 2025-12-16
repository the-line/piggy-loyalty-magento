<?php

declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Model\Checkout;

use Magento\Checkout\Block\Checkout\LayoutProcessorInterface;
use Leat\Loyalty\Model\Config;

class LayoutProcessor implements LayoutProcessorInterface
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function process($jsLayout)
    {
        if (!isset($jsLayout['components']['checkout']['children'])) {
            return $jsLayout;
        }

        $checkoutChildren = &$jsLayout['components']['checkout']['children'];

        if (!$this->config->isPrepaidBalanceEnabled()) {
            $this->removePrepaidBalanceComponents($checkoutChildren);
        } else {
            $this->configurePrepaidBalanceComponent($checkoutChildren);
        }

        if (!$this->config->getIsEnabled() || !$this->config->isGiftcardEnabled()) {
            $this->removeGiftcardComponents($checkoutChildren);
        }

        return $jsLayout;
    }

    private function configurePrepaidBalanceComponent(array &$checkoutChildren): void
    {
        if (isset($checkoutChildren['steps']['children']['billing-step']['children']['payment']['children']['afterMethods']['children']['leat-balance']['config'])) {
            $checkoutChildren['steps']['children']['billing-step']['children']['payment']['children']['afterMethods']['children']['leat-balance']['config']['title'] = 
                $this->config->getPrepaidBalanceTitle();
        }

        if (isset($checkoutChildren['sidebar']['children']['summary']['children']['totals']['children']['leat_loyalty_balance']['config'])) {
            $checkoutChildren['sidebar']['children']['summary']['children']['totals']['children']['leat_loyalty_balance']['config']['title'] = 
                $this->config->getPrepaidBalanceSummaryTitle();
        }
    }

    private function removePrepaidBalanceComponents(array &$checkoutChildren): void
    {
        if (isset($checkoutChildren['sidebar']['children']['summary']['children']['totals']['children']['leat_loyalty_balance'])) {
            unset($checkoutChildren['sidebar']['children']['summary']['children']['totals']['children']['leat_loyalty_balance']);
        }

        if (isset($checkoutChildren['steps']['children']['billing-step']['children']['payment']['children']['afterMethods']['children']['leat-balance'])) {
            unset($checkoutChildren['steps']['children']['billing-step']['children']['payment']['children']['afterMethods']['children']['leat-balance']);
        }
    }

    private function removeGiftcardComponents(array &$checkoutChildren): void
    {
        if (isset($checkoutChildren['sidebar']['children']['summary']['children']['totals']['children']['leat_loyalty_giftcard'])) {
            unset($checkoutChildren['sidebar']['children']['summary']['children']['totals']['children']['leat_loyalty_giftcard']);
        }

        if (isset($checkoutChildren['steps']['children']['billing-step']['children']['payment']['children']['afterMethods']['children']['leat-giftcard'])) {
            unset($checkoutChildren['steps']['children']['billing-step']['children']['payment']['children']['afterMethods']['children']['leat-giftcard']);
        }
    }
}
