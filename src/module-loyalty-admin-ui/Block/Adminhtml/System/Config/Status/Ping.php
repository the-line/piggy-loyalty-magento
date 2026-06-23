<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config\Status;

use Leat\Loyalty\Model\Connector;
use Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config\AbstractStatus;
use Leat\LoyaltyAdminUI\Service\ConnectionTester;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\FlagManager;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\StoreManagerInterface;

class Ping extends AbstractStatus
{
    public function __construct(
        protected ConnectionTester $connectionTester,
        FlagManager $flagManager,
        Connector $leatConnector,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct(
            $flagManager,
            $leatConnector,
            $request,
            $storeManager,
            $context,
            $data,
            $secureRenderer
        );
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    protected function getStatus(?int $storeId = null): string
    {
        // If this is a new configuration that hasn't been tested yet
        $personalAccessToken = $this->leatConnector->getConfig()->getPersonalAccessToken($storeId);
        if (empty($personalAccessToken)) {
            return '
                <span class="notice-msg">' .
                __(
                    'No connection test has been performed yet.' .
                    ' Click "Test Connection" having saved the the Personal Access Token.'
                ) .
                '</span>';
        }

        $status = $this->connectionTester->testConnection($storeId);
        if ($status['success']) {
            return '<span class="success-msg">' . __($status['message']) . '</span>';
        }

        return '<span class="error-msg">' . __($status['message']) . '</span>';
    }
}
