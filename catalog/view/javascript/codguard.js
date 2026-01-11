/**
 * CodGuard for OpenCart 4.x - Frontend JavaScript
 *
 * This script validates customer ratings via AJAX on Continue button click
 *
 * @package    CodGuard
 * @version    2.5.0
 */

(function() {
    'use strict';

    // Configuration
    const CODGUARD_CONFIG = {
        ajaxUrl: 'index.php?route=extension/codguard/fraud/codguard.validateRating',
        debug: true
    };

    // Log helper
    function log(message, data) {
        if (CODGUARD_CONFIG.debug && console && console.log) {
            console.log('[CodGuard] ' + message, data || '');
        }
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
                // Make sure we return a string, not an object
                const value = radio.value;
                if (typeof value === 'object') {
                    return JSON.stringify(value);
                }
                return String(value);
            }
        }

        // Check select dropdown
        const paymentSelect = document.querySelector('select[name="payment_method"]');
        if (paymentSelect) {
            const value = paymentSelect.value;
            if (typeof value === 'object') {
                return JSON.stringify(value);
            }
            return String(value);
        }

        return 'unknown';
    }

    // Validate rating via AJAX (robust JSON parsing)
    function validateRating(email, paymentMethod) {
        log('Validating rating for:', { email, paymentMethod });

        // Ensure payment method is a string
        const pmString = (typeof paymentMethod === 'object') ? 'unknown' : String(paymentMethod);

        return fetch(CODGUARD_CONFIG.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                email: String(email),
                payment_method: pmString
            })
        })
        .then(response => {
            // If response not OK, log and allow (fail-open)
            if (!response.ok) {
                log('Validation response not OK:', response.status, response.statusText);
                return { success: true, message: 'API error, allowing checkout' };
            }

            // Read as text first to handle empty or invalid JSON bodies
            return response.text().then(text => {
                if (!text || text.trim().length === 0) {
                    log('Validation response empty - allowing checkout');
                    return { success: true, message: 'API error, allowing checkout' };
                }

                try {
                    const data = JSON.parse(text);
                    log('Validation response parsed JSON:', data);
                    return data;
                } catch (err) {
                    log('Validation response invalid JSON:', text, err);
                    return { success: true, message: 'API error, allowing checkout' };
                }
            });
        })
        .catch(error => {
            log('Validation error (fetch):', error);
            return { success: true, message: 'API error, allowing checkout' };
        });
    }

    // Show error message to user
    function showError(message) {
        log('Showing error:', message);

        // Mark blocked state (helps other handlers identify that CodGuard blocked)
        if (document.body && document.body.dataset) {
            document.body.dataset.codguardBlocked = 'true';
        }

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
        alert.className = 'alert alert-danger alert-dismissible codguard-alert';
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

    // Hook into Continue button (#button-register)
    function hookRegisterButton() {
        const registerButton = document.querySelector('#button-register');

        if (registerButton && !registerButton.dataset.codguardHooked) {
            registerButton.dataset.codguardHooked = 'true';

            log('Found and hooking Continue button (#button-register)');

            registerButton.addEventListener('click', function(e) {
                log('===== CONTINUE BUTTON CLICKED =====');

                const email = getCustomerEmail();
                const paymentMethod = getPaymentMethodCode();

                if (!email) {
                    log('No email found, allowing continue (fail-open)');
                    return true;
                }

                log('Email found: ' + email + ', checking reputation in background');

                // Don't block the continue action - just call API in background
                // The API will store the result in session for later use
                validateRating(email, paymentMethod).then(result => {
                    log('Background validation result:', result);
                    // We don't block here - the server will handle blocking COD later
                }).catch(error => {
                    log('Background validation error (ignored):', error);
                });

                // Allow the continue action to proceed
                return true;
            }, true); // Use capture phase

            log('✓ Continue button hooked successfully');
        }
    }

    // Initialize
    function init() {
        log('CodGuard initializing...');
        log('Looking for #button-register...');

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

        // Hook the Continue button
        hookRegisterButton();

        // Periodic check (in case button is dynamically added)
        setInterval(function() {
            hookRegisterButton();
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
