define([
    'uiComponent',
    'ko',
    'jquery',
    'Magento_Customer/js/customer-data'
], function (Component, ko, $, customerData) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Leat_LoyaltyFrontend/loyalty-points-balance',
            leatData: {},
            initialPoints: 0,
            initialBalance: 0
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();

            // Initialize with default data including server-provided initial points
            this.leatData = ko.observable({
                points: this.initialPoints || 0,
                prepaidBalance: this.initialBalance || 0,
                hasContact: this.initialPoints > 0
            });

            // Handle transition from server-side render to KO
            this.handleInitialPoints();

            // Subscribe to leat customer data section
            this.leatCustomerData = customerData.get('leat');

            // Update our observable when customer data changes
            this.leatCustomerData.subscribe(function (updatedData) {
                if (updatedData) {
                    this.leatData(updatedData);
                    this.hideServerRenderedPoints();
                }
            }.bind(this));

            // Set initial data if available
            if (this.leatCustomerData()) {
                this.leatData(this.leatCustomerData());
                this.hideServerRenderedPoints();
            }

            // Force reload customer data once loaded to ensure it's fresh
            // Use a slight delay to ensure the page is fully loaded
            setTimeout(function() {
                customerData.reload(['leat'], false);
            }, 500);

            return this;
        },

        /**
         * Hide server-rendered points and show KO points
         */
        hideServerRenderedPoints: function() {
            $('.leat-initial-points').hide();
            $('.leat-ko-points').show();
        },

        /**
         * Handle transition from server-side render to KO
         */
        handleInitialPoints: function() {
            // Hide server-rendered points if customer data is already available
            if (this.leatCustomerData && this.leatCustomerData()) {
                this.hideServerRenderedPoints();
            }

            // Otherwise, KO points will remain hidden until customer data loads
            // to prevent value flicker
        },

        /**
         * Get the points value to display, ensuring 0 is shown when no points
         *
         * @returns {String}
         */
        displayPoints: function() {
            if (!this.leatData || typeof this.leatData !== 'function') {
                return this.initialPoints.toString() || '0';
            }

            var data = this.leatData();

            // Always show points as a number, defaulting to 0
            if (!data || !data.hasOwnProperty('points') || data.points === null || data.points === undefined) {
                return this.initialPoints.toString() || '0';
            }

            // Convert to number and return as string
            return parseInt(data.points, 10).toString();
        },

        /**
         * Get the points value to display, ensuring 0 is shown when no points
         *
         * @returns {String}
         */
        displayBalance: function() {
            if (!this.leatData || typeof this.leatData !== 'function') {
                return Number.parseFloat(this.initialBalance.toString() || '0').toFixed(2);
            }

            var data = this.leatData();

            // Always show points as a number, defaulting to 0
            if (!data || !data.hasOwnProperty('prepaidBalance') || data.prepaidBalance === null || data.prepaidBalance === undefined) {
                return Number.parseFloat(this.initialBalance.toString() || '0').toFixed(2);
            }

            // Convert to number and return as string
            return Number.parseFloat(data.prepaidBalance).toFixed(2);
        }
    });
});
