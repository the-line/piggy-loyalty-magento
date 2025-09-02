<?php
/**
 * GenericField
 *
 * @copyright Copyright © 2025 Bold. All rights reserved.
 * @author    luuk@boldcommerce.nl
 */
declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class GenericField extends Field {

    public function __construct(
        protected RequestInterface $request,
        protected StoreManagerInterface $storeManager,
        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }


    /**
     * @return int
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStoreId(): ?int
    {
        $scope = $this->getScope();
        $scopeId = $this->getScopeId();
        if ($scope === 'default') {
            $store = null;
        } elseif ($scope === 'website') {
            $store = (int) $this->storeManager->getWebsite($scopeId)?->getDefaultStore()->getId();
        } else {
            $store = (int) $scopeId;
        }

        return $store;
    }

    /**
     * @return string
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getScope(): string
    {
        $storeId = $this->request->getParam('store');
        $websiteId = $this->request->getParam('website');
        if ($storeId) {
            return ScopeInterface::SCOPE_STORE;
        } elseif ($websiteId) {
            return ScopeInterface::SCOPE_WEBSITE;
        } else {
            return ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        }
    }

    /**
     * @return int
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getScopeId(): int
    {
        $scope = $this->getScope();
        if ($scope === ScopeInterface::SCOPE_STORE) {
            return (int) $this->request->getParam('store');
        } elseif ($scope === ScopeInterface::SCOPE_WEBSITE) {
            return (int) $this->request->getParam('website');
        } else {
            return 0;
        }
    }
}
