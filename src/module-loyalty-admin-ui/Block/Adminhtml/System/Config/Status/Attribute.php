<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config\Status;

use Leat\Loyalty\Model\Connector;
use Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config\AbstractStatus;
use Leat\LoyaltyAdminUI\Service\SyncValidator;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\FlagManager;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\StoreManagerInterface;

class Attribute extends AbstractStatus
{
    /**
     * @param SyncValidator $syncValidator
     * @param FlagManager $flagManager
     * @param Connector $leatConnector
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param Context $context
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        protected SyncValidator $syncValidator,
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
        $validationMessage = $this->syncValidator->validateSyncStatus($storeId);
        if ($validationMessage !== null) {
            return '<span class="warning-msg">' . $validationMessage . '</span>';
        }

        return '<span class="success-msg">' . __('No <i>attribute</i> synchronisation necessary, everything is up to date.') . '</span>';
    }
}
