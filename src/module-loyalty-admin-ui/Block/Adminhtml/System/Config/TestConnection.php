<?php

declare(strict_types=1);

namespace Leat\LoyaltyAdminUI\Block\Adminhtml\System\Config;

use Leat\Loyalty\Model\Config;
use Leat\Loyalty\Model\Connector;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\FlagManager;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Magento\Store\Model\StoreManagerInterface;

class TestConnection extends GenericField
{
    public const FLAG_CODE = 'leat_loyalty_ping_status';
    private const string FLAG_CODE_FORMAT = 'leat_loyalty_ping_status_%d';

    public function __construct(
        protected Config                $config,
        protected Connector             $connector,
        protected FlagManager           $flagManager,
        RequestInterface      $request,
        StoreManagerInterface $storeManager,
        Context               $context,
        array                 $data = [],
        ?SecureHtmlRenderer   $secureRenderer = null
    ) {
        parent::__construct($request, $storeManager, $context, $data, $secureRenderer);
    }

    /**
     * Add button to test the Leat connection.
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
            ->setLabel(__('Test Connection'))
            ->setOnClick('window.testLeatConnection(); return false;')
            ->toHtml();

        $storeId = (int) $this->getStoreId();

        // Add translated messages
        $testingMessage = __('Testing connection...');
        $missingConfigMessage = __('Missing configuration: Please set Personal Access Token and Shop UUID');
        $connectionFailedMessage = __('Connection test failed');

        $html .= <<<HTML
            <script type="text/javascript">
                require(['jquery', 'mage/translate'], function($, \$t) {
                    window.testLeatConnection = function() {
                        var resultField = document.getElementById('row_leat_connection_ping_state');
                        if (resultField) {
                            // Set testing message
                            resultField.cells[1].innerHTML = '<span class="testing">{$testingMessage}</span>';
                        }

                        // Test if configuration is complete
                        var personalAccessTokenInput = $('#leat_connection_personal_access_token');
                        var shopUuidInput = $('#leat_connection_shop_uuid');

                        if (!personalAccessTokenInput.length || !shopUuidInput.length) {
                            updatePingStatus('error', 'Cannot find configuration fields');
                            return;
                        }

                        var personalAccessToken = personalAccessTokenInput.val();
                        var shopUuid = shopUuidInput.val();
                        if (!personalAccessToken || !shopUuid) {
                            updatePingStatus('error', '{$missingConfigMessage}');
                            return;
                        }

                        // Use AJAX to test the connection
                        $.ajax({
                            url: '{$this->getUrl('leat_loyalty/connection/testConnection', ['store' => $storeId])}',
                            type: 'POST',
                            dataType: 'json',
                            data: {
                                form_key: FORM_KEY,
                                store: $storeId
                            },
                            success: function(result) {
                                if (result.success) {
                                    updatePingStatus('success', result.message);
                                } else {
                                    updatePingStatus('error', result.message);
                                }
                            },
                            error: function(xhr) {
                                if (xhr.responseJSON && xhr.responseJSON.message) {
                                    updatePingStatus('error', xhr.responseJSON.message);
                                } else {
                                    updatePingStatus('error', '{$connectionFailedMessage}');
                                }
                            }
                        });
                    };

                    function updatePingStatus(status, message) {
                        var resultField = document.getElementById('row_leat_connection_ping_state');
                        if (resultField) {
                            var className = status === 'success' ? 'success-msg' : 'error-msg';
                            resultField.cells[1].innerHTML = '<span class="' + className + '">' + message + '</span>';
                        }
                    }
                });
            </script>
            <style>
                .testing { color: #eb5202; }
                .success-msg { color: #006400; }
                .error-msg { color: #e22626; }
            </style>
        HTML;

        return $html;
    }
}
