
define([
    'jquery',
    'mage/url',
    'mage/translate',
    'Magento_Ui/js/modal/modal'
], function ($, urlBuilder, $t, modal) {
    'use strict';

    return function (config) {
        var modalInstance = null;

        /**
         * Get URL parameter
         *
         * @param {String} name
         * @returns {String}
         */
        function getUrlParameter(name) {
            name = name.replace(/[[]/, '\\[').replace(/[\]]/, '\\]');
            var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
            var results = regex.exec(location.search);
            return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }

        /**
         * Check for referral code in URL and show popup if needed
         */
        function initReferralPopup() {
            var referralCode = getUrlParameter('___referral-code');

            if (referralCode) {
                // Store referral code in session storage to avoid showing popup on page reload
                if (!sessionStorage.getItem('referral_shown_' + referralCode)) {
                    // Fetch popup content
                    $.ajax({
                        url: urlBuilder.build('leat/referral/popup'),
                        type: 'GET',
                        data: {
                            referral_code: referralCode
                        },
                        success: function (response) {
                            if (response) {
                                // Create modal container if it doesn't exist
                                if (!$('#leat-referral-modal').length) {
                                    $('body').append('<div id="leat-referral-modal" class="leat-referral-modal"></div>');
                                }

                                // Add modal content
                                $('#leat-referral-modal').html(response);

                                // Initialize Magento modal
                                modalInstance = modal({
                                    type: 'popup',
                                    title: false,
                                    modalClass: 'leat-referral-modal-container',
                                    responsive: true,
                                    innerScroll: true,
                                    clickableOverlay: true,
                                    buttons: [],
                                    closed: function() {
                                        // Cleanup if needed
                                    }
                                }, $('#leat-referral-modal'));

                                // Open modal
                                modalInstance.openModal();

                                // Mark as shown in session storage
                                sessionStorage.setItem('referral_shown_' + referralCode, 'true');

                                // Setup event handlers
                                setupPopupEvents(referralCode);
                            }
                        }
                    });
                }
            }
        }

        /**
         * Setup popup event handlers
         *
         * @param {String} referralCode
         */
        function setupPopupEvents(referralCode) {
            // Close button handler
            $(document).on('click', '.leat-referral-popup-close', function() {
                if (modalInstance) {
                    modalInstance.closeModal();
                }
            });

            // Form submission handler
            $(document).on('submit', '#leat-referral-form', function(e) {
                e.preventDefault();

                var form = $(this);
                var submitBtn = form.find('button[type="submit"]');
                var email = form.find('input[name="email"]').val();

                submitBtn.prop('disabled', true).addClass('loading');

                $.ajax({
                    url: urlBuilder.build('leat/referral/subscribe'),
                    type: 'POST',
                    data: form.serialize(),
                    success: function(response) {
                        submitBtn.prop('disabled', false).removeClass('loading');

                        if (response.success) {
                            form.hide();
                            $('.leat-referral-popup-success').html(response.message).show();

                            // Auto close after success
                            setTimeout(function() {
                                if (modalInstance) {
                                    modalInstance.closeModal();
                                }
                            }, 5000);
                        } else {
                            $('.leat-referral-popup-error').html(response.message).show();

                            setTimeout(function() {
                                $('.leat-referral-popup-error').hide();
                            }, 3000);
                        }
                    },
                    error: function() {
                        submitBtn.prop('disabled', false).removeClass('loading');
                        $('.leat-referral-popup-error').html(
                            $t('An error occurred. Please try again later.')
                        ).show();

                        setTimeout(function() {
                            $('.leat-referral-popup-error').hide();
                        }, 3000);
                    }
                });
            });
        }

        // Initialize popup on page load
        $(function() {
            initReferralPopup();
        });
    };
});
