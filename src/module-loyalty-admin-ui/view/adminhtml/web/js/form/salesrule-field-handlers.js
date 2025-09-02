define([
    'uiRegistry',
    'jquery',
    'domReady!'
], function (registry, $) 
{
    'use strict';

    // Constants for action types
    const ACTION_ADD_GIFT_PRODUCTS = 'add_gift_products';


    /**
     * Handle simple action changes
     */
    function handleSimpleActionChange(simpleAction, giftSkusField) {
        if (simpleAction === ACTION_ADD_GIFT_PRODUCTS) {
            giftSkusField.show();
        } else {
            giftSkusField.hide();
        }
    }

    Promise.all([
        new Promise(resolve => registry.async('sales_rule_form.sales_rule_form.actions.simple_action')(resolve)),
        new Promise(resolve => registry.async('sales_rule_form.sales_rule_form.actions.gift_skus')(resolve))
    ]).then(function (components) {
        const [
            simpleActionField,
            giftSkusField
        ] = components;

        handleSimpleActionChange(simpleActionField.value(), giftSkusField);
        simpleActionField.value.subscribe(value => handleSimpleActionChange(value, giftSkusField))
    });
});
