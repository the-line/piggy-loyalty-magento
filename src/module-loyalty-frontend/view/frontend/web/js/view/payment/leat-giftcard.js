define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/translate',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage',
    'Magento_Checkout/js/action/get-payment-information',
    'Magento_Checkout/js/action/recollect-shipping-rates',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/model/full-screen-loader' // For remove operation
], function (
    $,
    ko,
    Component,
    $t,
    quote,
    customer,
    customerData,
    errorProcessor,
    urlBuilder,
    storage,
    paymentInformationAction,
    recollectShippingRates,
    totals,
    fullScreenLoader
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Leat_LoyaltyFrontend/payment/leat-giftcard',
            giftCardCode: ko.observable(''),
            appliedGiftCards: ko.observableArray([]), // Will store objects like {id: 1, code: '....1234', amount_formatted: '$10.00'}
            isVisible: ko.observable(false),
            isLoading: ko.observable(false), // For apply button
            isRemovingCard: ko.observable(null), // To track which card is being removed for animation
            message: ko.observable('')
        },

        initialize: function () {
            this._super();
            let self = this;

            let leatData = customerData.get('leat');
            leatData.subscribe(function (data) {
                self.updateVisibility(data);
            });
            self.updateVisibility(leatData());

            return this;
        },

        loadAppliedGiftCards: function () {
            let self = this;

            // Ensure we only load if the component is actually visible
            if (!self.isVisible()) {
                self.appliedGiftCards([]); // Clear if not visible
                return;
            }

            let serviceUrl = urlBuilder.createUrl('/carts/mine/leat-applied-giftcards', {});
            // Use main loader for consistency
            self.isLoading(true);
            storage.get(serviceUrl)
                .done(function (response) {
                    if (response && Array.isArray(response)) {
                        self.appliedGiftCards(response);
                    } else {
                        // If response is not an array (e.g. error object from Magento) or not what we expect
                        self.appliedGiftCards([]);
                        console.error('Unexpected response format when loading applied gift cards:', response);
                    }
                })
                .fail(function (response) {
                    console.error('Error loading applied gift cards:', response);
                    self.appliedGiftCards([]); // Clear on error
                })
                .always(function () {
                    self.isLoading(false);
                });
        },

        updateVisibility: function (data) {
            let wasVisible = this.isVisible();
            let isNowVisible = false;

            if (data && typeof data.hasContact === 'boolean') {
                isNowVisible = data.hasContact;
            }

            this.isVisible(isNowVisible);

            if (isNowVisible && !wasVisible) {
                this.loadAppliedGiftCards();
            } else if (isNowVisible && this.appliedGiftCards().length === 0) {
                // If it was already visible but cards are empty (e.g. after a full page reload where Knockout re-initializes)
                this.loadAppliedGiftCards();
            } else if (!isNowVisible) {
                this.appliedGiftCards([]);
            }
        },

        applyGiftCard: function () {
            let self = this;
            let serviceUrl, payload;

            if (!this.giftCardCode() || this.giftCardCode().trim() === '') {
                self.message($t('Please enter a gift card code.'));
                return;
            }

            self.isLoading(true);
            self.message('');

            serviceUrl = urlBuilder.createUrl('/carts/mine/leat-giftcard-redemption', {});
            payload = {
                code: self.giftCardCode()
            };

            storage.post(
                serviceUrl,
                JSON.stringify(payload)
            ).done(function (response) {
                if (response && response.success && response.applied_card) {
                    // Check if card already exists by ID
                    let existingCardIndex = self.appliedGiftCards().findIndex(card => card.id === response.applied_card.id);

                    if (existingCardIndex !== -1) {
                        // Update existing card
                        self.appliedGiftCards.splice(existingCardIndex, 1, response.applied_card);
                    } else {
                        // Add new card
                        self.appliedGiftCards.push(response.applied_card);
                    }

                    self.giftCardCode(''); // Clear input
                    self.message(''); // Clear any previous error messages
                    self.refreshTotals();
                } else {
                    self.message(response.message || $t('Could not apply gift card.'));
                }
            }).fail(function (response) {
                errorProcessor.process(response, self.messageContainer || self.message);
            }).always(function () {
                self.isLoading(false);
            });
        },

        removeGiftCard: function (appliedCardData) {
            let self = this;
            let serviceUrl = urlBuilder.createUrl('/carts/mine/leat-giftcard-remove', {}); // New endpoint
            let payload = {
                cartId: quote.getQuoteId(),
                applied_card_id: appliedCardData.id // Pass the unique ID of the card instance
            };

            // Set the card being removed for animation
            self.isRemovingCard(appliedCardData.gift_card_code);

            // Use a small timeout to allow the animation to play before starting the loader
            setTimeout(function () {
                fullScreenLoader.startLoader();
                self.message('');

                storage.post(
                    serviceUrl,
                    JSON.stringify(payload)
                ).done(function (response) {
                    if (response && response.success) {
                        self.appliedGiftCards.remove(function (card) {
                            return card.id === appliedCardData.id;
                        });
                        self.refreshTotals();
                    } else {
                        self.message(response.message || $t('Could not remove gift card.'));
                    }
                }).fail(function (response) {
                    errorProcessor.process(response, self.messageContainer || self.message);
                }).always(function () {
                    self.isRemovingCard(null);
                    fullScreenLoader.stopLoader();
                });
            }, 300); // Match this with the animation duration in CSS
        },

        refreshTotals: function () {
            const deferred = $.Deferred();
            totals.isLoading(true);
            recollectShippingRates();
            // Refreshes payment information, which re-calculates totals
            paymentInformationAction(deferred);
            $.when(deferred).done(function () {
                totals.isLoading(false);
            });
        },

        getSectionTitle: function () {
            return $t('Apply Leat Gift Card');
        }
    });
});
