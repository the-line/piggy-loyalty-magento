define([
    'jquery',
    'mage/mage',
    'mage/translate',
    'mage/storage', // For AJAX
    'Magento_Ui/js/modal/alert' // For simple error display, could use messageList too
], function ($, mage, $t, storage, alert) {
    'use strict';

    $.widget('mage.giftcardBalance', {
        options: {
            checkUrl: '',
            formSelector: '#form-giftcard-check',
            codeSelector: '#giftcard-check-code',
            resultSelector: '#giftcard-balance-result',
            messagesSelector: '#giftcard-balance-messages',
            submitButtonSelector: 'button[type="submit"]'
        },

        /**
         * Widget initialization
         * @private
         */
        _create: function () {
            this.element.mage('validation', {
                submitHandler: $.proxy(this.checkBalance, this)
            });
        },

        /**
         * Perform AJAX call to check balance
         */
        checkBalance: function () {
            var form = $(this.options.formSelector);
            var codeInput = $(this.options.codeSelector);
            var resultCode = codeInput.val();
            var resultContainer = $(this.options.resultSelector);
            var messagesContainer = $(this.options.messagesSelector);
            var submitButton = form.find(this.options.submitButtonSelector);

            if (!form.validation('isValid')) {
                return false;
            }

            resultContainer.hide().empty();
            messagesContainer.empty();
            submitButton.prop('disabled', true).addClass('disabled');

            storage.post(
                this.options.checkUrl,
                JSON.stringify({ giftcard_code: resultCode })
            ).done(function (response) {
                if (response.success) {
                    var balanceHtml = $t('Current Balance: ') + '<strong>' + response.formatted_amount + '</strong>';
                    if (response.expiration_date) {
                        balanceHtml += ' (' + $t('Expires: ') + response.expiration_date + ')';
                    }
                    if (!response.is_active) {
                         balanceHtml += ' <span style="color:red;">(' + $t('Inactive') + ')</span>';
                    }
                    resultContainer.html(balanceHtml).show();
                } else {
                    messagesContainer.html('<div class="message message-error error"><div>' + (response.message || $t('Could not retrieve balance.')) + '</div></div>');
                }
            }.bind(this)).fail(function (response) {
                 messagesContainer.html('<div class="message message-error error"><div>' + $t('An error occurred while checking the balance.') + '</div></div>');
                 // Log detailed error?
                 console.error('Giftcard Balance Check Failed:', response);
            }.bind(this)).always(function () {
                 submitButton.prop('disabled', false).removeClass('disabled');
            }.bind(this));
        }
    });

    return $.mage.giftcardBalance;
});
