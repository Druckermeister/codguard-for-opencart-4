/**
 * CodGuard for OpenCart 4.x - Frontend JavaScript
 *
 * This script validates customer ratings via AJAX when COD payment is selected
 *
 * @package    CodGuard
 * @version    2.4.2
 */

(function() {
    'use strict';

    // Configuration
    const CODGUARD_CONFIG = {
        ajaxUrl: 'index.php?route=extension/codguard/fraud/codguard.validateRating',
        checkInterval: 500, // Check every 500ms
        debug: true
    };

    // Log helper
    function log(message, data) {
        if (CODGUARD_CONFIG.debug && console && console.log) {
            console.log('[CodGuard] ' + message, data || '');
        }
    }

    // Check if COD is selected
    function isCODSelected() {
        // Check radio buttons for payment method
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        for (let radio of paymentRadios) {
            if (radio.checked && radio.value.toLowerCase().includes('cod')) {
                log('COD payment method selected:', radio.value);
                return true;
            }
        }

        // Check select dropdown for payment method
        const paymentSelect = document.querySelector('select[name="payment_method"]');
        if (paymentSelect && paymentSelect.value.toLowerCase().includes('cod')) {
            log('COD payment method selected (dropdown):', paymentSelect.value);
            return true;
        }

        return false;
    }

    // Get customer email
    function getCustomerEmail() {
        // Try various email field selectors
        const selectors = [
            'input[name="email"]',
            'input[name="guest_email"]',
            'input[name="payment_email"]',
            '#input-email',
            '#input-payment-email'
        ];

        for (let selector of selectors) {
            const emailInput = document.querySelector(selector);
            if (emailInput && emailInput.value) {
                log('Found email:', emailInput.value);
                return emailInput.value;
            }
        }

        return null;
    }

    // Get selected payment method code
    function getPaymentMethodCode() {
        // Check radio buttons
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        for (let radio of paymentRadios) {
            if (radio.checked) {
                return radio.value;
            }
        }

        // Check select dropdown
        const paymentSelect = document.querySelector('select[name="payment_method"]');
        if (paymentSelect) {
            return paymentSelect.value;
        }

        return null;
    }

    // Validate rating via AJAX
    function validateRating(email, paymentMethod) {
        log('Validating rating for:', { email, paymentMethod });

        return fetch(CODGUARD_CONFIG.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                email: email,
                payment_method: paymentMethod
            })
        })
        .then(response => response.json())
        .then(data => {
            log('Validation response:', data);
            return data;
        })
        .catch(error => {
            log('Validation error:', error);
            return { success: true, message: 'API error, allowing checkout' };
        });
    }

    // Show error message to user
    function showError(message) {
        log('Showing error:', message);

        // Try to find alert container
        let alertContainer = document.querySelector('.alert-container');

        if (!alertContainer) {
            // Create alert container if it doesn't exist
            alertContainer = document.createElement('div');
            alertContainer.className = 'alert-container';

            // Find checkout form or body
            const checkoutForm = document.querySelector('#checkout-confirm') ||
                                document.querySelector('#checkout') ||
                                document.body;

            checkoutForm.insertBefore(alertContainer, checkoutForm.firstChild);
        }

        // Create alert element
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible';
        alert.innerHTML = `
            <i class="fa-solid fa-circle-exclamation"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        // Clear previous alerts and add new one
        alertContainer.innerHTML = '';
        alertContainer.appendChild(alert);

        // Scroll to alert
        alertContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Main validation logic
    let lastValidation = null;
    let validationInProgress = false;

    function performValidation() {
        // Don't validate if already in progress
        if (validationInProgress) {
            return;
        }

        const email = getCustomerEmail();
        const paymentMethod = getPaymentMethodCode();

        // Only validate if we have both email and COD is selected
        if (!email || !isCODSelected()) {
            return;
        }

        // Check if we already validated this combination
        const validationKey = email + '|' + paymentMethod;
        if (lastValidation === validationKey) {
            return;
        }

        validationInProgress = true;
        lastValidation = validationKey;

        log('Starting validation...');

        validateRating(email, paymentMethod).then(result => {
            validationInProgress = false;

            if (!result.success && result.error) {
                // Block COD - show error
                showError(result.error);

                // Disable confirm button
                const confirmButton = document.querySelector('#button-confirm') ||
                                    document.querySelector('button[type="submit"]');
                if (confirmButton) {
                    confirmButton.disabled = true;
                    confirmButton.classList.add('disabled');
                }
            }
        });
    }

    // Hook into Confirm Order button ONLY - BLOCKING validation
    function hookConfirmButton() {
        // Only hook the final confirm order button
        const confirmButton = document.querySelector('#button-confirm');

        if (confirmButton && !confirmButton.dataset.codguardHooked) {
            confirmButton.dataset.codguardHooked = 'true';

            log('Found and hooking Confirm Order button');

            confirmButton.addEventListener('click', function(e) {
                log('===== CONFIRM ORDER CLICKED =====');

                // Check if COD is selected
                if (isCODSelected()) {
                    log('COD is selected, checking email...');
                    const email = getCustomerEmail();
                    const paymentMethod = getPaymentMethodCode();

                    if (!email) {
                        log('No email found, allowing order (fail-open)');
                        return true;
                    }

                    log('Email found: ' + email + ', BLOCKING to validate first');

                    // BLOCK the click event completely
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();

                    // Disable button to prevent double-clicks
                    confirmButton.disabled = true;
                    const originalText = confirmButton.textContent || confirmButton.innerHTML;
                    confirmButton.textContent = 'Validating...';

                    // Perform AJAX validation
                    validateRating(email, paymentMethod).then(result => {
                        log('Validation result:', result);

                        if (result.success) {
                            log('✓ Validation PASSED - allowing order');
                            // Re-enable and trigger the original order placement
                            confirmButton.disabled = false;
                            confirmButton.innerHTML = originalText;

                            // Remove our hook temporarily to avoid infinite loop
                            confirmButton.dataset.codguardHooked = 'false';

                            // Trigger the button click again
                            confirmButton.click();

                            // Re-add hook after a delay
                            setTimeout(() => {
                                confirmButton.dataset.codguardHooked = 'true';
                            }, 500);
                        } else {
                            log('✗ Validation FAILED - BLOCKING order');
                            confirmButton.disabled = false;
                            confirmButton.innerHTML = originalText;
                            showError(result.error || 'Cash on Delivery is not available for your account');
                        }
                    }).catch(error => {
                        log('Validation ERROR - allowing order (fail-open):', error);
                        confirmButton.disabled = false;
                        confirmButton.innerHTML = originalText;
                        confirmButton.dataset.codguardHooked = 'false';
                        confirmButton.click();
                        setTimeout(() => {
                            confirmButton.dataset.codguardHooked = 'true';
                        }, 500);
                    });

                    return false;
                } else {
                    log('COD not selected, allowing order');
                }
            }, true); // Use capture phase to intercept BEFORE any other handlers

            log('✓ Confirm Order button hooked successfully');
        }
    }

    // Watch for payment method changes
    function watchPaymentMethod() {
        // Watch radio buttons
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                log('Payment method changed:', this.value);
                if (isCODSelected()) {
                    performValidation();
                }
            });
        });

        // Watch select dropdown
        const paymentSelect = document.querySelector('select[name="payment_method"]');
        if (paymentSelect) {
            paymentSelect.addEventListener('change', function() {
                log('Payment method changed (dropdown):', this.value);
                if (isCODSelected()) {
                    performValidation();
                }
            });
        }
    }

    // Initialize
    function init() {
        log('CodGuard initializing...');
        log('Looking for payment method elements...');

        // Log what we find
        const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
        const paymentSelect = document.querySelector('select[name="payment_method"]');
        log('Found payment radios:', paymentRadios.length);
        log('Found payment select:', paymentSelect ? 'YES' : 'NO');

        // Log email fields
        const emailSelectors = [
            'input[name="email"]',
            'input[name="guest_email"]',
            'input[name="payment_email"]',
            '#input-email',
            '#input-payment-email'
        ];
        emailSelectors.forEach(selector => {
            const elem = document.querySelector(selector);
            if (elem) {
                log('Found email field:', selector, 'value:', elem.value);
            }
        });

        // Watch for payment method changes
        watchPaymentMethod();

        // Hook confirm button
        hookConfirmButton();

        // Periodic check (in case button is dynamically added)
        setInterval(function() {
            hookConfirmButton();
            watchPaymentMethod();
        }, 2000);

        log('CodGuard initialized');
    }

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Also try to init after a delay (for dynamic content)
    setTimeout(init, 2000);

})();
