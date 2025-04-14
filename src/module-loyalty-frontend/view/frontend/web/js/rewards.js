define([
    'jquery',
    'uiComponent',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'Leat_LoyaltyFrontend/js/progress-button'
], function ($, Component, $t, customerData, progressButton) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Leat_LoyaltyFrontend/rewards',
            redeemUrl: null
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();

            // Get the URL from the global config
            if (window.LeatConfig && window.LeatConfig.redeemUrl) {
                this.redeemUrl = window.LeatConfig.redeemUrl;
            }

            this.initEventHandlers();
            return this;
        },

        /**
         * Initialize event handlers
         */
        initEventHandlers: function () {
            $(document).on('click', '.leat-reward-redeem', this.onRedeemClick.bind(this));
        },

        /**
         * Handle redeem button click
         *
         * @param {Event} event
         */
        onRedeemClick: function (event) {
            const button = $(event.currentTarget);
            const rewardId = button.data('reward-id');

            if (!rewardId) {
                return;
            }

            // Initialize progress button with all text states
            const progress = progressButton.init(button, {
                initialText: $t('Redeem'),
                loadingText: $t('Redeeming...'),
                successText: $t('Redeemed!')
            });

            this.redeemReward(rewardId)
                .done(function (response) {
                    if (response.success) {
                        // Complete with success state
                        progress.complete(true, {
                            resetDelay: 3000
                        });

                        this.showSuccessMessage($t('Reward successfully redeemed!'));

                        // Refresh the Leat customer data section to update the points balance
                        try {
                            customerData.invalidate(['leat']);
                            customerData.reload(['leat'], true);
                            console.log('Leat points data refreshed');
                        } catch (e) {
                            console.error('Error refreshing Leat points data', e);
                        }
                    } else {
                        // Complete with error state
                        progress.complete(false);
                        this.showErrorMessage(response.message || $t('Failed to redeem reward.'));
                    }
                }.bind(this))
                .fail(function () {
                    // Reset on failure
                    progress.reset();
                    this.showErrorMessage($t('An error occurred while redeeming your reward.'));
                }.bind(this));
        },

        /**
         * Redeem a reward
         *
         * @param {string} rewardId
         * @returns {jQuery.Deferred}
         */
        redeemReward: function (rewardId) {
            return $.ajax({
                url: this.redeemUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    reward_id: rewardId,
                    form_key: $.mage.cookies.get('form_key')
                }
            });
        },

        /**
         * Show success message
         *
         * @param {string} message
         */
        showSuccessMessage: function (message) {
            // Using Magento's standard messages UI component
            if (typeof window.messages !== 'undefined') {
                window.messages.addSuccessMessage({
                    message: message
                });
            } else {
                // Attempt to use Magento's message manager
                if (typeof requirejs !== 'undefined') {
                    requirejs(['Magento_Ui/js/model/messageList'], function(messageList) {
                        messageList.addSuccessMessage({
                            message: message
                        });
                    });
                } else {
                    // Fallback to alert for testing
                    alert(message);
                }
            }
        },

        /**
         * Show error message
         *
         * @param {string} message
         */
        showErrorMessage: function (message) {
            // Using Magento's standard messages UI component
            if (typeof window.messages !== 'undefined') {
                window.messages.addErrorMessage({
                    message: message
                });
            } else {
                // Attempt to use Magento's message manager
                if (typeof requirejs !== 'undefined') {
                    requirejs(['Magento_Ui/js/model/messageList'], function(messageList) {
                        messageList.addErrorMessage({
                            message: message
                        });
                    });
                } else {
                    // Fallback to alert for testing
                    alert(message);
                }
            }
        }
    });
});
