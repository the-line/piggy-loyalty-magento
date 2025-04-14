define(
    [
        'knockout',
        'Magento_Checkout/js/view/summary/abstract-total',
        'Magento_Checkout/js/model/totals',
        'Magento_Checkout/js/model/quote'
    ],
    function (ko, Component, totals, quote) {
        return Component.extend({
            totals: quote.getTotals(),
            quote: quote,

            initialize: function () {
                this._super();

                this.creditCount = ko.computed(function () {
                    let creditCount = 0,
                        segment = totals.getSegment('leat_loyalty');

                    if (this.totals() && segment) {
                        creditCount = segment.value;
                    }

                    return creditCount;
                }, this);

                this.creditLabel = ko.computed(function () {
                    let label = "",
                        segment = totals.getSegment('leat_loyalty');

                    if (this.totals() && segment) {
                        label = segment.title;
                    }

                    return label
                }, this);
            },

            isDisplayed: function () {
                return this.creditCount();
            },

            getValue: function () {
                return this.creditCount() + " " + this.creditLabel();
            },

            isCoinCountPresent: function () {
                return this.creditCount() > 0;
            }
        });
    }
);
