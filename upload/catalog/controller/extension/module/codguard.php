<?php
/**
 * CodGuard for OpenCart - Catalog Controller
 *
 * @package    CodGuard
 * @author     CodGuard
 * @copyright  2025 CodGuard
 * @license    GPL v2 or later
 * @version    1.0.0
 */

class ControllerExtensionModuleCodguard extends Controller {

    /**
     * Event handler for order status changes
     * Called when order history is added (status change)
     */
    public function eventOrderStatusChange(&$route, &$args, &$output) {
        // Check if module is enabled
        if (!$this->config->get('module_codguard_status')) {
            return;
        }

        // Get order ID and new status from arguments
        $order_id = $args[0];
        $order_status_id = $args[1];

        $this->load->model('extension/module/codguard');
        $this->model_extension_module_codguard->queueOrder($order_id, $order_status_id);
    }

    /**
     * Validate customer rating during checkout
     * Called via AJAX from checkout page
     */
    public function validateRating() {
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

        $this->load->model('extension/module/codguard');
        $this->load->language('extension/module/codguard');

        // Get customer rating
        $rating = $this->model_extension_module_codguard->getCustomerRating($email);

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
            $this->model_extension_module_codguard->logBlockEvent(
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
     * Hook into checkout validation
     * This is called before order is confirmed
     */
    public function validateCheckout(&$route, &$data, &$output) {
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

        $this->load->model('extension/module/codguard');
        $this->load->language('extension/module/codguard');

        // Get customer rating
        $rating = $this->model_extension_module_codguard->getCustomerRating($email);

        // If API failed, allow (fail-open)
        if ($rating === null) {
            return;
        }

        // Compare to tolerance
        $tolerance = (float)$this->config->get('module_codguard_rating_tolerance') / 100;

        if ($rating < $tolerance) {
            // Block COD
            $this->model_extension_module_codguard->logBlockEvent(
                $email,
                $rating,
                $this->request->server['REMOTE_ADDR']
            );

            // Redirect back to checkout with error
            $this->session->data['error'] = $this->config->get('module_codguard_rejection_message') ?: $this->language->get('error_rating_low');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }
}
