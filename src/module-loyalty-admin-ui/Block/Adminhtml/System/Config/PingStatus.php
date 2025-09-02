<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config;

use Leat\Loyalty\Model\Config;
use Leat\LoyaltyAdminUI\Service\ConnectionTester;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\StoreManagerInterface;

class PingStatus extends GenericField
{
    public function __construct(
        protected ConnectionTester $connectionTester,
        protected Config $config,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct(
            $request,
            $storeManager,
            $context,
            $data,
            $secureRenderer
        );
    }

    /**
     * Render the connection status
     *
     * @param AbstractElement $element
     * @return string
     * @throws NoSuchEntityException
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $storeId = (int) $this->getStoreId();

        // If this is a new configuration that hasn't been tested yet
        $personalAccessToken = $this->config->getPersonalAccessToken($storeId);
        $shopUuid = $this->config->getShopUuid($storeId);

        if (empty($personalAccessToken) || empty($shopUuid)) {
            return '
                <span class="notice-msg">' .
                    __(
                        'No connection test has been performed yet.' .
                        ' Click "Test Connection" after entering your credentials.'
                    ) .
                '</span>';
        }

        $status = $this->connectionTester->testConnection($storeId);
        if ($status['success']) {
            return '<span class="success-msg">' . __($status['message']) . '</span>';
        }

        return '<span class="error-msg">' . __($status['message']) . '</span>';
    }

    /**
     * Add CSS styles for status messages
     *
     * @return string
     */
    protected function _renderCss(): string
    {
        return '<style>
            .success-msg { color: #006400; font-weight: bold; }
            .error-msg { color: #e22626; font-weight: bold; }
            .notice-msg { color: #007bdb; font-weight: bold; }
        </style>';
    }

    /**
     * Render the full element with CSS
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        return parent::render($element) . $this->_renderCss();
    }
}
