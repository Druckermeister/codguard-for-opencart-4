<?php
/**
 * CodGuard for OpenCart 4.x - Catalog Controller
 *
 * @package    CodGuard
 * @author     CodGuard
 * @copyright  2025 CodGuard
 * @license    GPL v2 or later
 * @version    2.2.2
 */

namespace Opencart\Catalog\Controller\Extension\Codguard\Module;

class Codguard extends \Opencart\System\Engine\Controller {

    /**
     * Event handler for order status changes
     * Called when order history is added (status change)
     */
    public function eventOrderStatusChange(string &$route, array &$args, mixed &$output): void {
        // Check if module is enabled
        if (!$this->config->get('module_codguard_status')) {
            return;
        }

        // Get order ID and new status from arguments
        $order_id = $args[0];
        $order_status_id = $args[1];

        $this->load->model('extension/codguard/module/codguard');
        $this->model_extension_codguard_module_codguard->queueOrder($order_id, $order_status_id);
    }

    /**
     * Validate customer rating during checkout
     * Called via AJAX from checkout page
     */
    public function validateRating(): void {
        $json = array();

        // Check if module is enabled
        if (!$this->config->get('module_codguard_status')) {
            $json['success'] = true;
            $json['message'] = 'Module disabled';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Get email from request
        $email = isset($this->request->post['email']) ? $this->request->post['email'] : '';
        $payment_method = isset($this->request->post['payment_method']) ? $this->request->post['payment_method'] : '';

        if (empty($email)) {
            $json['success'] = true;
            $json['message'] = 'No email provided';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Check if payment method is a COD method
        $cod_methods = $this->config->get('module_codguard_cod_methods');
        if (!$cod_methods || !in_array($payment_method, $cod_methods)) {
            $json['success'] = true;
            $json['message'] = 'Not a COD payment method';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $this->load->model('extension/codguard/module/codguard');
        $this->load->language('extension/codguard/module/codguard');

        // Get customer rating
        $rating = $this->model_extension_codguard_module_codguard->getCustomerRating($email);

        // If API failed, allow (fail-open)
        if ($rating === null) {
            $json['success'] = true;
            $json['message'] = 'API check failed, allowing checkout';
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Compare to tolerance
        $tolerance = (float)$this->config->get('module_codguard_rating_tolerance') / 100;

        if ($rating < $tolerance) {
            // Block COD
            $this->model_extension_codguard_module_codguard->logBlockEvent(
                $email,
                $rating,
                $this->request->server['REMOTE_ADDR']
            );

            $json['success'] = false;
            $json['error'] = $this->config->get('module_codguard_rejection_message') ?: $this->language->get('error_rating_low');
        } else {
            $json['success'] = true;
            $json['message'] = 'Rating OK';
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Inject checkout validation JavaScript
     * Called via event hook on footer render
     */
    public function injectCheckoutScript(string &$route, array &$data, mixed &$output): void {
        // Only inject on checkout pages
        if (!isset($this->request->get['route']) || strpos($this->request->get['route'], 'checkout') === false) {
            return;
        }

        // Check if module is enabled
        if (!$this->config->get('module_codguard_status')) {
            return;
        }

        $cod_methods = $this->config->get('module_codguard_cod_methods');
        if (empty($cod_methods)) {
            return;
        }

        // Build the validation URL
        $validation_url = $this->url->link('extension/codguard/module/codguard.validateRating', '', true);

        // Add inline script by appending to output
        if (!isset($data['codguard_injected'])) {
            $data['codguard_injected'] = true;

            $script = '<script type="text/javascript">
// CodGuard COD Validation
(function() {
    var codguardCodMethods = ' . json_encode($cod_methods) . ';
    var codguardValidationUrl = ' . json_encode($validation_url) . ';
    var codguardLastCheckedEmail = null;
    var codguardLastCheckedMethod = null;

    function codguardValidateRating() {
        var email = null;
        var emailInput = document.querySelector(\'input[name="email"]\');
        if (emailInput) email = emailInput.value;

        var paymentMethod = null;
        var paymentInput = document.querySelector(\'input[name="payment_method"]:checked\');
        if (paymentInput) paymentMethod = paymentInput.value;

        if (!email || !paymentMethod) return;
        if (codguardCodMethods.indexOf(paymentMethod) === -1) return;
        if (email === codguardLastCheckedEmail && paymentMethod === codguardLastCheckedMethod) return;

        codguardLastCheckedEmail = email;
        codguardLastCheckedMethod = paymentMethod;

        fetch(codguardValidationUrl, {
            method: \'POST\',
            headers: { \'Content-Type\': \'application/x-www-form-urlencoded\' },
            body: \'email=\' + encodeURIComponent(email) + \'&payment_method=\' + encodeURIComponent(paymentMethod)
        })
        .then(function(response) { return response.json(); })
        .then(function(json) {
            if (!json.success && json.error) {
                alert(json.error);
                var confirmButton = document.querySelector(\'#button-confirm\');
                if (confirmButton) confirmButton.disabled = true;
            }
        })
        .catch(function(error) { console.log(\'CodGuard validation error:\', error); });
    }

    document.addEventListener(\'change\', function(e) {
        if (e.target.name === \'payment_method\') codguardValidateRating();
    });

    document.addEventListener(\'blur\', function(e) {
        if (e.target.name === \'email\') codguardValidateRating();
    }, true);

    if (document.readyState === \'loading\') {
        document.addEventListener(\'DOMContentLoaded\', function() { setTimeout(codguardValidateRating, 1000); });
    } else {
        setTimeout(codguardValidateRating, 1000);
    }
})();
</script>';

            // Append script to output instead of $data
            $output .= $script;
        }
    }

    /**
     * Hook into checkout validation
     * This is called before order is confirmed
     */
    public function validateCheckout(string &$route, array &$data, mixed &$output): void {
        // Check if module is enabled
        if (!$this->config->get('module_codguard_status')) {
            return;
        }

        // Get payment method
        if (!isset($this->session->data['payment_method'])) {
            return;
        }

        $payment_code = $this->session->data['payment_method']['code'];

        // Check if it's a COD method
        $cod_methods = $this->config->get('module_codguard_cod_methods');
        if (!$cod_methods || !in_array($payment_code, $cod_methods)) {
            return;
        }

        // Get customer email
        $email = '';
        if (isset($this->session->data['guest'])) {
            $email = $this->session->data['guest']['email'];
        } elseif ($this->customer->isLogged()) {
            $email = $this->customer->getEmail();
        }

        if (empty($email)) {
            return;
        }

        $this->load->model('extension/codguard/module/codguard');
        $this->load->language('extension/codguard/module/codguard');

        // Get customer rating
        $rating = $this->model_extension_codguard_module_codguard->getCustomerRating($email);

        // If API failed, allow (fail-open)
        if ($rating === null) {
            return;
        }

        // Compare to tolerance
        $tolerance = (float)$this->config->get('module_codguard_rating_tolerance') / 100;

        if ($rating < $tolerance) {
            // Block COD
            $this->model_extension_codguard_module_codguard->logBlockEvent(
                $email,
                $rating,
                $this->request->server['REMOTE_ADDR']
            );

            // Redirect back to checkout with error
            $this->session->data['error'] = $this->config->get('module_codguard_rejection_message') ?: $this->language->get('error_rating_low');
            $this->response->redirect($this->url->link('checkout/checkout'));
        }
    }
}
