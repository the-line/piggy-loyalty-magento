<?php
declare(strict_types=1);

namespace Leat\LoyaltyFrontend\Block\Widget;

use Leat\AsyncQueue\Model\Queue\Request\RequestTypePool;
use Leat\Loyalty\Model\Connector;
use Leat\Loyalty\Model\ResourceModel\Loyalty\ContactResource;
use Leat\LoyaltyFrontend\Block\GenericWidgetBlock;
use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\CustomerContactLink;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class Balance extends GenericWidgetBlock
{
    /**
     * @var string
     */
    protected $_template = 'Leat_LoyaltyFrontend::widget/balance.phtml';

    /**
     * @var string
     */
    protected string $defaultId = 'leat-balance';

    /**
     * @var string
     */
    protected string $defaultCssClass = 'leat-loyalty-header';

    public function __construct(
        StoreManagerInterface $storeManager,
        Config $config,
        CustomerSession $customerSession,
        ContactResource $contactResource,
        Connector $connector,
        RequestTypePool $requestTypePool,
        Context $context,
        protected CustomerContactLink $customerContactLink
    ) {
        parent::__construct(
            $storeManager,
            $config,
            $customerSession,
            $contactResource,
            $connector,
            $requestTypePool,
            $context
        );
    }


    /**
     * Get points balance
     *
     * @return int
     */
    public function getPointsBalance(): int
    {
        try {
            $customerId = $this->customerSession->getCustomerId();
            if (!$customerId) {
                return 0;
            }

            $contact = $this->customerContactLink->getCustomerContact((int)$customerId);
            if (!$contact) {
                return 0;
            }

            return (int)($contact->getCreditBalance()->getBalance() ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get prepaid balance in cents
     *
     * @return int
     */
    public function getPrepaidBalance(): int
    {
        try {
            $customerId = $this->customerSession->getCustomerId();
            if (!$customerId) {
                return 0;
            }

            $contact = $this->customerContactLink->getCustomerContact((int)$customerId);
            if (!$contact) {
                return 0;
            }

            return (int)($contact->getPrepaidBalance() ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Check if we should show points
     *
     * @return bool
     */
    public function shouldShowPoints(): bool
    {
        if (!$this->config->getIsEnabled($this->getStoreId())) {
            return false;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return false;
        }

        return true;
    }

    /**
     * Get widget heading
     *
     * @return string
     */
    public function getWidgetHeading(): string
    {
        return (string) ($this->getData('widget_heading') ?? __('Your Balance'));
    }

    /**
     * Get credit name to display (e.g., "Points" or a custom name)
     *
     * @return string
     */
    public function getCreditName(): string
    {
        return (string) (($this->getData('credit_name')
            ?? $this->config->getCreditName($this->getStoreId()))
            ?? __('Points'));
    }

    /**
     * Get prepaid balance name to display
     *
     * @return string
     */
    public function getPrepaidBalanceName(): string
    {
        return (string) ($this->getData('prepaid_balance_name') ?? (string) __('Prepaid Balance'));
    }

    /**
     * Check if the header should be visible
     *
     * @return bool
     */
    public function isHeaderVisible(): bool
    {
        return (bool)($this->getData('show_header') ?? false);
    }
}
