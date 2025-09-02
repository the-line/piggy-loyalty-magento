define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    return {
        /**
         * Initialize progress button
         *
         * @param {jQuery} button - The button element
         * @param {Object} options - Button text options
         * @param {string} options.initialText - Original button text (saved for reset)
         * @param {string} options.loadingText - Text to display while loading
         * @param {string} options.successText - Text to display after success
         * @param {boolean} options.toggleMode - Whether the button should toggle between two states
         * @param {Object} options.toggleOptions - Options for toggle mode
         * @param {string} options.toggleOptions.toggleText - Text to display for the toggle state
         * @param {string} options.toggleOptions.toggleLoadingText - Loading text for the toggle state
         * @param {string} options.toggleOptions.toggleSuccessText - Success text for the toggle state
         * @param {boolean} options.toggleOptions.isToggled - Whether the button is currently toggled
         * @returns {Object} Progress button controls
         */
        init: function (button, options) {
            options = options || {};
            // Store original text if not provided
            const initialText = options.initialText || button.text();
            button.data('original-text', initialText);
            
            // Toggle mode settings
            const toggleMode = options.toggleMode || false;
            const toggleOptions = options.toggleOptions || {};
            const toggleText = toggleOptions.toggleText || $t('Remove');
            const toggleLoadingText = toggleOptions.toggleLoadingText || $t('Removing...');
            const toggleSuccessText = toggleOptions.toggleSuccessText || $t('Removed!');
            
            // Store toggle state
            let isToggled = toggleOptions.isToggled || false;
            if (toggleMode) {
                button.data('is-toggled', isToggled);
                
                // If already toggled, show toggle text instead
                if (isToggled && button.text() === initialText) {
                    button.text(toggleText);
                }
            }
            
            // Set loading text based on toggle state
            const currentLoadingText = isToggled ? 
                (toggleLoadingText || $t('Removing...')) : 
                (options.loadingText || $t('Loading...'));
            
            // Set loading text and disable button
            button.attr('disabled', true);
            button.addClass('progress-button');
            button.text(currentLoadingText);
            
            // Add progress bar
            const progressContainer = $('<div class="progress"><div class="progress-bar"></div></div>');
            button.parent().append(progressContainer);
            
            // Get progress bar element
            const progressBar = progressContainer.find('.progress-bar');
            
            // Start progress animation
            progressBar.animate({width: '60%'}, 500);
            
            return {
                /**
                 * Complete the progress animation and update button state
                 *
                 * @param {boolean} success - Whether the operation was successful
                 * @param {Object} completeOptions - Options for customizing completion
                 */
                complete: function (success, completeOptions) {
                    completeOptions = completeOptions || {};
                    
                    // Determine appropriate text based on toggle state
                    let successText, resetText;
                    
                    if (toggleMode) {
                        if (isToggled) {
                            // If toggled, use toggle success text, and reset to initial text
                            successText = completeOptions.successText || toggleSuccessText;
                            resetText = completeOptions.resetText || initialText;
                        } else {
                            // If not toggled, use normal success text, and reset to toggle text
                            successText = completeOptions.successText || options.successText || $t('Success!');
                            resetText = completeOptions.resetText || toggleText;
                        }
                        
                        // Update the toggle state for next use
                        isToggled = !isToggled;
                        button.data('is-toggled', isToggled);
                    } else {
                        // Standard non-toggle mode
                        successText = completeOptions.successText || options.successText || $t('Success!');
                        resetText = completeOptions.resetText || initialText;
                    }
                    
                    const errorText = completeOptions.errorText || options.errorText || $t('Try Again');
                    const resetDelay = completeOptions.resetDelay || 3000;
                    
                    // Complete progress bar
                    progressBar.animate({width: '100%'}, 300);
                    
                    if (success) {
                        // Show temporary success state
                        button.text(successText);
                        button.removeClass('primary').addClass('success');
                        
                        // Reset button after a delay with new appropriate text based on toggle state
                        if (completeOptions.autoReset !== false) {
                            setTimeout(function () {
                                progressContainer.remove();
                                button.text(resetText);
                                button.removeClass('success').addClass('primary');
                                button.attr('disabled', false);
                                
                                // If there's a custom class for toggled state, apply it
                                if (toggleMode && toggleOptions.toggledClass) {
                                    if (isToggled) {
                                        button.addClass(toggleOptions.toggledClass);
                                    } else {
                                        button.removeClass(toggleOptions.toggledClass);
                                    }
                                }
                            }, resetDelay);
                        }
                    } else {
                        // Reset immediately for errors - goes back to previous state without toggling
                        progressContainer.remove();
                        button.text(errorText);
                        
                        // Reset back to previous state text (before the attempted toggle)
                        const previousStateText = isToggled ? initialText : toggleText;
                        
                        // Revert the toggle state since the operation failed
                        if (toggleMode) {
                            isToggled = !isToggled;
                            button.data('is-toggled', isToggled);
                        }
                        
                        // Reset button after a short delay
                        setTimeout(function () {
                            button.text(toggleMode ? previousStateText : initialText);
                            button.attr('disabled', false);
                        }, 1000);
                    }
                },
                
                /**
                 * Reset the button to its original state
                 */
                reset: function () {
                    progressContainer.remove();
                    
                    // Reset to appropriate text based on toggle state
                    if (toggleMode) {
                        button.text(isToggled ? toggleText : initialText);
                    } else {
                        button.text(initialText);
                    }
                    
                    button.removeClass('success').addClass('primary');
                    button.attr('disabled', false);
                },
                
                /**
                 * Get the current toggle state
                 *
                 * @returns {boolean} Whether the button is currently toggled
                 */
                isToggled: function () {
                    return button.data('is-toggled') || false;
                },
                
                /**
                 * Set toggle state without visual changes
                 *
                 * @param {boolean} toggled - Whether the button should be toggled
                 */
                setToggleState: function (toggled) {
                    if (toggleMode) {
                        isToggled = toggled;
                        button.data('is-toggled', isToggled);
                        button.text(isToggled ? toggleText : initialText);
                    }
                }
            };
        }
    };
});
