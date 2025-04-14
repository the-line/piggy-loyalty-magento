<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config;

use Leat\LoyaltyAdminUI\Service\SyncValidator;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class SyncStatus extends Field
{
    /**
     * @var SyncValidator
     */
    private SyncValidator $syncValidator;

    /**
     * @param SyncValidator $syncValidator
     * @param Context $context
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        SyncValidator $syncValidator,
        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        $this->syncValidator = $syncValidator;
        parent::__construct(
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
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $storeId = (int)$this->_request->getParam('store', 0);

        $validationMessage = $this->syncValidator->validateSyncStatus($storeId);
        if ($validationMessage !== null) {
            return '<span class="warning-msg">' . $validationMessage . '</span>';
        }

        return '<span class="success-msg">' . __('No synchronisation necessary, everything is up to date.') . '</span>';
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
}
