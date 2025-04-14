define([
    'jquery',
    'mage/storage',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/get-payment-information',
    'Magento_Checkout/js/action/recollect-shipping-rates',
    'Magento_Checkout/js/model/totals',
], function (
    $,
    storage,
    urlBuilder,
    customer,
    quote,
    errorProcessor,
    fullScreenLoader,
    paymentInformationAction,
    recollectShippingRates,
    totals
) {
    'use strict';

    /**
     * Apply leat balance amount to quote
     *
     * @param {Number} amount
     * @returns {Deferred}
     */
    return function (amount) {
        var serviceUrl, payload;

        fullScreenLoader.startLoader();

        if (customer.isLoggedIn()) {
            serviceUrl = urlBuilder.createUrl('/carts/mine/leat-balance', {});
            payload = {
                cartId: quote.getQuoteId(),
                balanceAmount: amount
            };
        } else {
            serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/leat-balance', {
                cartId: quote.getQuoteId()
            });
            payload = {
                balanceAmount: amount
            };
        }

        return storage.post(
            serviceUrl,
            JSON.stringify(payload)
        ).done(function (response) {
            if (response && response.success) {
                const deferred = $.Deferred();
                totals.isLoading(true);
                recollectShippingRates();
                paymentInformationAction(deferred);
                $.when(deferred).done(function () {
                    fullScreenLoader.stopLoader();
                    totals.isLoading(false);
                });
            } else {
                fullScreenLoader.stopLoader();
            }
        }).fail(function (response) {
            errorProcessor.process(response);
            fullScreenLoader.stopLoader();
            totals.isLoading(false);
        });
    };
});
