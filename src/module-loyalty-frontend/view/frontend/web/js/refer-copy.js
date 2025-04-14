
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    return function (config, element) {
        $(element).on('click', function () {
            var copyText = document.getElementById('leat-referral-link');

            // Select the text field
            copyText.select();
            copyText.setSelectionRange(0, 99999); // For mobile devices

            // Copy the text inside the text field
            document.execCommand('copy');

            // Show success message
            $('<div class="leat-copy-success"></div>')
                .text($t('Link copied!'))
                .appendTo('body')
                .delay(1500)
                .fadeOut(300, function () {
                    $(this).remove();
                });
        });
    };
});
