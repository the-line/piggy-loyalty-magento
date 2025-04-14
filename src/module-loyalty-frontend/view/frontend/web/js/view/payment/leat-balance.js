define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/translate',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/totals',
    'Magento_Customer/js/customer-data',
    'Magento_Catalog/js/price-utils',
    'Leat_LoyaltyFrontend/js/action/set-leat-balance',
    'domReady!'
], function (
    $,
    ko,
    Component,
    $t,
    quote,
    totals,
    customerData,
    priceUtils,
    setLeatBalanceAction
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Leat_LoyaltyFrontend/payment/leat-balance',
            isVisible: false,
            prepaidBalance: 0,
            appliedBalance: 0,
            maxBalance: 0,
            isSliding: false,
            manualInput: '0',
            isBalanceApplied: false,
            errorMessage: '',
            isLoading: false,
            grandTotal: 0
        },

        /**
         * Initialize component
         * @inheritdoc
         */
        initialize: function() {
            this._super();
            return this;
        },

        /**
         * Initialize observables
         */
        initObservable: function () {
            this._super()
                .observe([
                    'isVisible',
                    'prepaidBalance',
                    'appliedBalance',
                    'maxBalance',
                    'isSliding',
                    'manualInput',
                    'isBalanceApplied',
                    'errorMessage',
                    'isLoading',
                    'grandTotal'
                ]);

            // Subscribe to quote totals to update maxBalance
            quote.totals.subscribe(function (totals) {
                if (totals) {
                    this.grandTotal(this.getBaseGrandTotal());
                    this.updateMaxBalance();
                }
            }, this);

            // Subscribe to customer data section for leat
            var leatData = customerData.get('leat');
            leatData.subscribe(function (data) {
                if (data && data.prepaidBalance !== undefined) {
                    this.prepaidBalance(parseFloat(data.prepaidBalance) || 0);
                    this.updateMaxBalance();
                    this.updateVisibility();
                }
            }, this);

            // Initialize with current data
            if (leatData() && leatData().prepaidBalance !== undefined) {
                this.prepaidBalance(parseFloat(leatData().prepaidBalance) || 0);
                this.updateMaxBalance();
                this.updateVisibility();

                // Simple AJAX call to check for applied balance
                $.ajax({
                    url: '/leat/balance/getapplied',
                    type: 'GET',
                    dataType: 'json'
                }).done(function(response) {
                    if (response && response.success && response.applied_balance > 0) {
                        var appliedAmount = parseFloat(response.applied_balance);

                        // Set values in the component
                        this.appliedBalance(appliedAmount);
                        this.manualInput(appliedAmount.toFixed(2));
                        this.isBalanceApplied(true);
                    }
                }.bind(this));
            }

            return this;
        },

        /**
         * Update max balance based on prepaid balance and grand total
         */
        updateMaxBalance: function () {
            var grandTotal = this.getBaseGrandTotal();
            var prepaidBalance = this.prepaidBalance();

            // Max balance is the lesser of available balance and grand total
            this.maxBalance(Math.min(prepaidBalance, grandTotal + parseFloat(this.appliedBalance())));
            this.grandTotal(grandTotal);
        },

        /**
         * Update visibility based on prepaid balance
         */
        updateVisibility: function () {
            // Only show if customer has prepaid balance
            this.isVisible(this.prepaidBalance() > 0);
        },

        /**
         * Get the current grand total from quote
         * @returns {number}
         */
        getBaseGrandTotal: function () {
            if (quote.totals()) {
                return parseFloat(quote.totals().base_grand_total);
            }
            return 0;
        },

        /**
         * Format price
         * @param {number} price
         * @returns {string}
         */
        formatPrice: function (price) {
            return priceUtils.formatPrice(price, quote.getPriceFormat());
        },

        /**
         * Handler for slider changes
         * @param {Object} data
         * @param {Event} event
         */
        onSliderChange: function (data, event) {
            var value = parseFloat(event.target.value);
            this.appliedBalance(value);
            this.manualInput(value.toFixed(2));
            this.isSliding(true);
            return true;
        },

        /**
         * Handler for when sliding stops
         */
        onSlideEnd: function () {
            if (this.isSliding()) {
                this.isSliding(false);
                this.applyBalance();
            }
        },

        /**
         * Handler for manual input changes
         */
        onManualInputChange: function () {
            var value = parseFloat(this.manualInput());

            if (isNaN(value)) {
                value = 0;
            }

            // Ensure value is within valid range
            value = Math.max(0, Math.min(value, this.maxBalance()));

            this.appliedBalance(value);
            this.manualInput(value.toFixed(2));
        },

        /**
         * Apply the selected balance amount
         */
        applyBalance: function () {
            var self = this;
            var amount = parseFloat(this.appliedBalance());

            if (isNaN(amount)) {
                amount = 0;
            }

            this.isLoading(true);
            this.errorMessage('');

            setLeatBalanceAction(amount)
                .done(function (response) {
                    if (response && response.success) {
                        self.isBalanceApplied(amount > 0);
                    } else if (response && response.error_message) {
                        self.errorMessage(response.error_message);
                    }
                })
                .fail(function () {
                    self.errorMessage($t('An error occurred while applying prepaid balance. Please try again.'));
                })
                .always(function () {
                    self.isLoading(false);
                });
        },

        /**
         * Cancel applied balance
         */
        cancelBalance: function () {
            if (this.isBalanceApplied()) {
                this.appliedBalance(0);
                this.manualInput('0');
                this.applyBalance();
            }
        },

        /**
         * Get section title
         * @returns {string}
         */
        getSectionTitle: function () {
            return $t('Use Your Prepaid Balance');
        },

        /**
         * Check if balance slider is disabled
         * @returns {boolean}
         */
        isBalanceSliderDisabled: function () {
            return this.isLoading() || this.maxBalance() <= 0;
        }
    });
});
