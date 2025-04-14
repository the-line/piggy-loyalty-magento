define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Customer/js/customer-data',
    'moment'
], function (Component, ko, $, customerData, moment) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Leat_LoyaltyFrontend/leat-activity-log',
            initialTransactionsCount: 0
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();

            // Initialize with empty transactions
            this.transactions = ko.observableArray([]);

            // Subscribe to leat customer data section
            this.leatCustomerData = customerData.get('leat');

            // Update our observable when customer data changes
            this.leatCustomerData.subscribe(function (updatedData) {
                if (updatedData && updatedData.transactions) {
                    this.updateTransactions(updatedData.transactions);
                    this.hideServerRenderedTransactions();
                }
            }.bind(this));

            // Set initial data if available
            if (this.leatCustomerData() && this.leatCustomerData().transactions) {
                this.updateTransactions(this.leatCustomerData().transactions);
                this.hideServerRenderedTransactions();
            }

            return this;
        },

        /**
         * Hide server-rendered transactions and show KO transactions
         */
        hideServerRenderedTransactions: function() {
            $('.leat-initial-transaction').hide();
            $('.leat-ko-transaction').show();
        },

        /**
         * Update the transactions observable array
         *
         * @param {Array} transactions
         */
        updateTransactions: function(transactions) {
            if (!Array.isArray(transactions)) {
                return;
            }

            const formattedTransactions = transactions.map(transaction => {
                return {
                    action: transaction.action,
                    points: transaction.points,
                    formattedPoints: this.formatPoints(transaction.points),
                    date: transaction.date,
                    orderId: transaction.order_id ? '#' + transaction.order_id : '-'
                };
            });

            this.transactions(formattedTransactions);
        },

        /**
         * Format points with a sign
         *
         * @param {Number} points
         * @returns {String}
         */
        formatPoints: function(points) {
            return points >= 0 ? "+" + points : points.toString();
        }
    });
});
