define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Magento_Checkout/js/model/totals'
], function (Component, quote, priceUtils, totals) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Leat_LoyaltyFrontend/checkout/summary/leat-giftcard'
        },

        /**
         * @return {Boolean}
         */
        isDisplayed: function () {
            return this.getLeatBalance() !== 0;
        },

        /**
         * Get leat balance amount
         *
         * @returns {Number}
         */
        getLeatBalance: function() {
            var totals = quote.getTotals()();
            if (totals) {
                // Look for our custom total
                if (totals.total_segments) {
                    for (var i = 0; i < totals.total_segments.length; i++) {
                        if (totals.total_segments[i].code === 'leat_loyalty_giftcard') {
                            return totals.total_segments[i].value;
                        }
                    }
                }
            }
            return 0;
        },

        /**
         * @return {String}
         */
        getFormattedLeatBalance: function () {
            return this.getFormattedPrice(this.getLeatBalance());
        }
    });
});
