<?php
/**
 * CodGuard for OpenCart 4.x - Admin Controller
 *
 * @package    CodGuard
 * @author     CodGuard
 * @copyright  2025 CodGuard
 * @license    GPL v2 or later
 * @version    2.2.2
 */

namespace Opencart\Admin\Controller\Extension\Codguard\Fraud;

class Codguard extends \Opencart\System\Engine\Controller {
    private $error = array();

    /**
     * Main index/settings page
     */
    public function index(): void {
        $this->load->language('extension/codguard/module/codguard');
        $this->load->model('setting/setting');
        $this->load->model('extension/codguard/fraud/codguard');

        $this->document->setTitle($this->language->get('heading_title'));

        // Handle form submission
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_codguard', $this->request->post);

            // Also save to persistent settings table
            foreach ($this->request->post as $key => $value) {
                if (strpos($key, 'module_codguard_') === 0) {
                    $this->model_extension_codguard_fraud_codguard->saveSetting($key, $value);
                }
            }

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=fraud'));
        }

        // Load saved settings from persistent table if they exist
        $saved_settings = $this->model_extension_codguard_fraud_codguard->loadAllSettings();
        if (!empty($saved_settings)) {
            foreach ($saved_settings as $key => $value) {
                // Only restore if not already set in oc_setting
                if ($this->config->get($key) === null) {
                    $this->request->post[$key] = $value;
                }
            }
        }

        // Prepare data for view
        $data = array();

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=fraud')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/codguard/fraud/codguard', 'user_token=' . $this->session->data['user_token'])
        );

        // Language strings
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');

        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_shop_id'] = $this->language->get('entry_shop_id');
        $data['entry_public_key'] = $this->language->get('entry_public_key');
        $data['entry_private_key'] = $this->language->get('entry_private_key');
        $data['entry_rating_tolerance'] = $this->language->get('entry_rating_tolerance');
        $data['entry_rejection_message'] = $this->language->get('entry_rejection_message');
        $data['entry_good_status'] = $this->language->get('entry_good_status');
        $data['entry_refused_status'] = $this->language->get('entry_refused_status');
        $data['entry_cod_methods'] = $this->language->get('entry_cod_methods');

        $data['help_shop_id'] = $this->language->get('help_shop_id');
        $data['help_public_key'] = $this->language->get('help_public_key');
        $data['help_private_key'] = $this->language->get('help_private_key');
        $data['help_rating_tolerance'] = $this->language->get('help_rating_tolerance');
        $data['help_rejection_message'] = $this->language->get('help_rejection_message');
        $data['help_good_status'] = $this->language->get('help_good_status');
        $data['help_refused_status'] = $this->language->get('help_refused_status');
        $data['help_cod_methods'] = $this->language->get('help_cod_methods');

        $data['tab_api_config'] = $this->language->get('tab_api_config');
        $data['tab_order_status'] = $this->language->get('tab_order_status');
        $data['tab_payment_methods'] = $this->language->get('tab_payment_methods');
        $data['tab_rating_settings'] = $this->language->get('tab_rating_settings');
        $data['tab_statistics'] = $this->language->get('tab_statistics');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        // Error messages
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['shop_id'])) {
            $data['error_shop_id'] = $this->error['shop_id'];
        } else {
            $data['error_shop_id'] = '';
        }

        if (isset($this->error['public_key'])) {
            $data['error_public_key'] = $this->error['public_key'];
        } else {
            $data['error_public_key'] = '';
        }

        if (isset($this->error['private_key'])) {
            $data['error_private_key'] = $this->error['private_key'];
        } else {
            $data['error_private_key'] = '';
        }

        // Form action and cancel URLs
        $data['action'] = $this->url->link('extension/codguard/fraud/codguard', 'user_token=' . $this->session->data['user_token']);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=fraud');

        // Get current settings
        if (isset($this->request->post['module_codguard_status'])) {
            $data['module_codguard_status'] = $this->request->post['module_codguard_status'];
        } else {
            $data['module_codguard_status'] = $this->config->get('module_codguard_status');
        }

        if (isset($this->request->post['module_codguard_shop_id'])) {
            $data['module_codguard_shop_id'] = $this->request->post['module_codguard_shop_id'];
        } else {
            $data['module_codguard_shop_id'] = $this->config->get('module_codguard_shop_id');
        }

        if (isset($this->request->post['module_codguard_public_key'])) {
            $data['module_codguard_public_key'] = $this->request->post['module_codguard_public_key'];
        } else {
            $data['module_codguard_public_key'] = $this->config->get('module_codguard_public_key');
        }

        if (isset($this->request->post['module_codguard_private_key'])) {
            $data['module_codguard_private_key'] = $this->request->post['module_codguard_private_key'];
        } else {
            $data['module_codguard_private_key'] = $this->config->get('module_codguard_private_key');
        }

        if (isset($this->request->post['module_codguard_rating_tolerance'])) {
            $data['module_codguard_rating_tolerance'] = $this->request->post['module_codguard_rating_tolerance'];
        } else {
            $data['module_codguard_rating_tolerance'] = $this->config->get('module_codguard_rating_tolerance') ?: 35;
        }

        if (isset($this->request->post['module_codguard_rejection_message'])) {
            $data['module_codguard_rejection_message'] = $this->request->post['module_codguard_rejection_message'];
        } else {
            $data['module_codguard_rejection_message'] = $this->config->get('module_codguard_rejection_message') ?: 'Unfortunately, we cannot offer Cash on Delivery for this order. Please choose a different payment method.';
        }

        if (isset($this->request->post['module_codguard_good_status'])) {
            $data['module_codguard_good_status'] = $this->request->post['module_codguard_good_status'];
        } else {
            $data['module_codguard_good_status'] = $this->config->get('module_codguard_good_status') ?: 5; // Complete
        }

        if (isset($this->request->post['module_codguard_refused_status'])) {
            $data['module_codguard_refused_status'] = $this->request->post['module_codguard_refused_status'];
        } else {
            $data['module_codguard_refused_status'] = $this->config->get('module_codguard_refused_status') ?: 7; // Canceled
        }

        if (isset($this->request->post['module_codguard_cod_methods'])) {
            $data['module_codguard_cod_methods'] = $this->request->post['module_codguard_cod_methods'];
        } else {
            $data['module_codguard_cod_methods'] = $this->config->get('module_codguard_cod_methods') ?: array();
        }

        // Get order statuses
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Get payment methods
        $this->load->model('setting/extension');
        $payment_methods = $this->model_setting_extension->getExtensionsByType('payment');
        $data['payment_methods'] = array();

        foreach ($payment_methods as $payment) {
            $this->load->language('extension/' . $payment['extension'] . '/payment/' . $payment['code']);

            if ($this->config->get('payment_' . $payment['code'] . '_status')) {
                $data['payment_methods'][] = array(
                    'code' => $payment['code'],
                    'name' => $this->language->get('heading_title')
                );
            }
        }

        // Get statistics
        $data['statistics'] = $this->model_extension_codguard_fraud_codguard->getStatistics();
        $data['recent_blocks'] = $this->model_extension_codguard_fraud_codguard->getRecentBlocks(10);

        // Check if plugin is properly configured
        $data['is_configured'] = !empty($data['module_codguard_shop_id']) &&
                                 !empty($data['module_codguard_public_key']) &&
                                 !empty($data['module_codguard_private_key']);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/codguard/fraud/codguard', $data));
    }

    /**
     * Install method - Called automatically by OpenCart when installing extension
     */
    public function install(): void {
        // Load the model for database operations
        $this->load->model('extension/codguard/fraud/codguard');
        $this->model_extension_codguard_fraud_codguard->install();

        // Add permissions to all user groups (OpenCart 4.x JSON format)
        $this->db->query("
            UPDATE `" . DB_PREFIX . "user_group`
            SET permission = JSON_ARRAY_APPEND(
                JSON_ARRAY_APPEND(permission, '$.access', 'extension/codguard/fraud/codguard'),
                '$.modify',
                'extension/codguard/fraud/codguard'
            )
        ");
    }

    /**
     * Uninstall method - Called automatically by OpenCart when uninstalling extension
     */
    public function uninstall(): void {
        // Load the model for database operations
        $this->load->model('extension/codguard/fraud/codguard');
        $this->model_extension_codguard_fraud_codguard->uninstall();

        // Note: We don't remove permissions on uninstall to avoid breaking things
        // Admins can manually remove from System > Users > User Groups if needed
    }

    /**
     * Validate form data
     */
    protected function validate(): bool {
        if (!$this->user->hasPermission('modify', 'extension/codguard/fraud/codguard')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (empty($this->request->post['module_codguard_shop_id'])) {
            $this->error['shop_id'] = $this->language->get('error_shop_id');
        }

        if (empty($this->request->post['module_codguard_public_key'])) {
            $this->error['public_key'] = $this->language->get('error_public_key');
        } elseif (strlen($this->request->post['module_codguard_public_key']) < 10) {
            $this->error['public_key'] = $this->language->get('error_public_key_length');
        }

        if (empty($this->request->post['module_codguard_private_key'])) {
            $this->error['private_key'] = $this->language->get('error_private_key');
        } elseif (strlen($this->request->post['module_codguard_private_key']) < 10) {
            $this->error['private_key'] = $this->language->get('error_private_key_length');
        }

        return !$this->error;
    }

    /**
     * Event handler for admin order status changes
     * Called when order status is changed from admin panel
     */
    public function eventOrderStatusChange(string &$route, array &$args, mixed &$output): void {
        // Check if module is enabled
        if (!$this->config->get('module_codguard_status')) {
            return;
        }

        // Get order ID and new status from arguments
        $order_id = $args[0];
        $order_status_id = $args[1];

        // Load the catalog model (fraud detection logic is in catalog)
        $this->load->model('extension/codguard/fraud/codguard');
        $this->model_extension_codguard_fraud_codguard->queueOrder($order_id, $order_status_id);
    }
}
