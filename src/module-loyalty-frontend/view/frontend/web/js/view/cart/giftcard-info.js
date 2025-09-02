define([
    'jquery',
    'uiComponent',
    'ko'
], function ($, Component, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Leat_LoyaltyFrontend/cart/giftcard-info',
            isVisible: false,
            amount: '',
            isGiftcard: false
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();

            // Check if this is a giftcard product
            this.checkIfGiftcard();

            return this;
        },

        /**
         * Check if the current cart item is a giftcard
         */
        checkIfGiftcard: function () {
            var buyRequest = this.getBuyRequest();
            debugger;

            if (buyRequest && buyRequest.leat_giftcard_type &&
                (buyRequest.leat_giftcard_type === '1' || buyRequest.leat_giftcard_type === '2')) {

                this.isGiftcard = true;
                this.isVisible = true;

                // Add giftcard css class to parent item
                $(this.element).closest('.item-info').addClass('giftcard-item');

                // Get giftcard amount
                if (buyRequest.leat_giftcard_amount) {
                    this.amount = parseFloat(buyRequest.leat_giftcard_amount).toFixed(2);
                }
            }
        },

        /**
         * Get buy request data from item
         *
         * @returns {Object|null}
         */
        getBuyRequest: function () {
            var options = this.getItemOptions(),
                buyRequest = null;

            if (options && options.length) {
                // Find the info_buyRequest option
                options.forEach(function (option) {
                    if (option.code === 'info_buyRequest') {
                        try {
                            if (typeof option.value === 'object') {
                                buyRequest = option.value;
                            } else {
                                buyRequest = JSON.parse(option.value);
                            }
                        } catch (e) {
                            console.error('Error parsing buyRequest', e);
                        }
                    }
                });
            }

            return buyRequest;
        },

        /**
         * Get options from the item data
         *
         * @returns {Array}
         */
        getItemOptions: function () {
            return this.item() && this.item().options && this.item().options.length ?
                this.item().options : [];
        }
    });
});
