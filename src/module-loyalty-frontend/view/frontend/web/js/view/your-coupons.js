define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/translate',
    'mage/url',
    'Magento_Customer/js/customer-data',
    'moment',
    'Leat_LoyaltyFrontend/js/progress-button'
], function ($, ko, Component, $t, url, customerData, moment, progressButton) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Leat_LoyaltyFrontend/your-coupons',
            coupons: []
        },

        /**
         * Initialize component
         */
        initialize: function () {
            this._super();

            // Store original coupons data - need to do this before creating the observable
            var originalCoupons = this.coupons;

            // Initialize observables
            this.initialized = ko.observable(false);
            this.hasPreloadedData = ko.observable(false);
            this.coupons = ko.observableArray([]);
            this.isReloading = ko.observable(false); // Flag to prevent multiple simultaneous reloads

            // Handle prefetched data
            try {
                // Process the prefetched coupons data
                if (typeof originalCoupons === 'string' && originalCoupons.length > 0) {
                    // If it's a JSON string, parse it
                    var prefetchedCoupons = JSON.parse(originalCoupons);
                    if (prefetchedCoupons && Array.isArray(prefetchedCoupons) && prefetchedCoupons.length) {
                        this.coupons(prefetchedCoupons);
                        this.hasPreloadedData(true);
                    }
                } else if (Array.isArray(originalCoupons) && originalCoupons.length) {
                    // If already an array, use directly
                    this.coupons(originalCoupons);
                    this.hasPreloadedData(true);
                } else if (originalCoupons && typeof originalCoupons === 'object' && Object.keys(originalCoupons).length) {
                    // Handle case when passed as a direct object
                    this.coupons([originalCoupons]);
                    this.hasPreloadedData(true);
                }
            } catch (e) {
                console.error('Error processing prefetched coupons:', e, originalCoupons);
                this.coupons([]);
            }

            // Switch from PHP rendered content to KnockoutJS after initialization
            this.switchToKnockout();

            // Subscribe to leat customer data section for live updates
            var leatData = customerData.get('leat');

            leatData.subscribe(function (updatedData) {
                if (updatedData && updatedData.hasContact && !this.isReloading()) {
                    // Reload rewards when customer data changes to keep in sync
                    this.reloadCoupons();
                }
            }.bind(this));

            // Attach event handler for static buttons
            $(document).on('click', '.js-collect-reward', this.handleStaticButtonClick.bind(this));

            return this;
        },

        /**
         * Switch from PHP rendered content to KnockoutJS
         */
        switchToKnockout: function() {
            // When we have preloaded data, we want to make a clean transition
            if (this.hasPreloadedData()) {
                // Keep the PHP rendered content visible, but mark as initialized
                this.initialized(true);

                // Make the switch after a delay to ensure full rendering
                setTimeout(function() {
                    var $container = $('.leat-coupons-container');
                    if ($container.length) {
                        // Simply swap the visibility
                        $container.find('.leat-initial-content').hide();
                        $container.find('.leat-ko-content').show();
                    }
                }.bind(this), 100);
            } else {
                // No preloaded data, make a direct switch and load data
                setTimeout(function() {
                    if (this.initialized()) {
                        return;
                    }

                    var $container = $('.leat-coupons-container');
                    if ($container.length) {
                        // Hide the initial PHP rendered content
                        $container.find('.leat-initial-content').hide();

                        // Show the KnockoutJS content
                        $container.find('.leat-ko-content').show();

                        this.initialized(true);

                        // Fetch data from API
                        this.reloadCoupons();
                    }
                }.bind(this), 100);
            }
        },

        /**
         * Reload coupons from the server
         */
        reloadCoupons: function () {
            // Prevent multiple simultaneous reloads
            if (this.isReloading()) {
                console.log('Skipping duplicate reloadCoupons call - already in progress');
                return;
            }

            this.isReloading(true);
            console.log('Loading coupons from server');

            $.ajax({
                url: url.build('leat/reward/collectiblerewards'),
                type: 'GET',
                dataType: 'json',
                showLoader: false, // Hide loader to prevent flashing
                success: function (response) {
                    if (response.success && response.coupons) {
                        this.coupons(response.coupons);
                    } else if (response.success && Array.isArray(response.data)) {
                        // Alternative response format
                        this.coupons(response.data);
                    } else {
                        // Empty state if no valid response
                        this.coupons([]);
                    }
                    this.isReloading(false);
                    console.log('Finished loading coupons');
                }.bind(this),
                error: function () {
                    // Silent error handling without changing current state
                    this.isReloading(false);
                    console.error('Error loading coupons');
                }.bind(this)
            });
        },

        /**
         * Format date for display
         *
         * @param {String} dateString
         * @returns {String}
         */
        formatDate: function (dateString) {
            if (!dateString) {
                return '';
            }

            // Handle different date formats
            try {
                if (typeof dateString === 'object' && dateString.date) {
                    // Format is { date: "2023-01-01 00:00:00.000000", timezone_type: 3, timezone: "UTC" }
                    return moment(dateString.date).format('MMM D, YYYY');
                } else {
                    // Simple date string
                    return moment(dateString).format('MMM D, YYYY');
                }
            } catch (e) {
                console.error('Error formatting date:', e, dateString);
                return '';
            }
        },

        /**
         * Handle static button click (from PHP rendered content)
         *
         * @param {Event} event
         */
        handleStaticButtonClick: function(event) {
            // Ensure we have a jQuery object
            var $button = $(event.currentTarget);
            var rewardId = $button.data('reward-id');

            if (rewardId) {
                // Create mock reward object
                var reward = { id: rewardId };
                this.collectReward(reward, $button);
            }
        },

        /**
         * Collect a reward
         *
         * @param {Object} reward
         * @param {jQuery|HTMLElement} [buttonElement] Optional button element when called from static handler
         * @param {Event} [event] Optional event object when called directly from a click handler
         */
        collectReward: function (reward, buttonElement, event) {
            // Use provided button or find from event
            // Make sure we have a jQuery object, not a DOM element
            if (buttonElement) {
                buttonElement = $(buttonElement);
            } else if (event) {
                buttonElement = $(event.target);
            } else if (window.event) {
                // Fallback for older browsers
                buttonElement = $(window.event.target);
            } else {
                // Final fallback - find the button based on reward ID
                buttonElement = $('.js-collect-reward[data-reward-id="' + reward.id + '"]');
            }

            // Check if the button is already toggled (has "toggled" class or data attribute)
            const isToggled = buttonElement.hasClass('toggled') || buttonElement.data('is-toggled');

            // Initialize progress button with toggle functionality
            const progress = progressButton.init(buttonElement, {
                initialText: $t('Apply Voucher'),
                loadingText: $t('Applying...'),
                successText: $t('Applied!'),
                toggleMode: true,
                toggleOptions: {
                    toggleText: $t('Remove from Cart'),
                    toggleLoadingText: $t('Removing...'),
                    toggleSuccessText: $t('Removed!'),
                    isToggled: isToggled,
                    toggledClass: 'toggled'
                }
            });

            // Determine which API endpoint to use based on toggle state
            const endpoint = progress.isToggled() ? 'leat/coupon/remove' : 'leat/coupon/collect';

            // Call API to toggle the reward
            $.ajax({
                url: url.build(endpoint),
                type: 'POST',
                dataType: 'json',
                data: {
                    reward_id: reward.id
                },
                showLoader: false, // No need for default loader since we have progress button
                success: function(response) {
                    if (response.success) {
                        // Complete with success state and toggle the button
                        progress.complete(true, {
                            resetDelay: 2000
                        });

                        // Set appropriate message based on toggle state
                        const successMessage = progress.isToggled()
                            ? $t('Voucher applied to your cart')
                            : $t('Voucher removed from your cart');

                        // Show success message if provided or use our default
                        if (response.message) {
                            this.showSuccessMessage(response.message);
                        } else {
                            this.showSuccessMessage(successMessage);
                        }

                        // Refresh customer data sections
                        customerData.reload(['leat', 'cart']);

                        // Don't need an explicit reloadCoupons call here
                        // The customerData subscription will handle it
                    } else {
                        // Complete with error state (won't toggle)
                        progress.complete(false, {
                            errorText: $t('Failed')
                        });
                        this.showErrorMessage(response.message || $t('Unable to process voucher.'));
                    }
                }.bind(this),
                error: function(jqXHR) {
                    // Reset on failure (won't toggle)
                    progress.reset();

                    // Try to parse error response
                    let errorMessage = $t('An error occurred while processing the voucher.');

                    try {
                        const response = JSON.parse(jqXHR.responseText);
                        if (response && response.message) {
                            errorMessage = response.message;
                        }
                    } catch (e) {
                        // Use default error message if we can't parse the response
                        if (jqXHR.status === 400) {
                            errorMessage = $t('Invalid request. Please check the provided information.');
                        } else if (jqXHR.status === 500) {
                            errorMessage = $t('Server error. Please try again later.');
                        }
                    }

                    this.showErrorMessage(errorMessage);
                }.bind(this)
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
                try {
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
                } catch (e) {
                    console.error('Error showing success message', e);
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
                try {
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
                } catch (e) {
                    console.error('Error showing error message', e);
                    alert(message);
                }
            }
        }
    });
});
