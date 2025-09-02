<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\FlagManager;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\StoreManagerInterface;

class Sync extends GenericField
{
    public const FLAG_CODE = 'leat_loyalty_sync_data_status';
    private const string FLAG_CODE_FORMAT = 'leat_loyalty_sync_data_status_%d';

    public function __construct(
        protected FlagManager $flagManager,
        StoreManagerInterface $storeManager,
        RequestInterface      $request,
        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($request, $storeManager, $context, $data, $secureRenderer);
    }

    /**
     * Add button to sync data
     *
     * @param AbstractElement $element
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $html = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')
            ->setType('button')
            ->setClass('scalable')
            ->setLabel(__('Syncronize Now'))
            ->setOnClick('window.syncLeatData(); return false;')
            ->toHtml();

        $storeId = $this->getStoreId();
        $syncingMessage = __('Syncing data...');
        $syncFailedMessage = __('Data synchronization failed');
        $html .= <<<HTML
            <script type="text/javascript">
                require(['jquery', 'mage/translate'], function($, \$t) {
                    window.syncLeatData = function() {
                        var resultField = document.getElementById('row_leat_sync_settings_sync_status');
                        if (resultField) {
                            // Set syncing message
                            resultField.cells[1].innerHTML = '<span class="syncing">{$syncingMessage}</span>';
                        }

                        // Use AJAX to sync data
                        $.ajax({
                            url: '{$this->getUrl('leat_loyalty/sync/data', ['store' => $storeId])}',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                form_key: FORM_KEY,
                                store: $storeId
                            },
                            success: function(result) {
                                if (result.success) {
                                    updateSyncStatus('success', result.message);
                                } else {
                                    updateSyncStatus('error', result.message);
                                }
                            },
                            error: function(xhr) {
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    updateSyncStatus('error', xhr.responseJSON.message);
                                } else {
                                    updateSyncStatus('error', '{$syncFailedMessage}');
                                }
                            }
                        });
                    };

                    function updateSyncStatus(status, message) {
                        var resultField = document.getElementById('row_leat_sync_settings_sync_status');
                        if (resultField) {
                            var className = status === 'success' ? 'success-msg' : 'error-msg';
                            resultField.cells[1].innerHTML = '<span class="' + className + '">' + message + '</span>';
                        }
                    }
                });
            </script>
            <style>
                .syncing { color: #eb5202; }
                .success-msg { color: #006400; font-weight: bold; }
                .error-msg { color: #e22626; font-weight: bold; }
            </style>
        HTML;

        return $html;
    }
}
