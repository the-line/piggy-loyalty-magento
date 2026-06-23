<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config;

use Leat\Loyalty\Model\Connector;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\FlagManager;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractStatus extends GenericField
{
    /**
     * @param FlagManager $flagManager
     * @param Connector $leatConnector
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param Context $context
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        protected FlagManager $flagManager,
        protected Connector $leatConnector,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
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
     * Render the sync status
     *
     * @param AbstractElement $element
     * @return string
     * @throws NoSuchEntityException
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $storeId = $this->getStoreId();
        return $this->getStatus($storeId);
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    protected function getStatus(?int $storeId = null): string
    {
        return '<span>Sync Status Content Goes Here</span>';
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
                    .warning-msg { color: #eb5202; font-weight: bold; }
                    .notice-msg { color: #007bdb; font-weight: bold; }
                    .neutral-msg { color: #6b7280; font-style: italic; font-weight: bold; }
                    .datetime-msg { color: #514943; font-style: italic; font-size: 0.9em; margin-top: 5px; }
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

    /**
     * @return string
     */
    protected function getNoFlagDataMessage()
    {
        return sprintf("<span>%s<br/>%s<br/>%s</span>",
            '<span class="neutral-msg">' . __("Awaiting first sync...") . '</span>',
            '<span class="datetime-msg">' . __("Crontab might not be correctly configured.") . '</span>',
            '<span class="datetime-msg">' . __("Otherwise, run `bin/magento sys:cron:run leat_loyalty_sync_categories_and_products` manually.") . '</span>'
        );
    }
}
