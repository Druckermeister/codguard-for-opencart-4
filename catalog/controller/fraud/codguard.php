<?php

/**
 * CodGuard for OpenCart 4.x - Catalog Controller (Simplified)
 *
 * @package    CodGuard
 * @author     CodGuard
 * @copyright  2025 CodGuard
 * @license    GPL v2 or later
 * @version    2.9.0
 */

namespace Opencart\Catalog\Controller\Extension\Codguard\Fraud;

class Codguard extends \Opencart\System\Engine\Controller
{
    /**
     * Validate customer rating during checkout
     * Called via AJAX from checkout page (triggered by Continue button)
     */
    public function validateRating(): void
    {
        $json = array();

        $this->log->write('CodGuard [DEBUG]: validateRating() called');
        $this->log->write('CodGuard [DEBUG]: POST data: ' . json_encode($this->request->post));

        // Check if module is enabled
        $module_status = $this->config->get('module_codguard_status');
        $this->log->write('CodGuard [DEBUG]: Module status: ' . ($module_status ? 'ENABLED' : 'DISABLED'));

        if (!$module_status) {
            $json['success'] = true;
            $json['message'] = 'Module disabled';
            $this->log->write('CodGuard [INFO]: Validation skipped - module disabled');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Get email from request
        $email = isset($this->request->post['email']) ? $this->request->post['email'] : '';

        $this->log->write('CodGuard [DEBUG]: Email: ' . $email);

        if (empty($email)) {
            $json['success'] = true;
            $json['message'] = 'No email provided';
            $this->log->write('CodGuard [INFO]: Validation skipped - no email provided');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $this->log->write('CodGuard [INFO]: Getting customer rating - Email: ' . $email);

        $this->load->model('extension/codguard/fraud/codguard');

        // Get customer rating
        $rating = $this->model_extension_codguard_fraud_codguard->getCustomerRating($email);

        // If API failed, allow (fail-open)
        if ($rating === null) {
            // Clear any previous session data
            unset($this->session->data['codguard_rating']);
            unset($this->session->data['codguard_email']);

            $json['success'] = true;
            $json['message'] = 'API check failed, allowing checkout';
            $this->log->write('CodGuard [WARNING]: API check failed for ' . $email . ', allowing checkout (fail-open)');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        // Store rating and email in session for future use
        $this->session->data['codguard_rating'] = $rating;
        $this->session->data['codguard_email'] = $email;

        $this->log->write('CodGuard [INFO]: Stored rating in session - Email: ' . $email . ', Rating: ' . $rating);

        // Return success with rating
        $json['success'] = true;
        $json['rating'] = $rating;
        $json['message'] = 'Rating retrieved and stored';
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Debug test endpoint to manually test API connection
     * Access via: index.php?route=extension/codguard/fraud/codguard.testApi&email=test@example.com
     */
    public function testApi(): void
    {
        // Only allow in development/testing
        $email = isset($this->request->get['email']) ? $this->request->get['email'] : 'test@example.com';

        $this->log->write('CodGuard [TEST]: Manual API test initiated for email: ' . $email);

        $this->load->model('extension/codguard/fraud/codguard');

        $shop_id = $this->config->get('module_codguard_shop_id');
        $public_key = $this->config->get('module_codguard_public_key');
        $module_status = $this->config->get('module_codguard_status');
        $cod_methods = $this->config->get('module_codguard_cod_methods');
        $tolerance = $this->config->get('module_codguard_rating_tolerance');

        $output = array(
            'test_time' => date('Y-m-d H:i:s'),
            'email' => $email,
            'configuration' => array(
                'module_enabled' => $module_status ? 'YES' : 'NO',
                'shop_id' => $shop_id ?: 'NOT SET',
                'public_key' => $public_key ? substr($public_key, 0, 10) . '... (' . strlen($public_key) . ' chars)' : 'NOT SET',
                'cod_methods' => $cod_methods ? implode(', ', $cod_methods) : 'NONE',
                'rating_tolerance' => $tolerance . '%'
            ),
            'api_test' => array()
        );

        // Test API call
        $rating = $this->model_extension_codguard_fraud_codguard->getCustomerRating($email);

        if ($rating === null) {
            $output['api_test']['status'] = 'FAILED';
            $output['api_test']['message'] = 'API call failed - check error log for details';
        } else {
            $output['api_test']['status'] = 'SUCCESS';
            $output['api_test']['rating'] = $rating;
            $output['api_test']['tolerance'] = (float)$tolerance / 100;
            $output['api_test']['would_allow'] = $rating >= ((float)$tolerance / 100) ? 'YES' : 'NO';
        }

        $this->log->write('CodGuard [TEST]: Test results - ' . json_encode($output));

        // Output JSON response
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($output, JSON_PRETTY_PRINT));
    }
}
