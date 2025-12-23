<?php
/**
 * CodGuard for OpenCart 4.x - Admin Model
 *
 * @package    CodGuard
 * @author     CodGuard
 * @copyright  2025 CodGuard
 * @license    GPL v2 or later
 * @version    2.8.0
 */

namespace Opencart\Admin\Model\Extension\Codguard\Fraud;

class Codguard extends \Opencart\System\Engine\Model {

    /**
     * Install extension
     */
    public function install(): void {
        // Create block events table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codguard_block_events` (
                `event_id` int(11) NOT NULL AUTO_INCREMENT,
                `email` varchar(255) NOT NULL,
                `rating` decimal(5,4) NOT NULL,
                `timestamp` int(11) NOT NULL,
                `ip_address` varchar(45) DEFAULT NULL,
                PRIMARY KEY (`event_id`),
                KEY `timestamp` (`timestamp`),
                KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // Create order queue table for bundled sync
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codguard_order_queue` (
                `queue_id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) NOT NULL,
                `order_data` text NOT NULL,
                `created_at` datetime NOT NULL,
                `sent_at` datetime DEFAULT NULL,
                `status` enum('pending','sent','failed') DEFAULT 'pending',
                PRIMARY KEY (`queue_id`),
                UNIQUE KEY `order_id` (`order_id`),
                KEY `status` (`status`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // Create settings table to persist configuration even after uninstall
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codguard_settings` (
                `setting_id` int(11) NOT NULL AUTO_INCREMENT,
                `key` varchar(64) NOT NULL,
                `value` text NOT NULL,
                `serialized` tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`setting_id`),
                UNIQUE KEY `key` (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // Migrate existing settings from oc_setting to codguard_settings
        $this->migrateSettings();

        // Register event handlers
        $this->load->model('setting/event');

        // MAIN EVENT: Filter payment methods when they're loaded
        // This is the primary mechanism - it prevents COD from even appearing for low-rated customers
        $this->model_setting_event->addEvent([
            'code' => 'codguard_filter_payment_methods',
            'description' => 'CodGuard filter payment methods based on customer rating',
            'trigger' => 'catalog/model/checkout/payment_method/getMethods/after',
            'action' => 'extension/codguard/fraud/codguard|filterPaymentMethods',
            'status' => 1,
            'sort_order' => 0
        ]);

        // Payment method controller validation (try to catch AJAX save) - BACKUP
        $this->model_setting_event->addEvent([
            'code' => 'codguard_payment_method_save',
            'description' => 'CodGuard payment method validation',
            'trigger' => 'catalog/controller/checkout/payment_method.save/before',
            'action' => 'extension/codguard/fraud/codguard.validatePaymentMethodController',
            'status' => 1,
            'sort_order' => 0
        ]);

        // Also try model-level event - BACKUP
        $this->model_setting_event->addEvent([
            'code' => 'codguard_payment_method_model',
            'description' => 'CodGuard payment method model validation',
            'trigger' => 'catalog/model/checkout/payment_method/setPaymentMethod/before',
            'action' => 'extension/codguard/fraud/codguard.validatePaymentMethod',
            'status' => 1,
            'sort_order' => 0
        ]);

        // Order status change event
        $this->model_setting_event->addEvent([
            'code' => 'codguard_order_status_change',
            'description' => 'CodGuard order status change handler',
            'trigger' => 'catalog/model/checkout/order/addHistory/after',
            'action' => 'extension/codguard/fraud/codguard.eventOrderStatusChange',
            'status' => 1,
            'sort_order' => 0
        ]);

        // Checkout validation event (server-side validation before order confirmation)
        // Note: We use catalog/model/checkout/order/addOrder/before which is called
        // right before the order is created, ensuring payment method is available
        $this->model_setting_event->addEvent([
            'code' => 'codguard_checkout_validation',
            'description' => 'CodGuard checkout validation before order creation',
            'trigger' => 'catalog/model/checkout/order/addOrder/before',
            'action' => 'extension/codguard/fraud/codguard.validateCheckout',
            'status' => 1,
            'sort_order' => 0
        ]);

        // JavaScript injection event (adds CodGuard JS to checkout pages)
        $this->model_setting_event->addEvent([
            'code' => 'codguard_add_javascript',
            'description' => 'CodGuard JavaScript injection for checkout pages',
            'trigger' => 'catalog/view/*/before',
            'action' => 'extension/codguard/fraud/codguard.addJavaScript',
            'status' => 1,
            'sort_order' => 0
        ]);

        // Also try catalog/controller/checkout/confirm/before as fallback
        $this->model_setting_event->addEvent([
            'code' => 'codguard_checkout_confirm',
            'description' => 'CodGuard checkout confirm handler (fallback)',
            'trigger' => 'catalog/controller/checkout/confirm/before',
            'action' => 'extension/codguard/fraud/codguard.validateCheckoutConfirm',
            'status' => 1,
            'sort_order' => 0
        ]);

        // Also catch the success page (order has been created, but we can still validate)
        $this->model_setting_event->addEvent([
            'code' => 'codguard_checkout_success',
            'description' => 'CodGuard checkout success handler (order created)',
            'trigger' => 'catalog/controller/checkout/success/before',
            'action' => 'extension/codguard/fraud/codguard.validateAfterOrder',
            'status' => 1,
            'sort_order' => 0
        ]);

        // Log installation
        $this->log->write('CodGuard extension installed successfully - 8 events registered (including payment method filter)');
    }

    /**
     * Uninstall extension
     */
    public function uninstall(): void {
        // Remove event handlers
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('codguard_filter_payment_methods');
        $this->model_setting_event->deleteEventByCode('codguard_payment_method_save');
        $this->model_setting_event->deleteEventByCode('codguard_payment_method_model');
        $this->model_setting_event->deleteEventByCode('codguard_order_status_change');
        $this->model_setting_event->deleteEventByCode('codguard_checkout_validation');
        $this->model_setting_event->deleteEventByCode('codguard_add_javascript');
        $this->model_setting_event->deleteEventByCode('codguard_checkout_confirm');
        $this->model_setting_event->deleteEventByCode('codguard_checkout_success');

        // Note: We don't drop tables to preserve data
        // Drop tables manually if needed:
        // DROP TABLE IF EXISTS `" . DB_PREFIX . "codguard_block_events`;
        // DROP TABLE IF EXISTS `" . DB_PREFIX . "codguard_order_queue`;

        $this->log->write('CodGuard extension uninstalled');
    }

    /**
     * Get statistics for different time periods
     */
    public function getStatistics(): array {
        $stats = array(
            'today' => 0,
            'week' => 0,
            'month' => 0,
            'all' => 0
        );

        $today_start = strtotime('today');
        $week_start = strtotime('-7 days');
        $month_start = strtotime('-30 days');

        // Today
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "codguard_block_events`
            WHERE timestamp >= " . (int)$today_start . "
        ");
        $stats['today'] = $query->row['total'];

        // Last 7 days
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "codguard_block_events`
            WHERE timestamp >= " . (int)$week_start . "
        ");
        $stats['week'] = $query->row['total'];

        // Last 30 days
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "codguard_block_events`
            WHERE timestamp >= " . (int)$month_start . "
        ");
        $stats['month'] = $query->row['total'];

        // All time
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "codguard_block_events`
        ");
        $stats['all'] = $query->row['total'];

        return $stats;
    }

    /**
     * Get recent block events
     */
    public function getRecentBlocks(int $limit = 10): array {
        $query = $this->db->query("
            SELECT email, rating, timestamp, ip_address
            FROM `" . DB_PREFIX . "codguard_block_events`
            ORDER BY timestamp DESC
            LIMIT " . (int)$limit . "
        ");

        return $query->rows;
    }

    /**
     * Add block event
     */
    public function addBlockEvent(string $email, float $rating, string $ip_address = ''): void {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "codguard_block_events`
            SET email = '" . $this->db->escape($email) . "',
                rating = '" . (float)$rating . "',
                timestamp = " . time() . ",
                ip_address = '" . $this->db->escape($ip_address) . "'
        ");
    }

    /**
     * Clean old block events (older than 90 days)
     */
    public function cleanOldBlockEvents(): void {
        $ninety_days_ago = strtotime('-90 days');

        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "codguard_block_events`
            WHERE timestamp < " . (int)$ninety_days_ago . "
        ");
    }

    /**
     * Add order to queue
     */
    public function addOrderToQueue(int $order_id, array $order_data): void {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "codguard_order_queue`
            SET order_id = " . (int)$order_id . ",
                order_data = '" . $this->db->escape(json_encode($order_data)) . "',
                created_at = NOW(),
                status = 'pending'
            ON DUPLICATE KEY UPDATE
                order_data = '" . $this->db->escape(json_encode($order_data)) . "',
                created_at = NOW(),
                status = 'pending'
        ");
    }

    /**
     * Get pending orders from queue
     */
    public function getPendingOrders(): array {
        $query = $this->db->query("
            SELECT queue_id, order_id, order_data
            FROM `" . DB_PREFIX . "codguard_order_queue`
            WHERE status = 'pending'
            
            ORDER BY created_at ASC
        ");

        $orders = array();
        foreach ($query->rows as $row) {
            $orders[] = array(
                'queue_id' => $row['queue_id'],
                'order_id' => $row['order_id'],
                'data' => json_decode($row['order_data'], true)
            );
        }

        return $orders;
    }

    /**
     * Mark orders as sent
     */
    public function markOrdersAsSent(array $queue_ids): void {
        if (empty($queue_ids)) {
            return;
        }

        $this->db->query("
            UPDATE `" . DB_PREFIX . "codguard_order_queue`
            SET status = 'sent',
                sent_at = NOW()
            WHERE queue_id IN (" . implode(',', array_map('intval', $queue_ids)) . ")
        ");
    }

    /**
     * Mark orders as failed
     */
    public function markOrdersAsFailed(array $queue_ids): void {
        if (empty($queue_ids)) {
            return;
        }

        $this->db->query("
            UPDATE `" . DB_PREFIX . "codguard_order_queue`
            SET status = 'failed'
            WHERE queue_id IN (" . implode(',', array_map('intval', $queue_ids)) . ")
        ");
    }

    /**
     * Clean old sent orders (older than 7 days)
     */
    public function cleanOldQueueItems(): void {
        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "codguard_order_queue`
            WHERE status = 'sent'
            AND sent_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
    }

    /**
     * Get queue statistics
     */
    public function getQueueStatistics(): array {
        $stats = array(
            'pending' => 0,
            'sent' => 0,
            'failed' => 0
        );

        $query = $this->db->query("
            SELECT status, COUNT(*) as total
            FROM `" . DB_PREFIX . "codguard_order_queue`
            GROUP BY status
        ");

        foreach ($query->rows as $row) {
            $stats[$row['status']] = $row['total'];
        }

        return $stats;
    }

    /**
     * Migrate settings from oc_setting to codguard_settings table
     */
    private function migrateSettings(): void {
        $keys = array(
            'module_codguard_status',
            'module_codguard_shop_id',
            'module_codguard_public_key',
            'module_codguard_private_key',
            'module_codguard_rating_tolerance',
            'module_codguard_rejection_message',
            'module_codguard_good_status',
            'module_codguard_refused_status',
            'module_codguard_cod_methods'
        );

        foreach ($keys as $key) {
            $value = $this->config->get($key);
            if ($value !== null) {
                $this->saveSetting($key, $value);
            }
        }
    }

    /**
     * Save setting to codguard_settings table
     */
    public function saveSetting(string $key, $value): void {
        $serialized = 0;
        if (is_array($value) || is_object($value)) {
            $value = serialize($value);
            $serialized = 1;
        }

        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "codguard_settings`
            SET `key` = '" . $this->db->escape($key) . "',
                `value` = '" . $this->db->escape($value) . "',
                `serialized` = " . (int)$serialized . "
            ON DUPLICATE KEY UPDATE
                `value` = '" . $this->db->escape($value) . "',
                `serialized` = " . (int)$serialized . "
        ");
    }

    /**
     * Load setting from codguard_settings table
     */
    public function loadSetting(string $key) {
        $query = $this->db->query("
            SELECT `value`, `serialized`
            FROM `" . DB_PREFIX . "codguard_settings`
            WHERE `key` = '" . $this->db->escape($key) . "'
        ");

        if ($query->num_rows) {
            $value = $query->row['value'];
            if ($query->row['serialized']) {
                $value = unserialize($value);
            }
            return $value;
        }

        return null;
    }

    /**
     * Load all settings from codguard_settings table
     */
    public function loadAllSettings(): array {
        $settings = array();

        $query = $this->db->query("
            SELECT `key`, `value`, `serialized`
            FROM `" . DB_PREFIX . "codguard_settings`
        ");

        foreach ($query->rows as $row) {
            $value = $row['value'];
            if ($row['serialized']) {
                $value = unserialize($value);
            }
            $settings[$row['key']] = $value;
        }

        return $settings;
    }

    /**
     * API endpoint for order import
     */
    const API_ORDER_ENDPOINT = 'https://api.codguard.com/api/orders/import';

    /**
     * Send order immediately (no queue)
     * Called from admin when order status changes
     *
     * @param int $order_id Order ID
     * @param int $order_status_id Order status ID
     */
    public function queueOrder(int $order_id, int $order_status_id): void {
        $good_status = $this->config->get('module_codguard_good_status');
        $refused_status = $this->config->get('module_codguard_refused_status');

        // Only send orders with configured statuses
        if ($order_status_id != $good_status && $order_status_id != $refused_status) {
            return;
        }

        // Load order data from admin model
        $this->load->model('sale/order');
        $order = $this->model_sale_order->getOrder($order_id);

        if (!$order || empty($order['email'])) {
            $this->log->write('CodGuard [ORDER-UPLOAD]: Order #' . $order_id . ' not found or has no email');
            return;
        }

        // Prepare order data
        $order_data = $this->prepareOrderData($order, $order_status_id);

        $this->log->write('CodGuard [ORDER-UPLOAD]: Order #' . $order_id . ' - sending immediately to API');

        // Send immediately to API
        $result = $this->sendOrdersToApi(array($order_data));

        if ($result) {
            $this->log->write('CodGuard [ORDER-UPLOAD]: SUCCESS - Order #' . $order_id . ' sent to API');
        } else {
            $this->log->write('CodGuard [ORDER-UPLOAD]: FAILED - Order #' . $order_id . ' could not be sent to API');
        }
    }

    /**
     * Prepare order data for API
     *
     * @param array $order Order data
     * @param int $order_status_id Order status ID
     * @return array Formatted order data
     */
    private function prepareOrderData(array $order, int $order_status_id): array {
        $shop_id = $this->config->get('module_codguard_shop_id');
        $refused_status = $this->config->get('module_codguard_refused_status');

        // Format address
        $address_parts = array_filter(array(
            $order['payment_address_1'],
            $order['payment_address_2'],
            $order['payment_city'],
            $order['payment_zone']
        ));

        $address = implode(', ', $address_parts);

        // Determine outcome
        $outcome = ($order_status_id == $refused_status) ? '-1' : '1';

        // Get status name
        $this->load->model('localisation/order_status');
        $status_info = $this->model_localisation_order_status->getOrderStatus($order_status_id);
        $status_name = $status_info ? $status_info['name'] : 'unknown';

        // Get country ISO code from country_id (direct lookup)
        $country_code = '';
        if (!empty($order['payment_country_id'])) {
            $country_query = $this->db->query(
                "SELECT iso_code_2 FROM " . DB_PREFIX . "country WHERE country_id = " . (int)$order['payment_country_id']
            );
            if ($country_query->num_rows > 0) {
                $country_code = $country_query->row['iso_code_2'];
            }
        }

        // Get phone number with placeholder for shops without phone field
        $phone = !empty($order['telephone']) ? $order['telephone'] : 'N/A';

        return array(
            'eshop_id' => (int)$shop_id,
            'email' => $order['email'],
            'code' => $order['order_id'],
            'status' => $status_name,
            'outcome' => $outcome,
            'phone' => $phone,
            'country_code' => $country_code,
            'postal_code' => $order['payment_postcode'] ?: '',
            'address' => $address
        );
    }

    /**
     * Send orders to CodGuard API
     *
     * @param array $orders Array of order data
     * @return bool Success status
     */
    private function sendOrdersToApi(array $orders): bool {
        $public_key = $this->config->get('module_codguard_public_key');
        $private_key = $this->config->get('module_codguard_private_key');

        if (empty($public_key) || empty($private_key)) {
            $this->log->write('CodGuard: API keys not configured');
            return false;
        }

        $payload = json_encode(array('orders' => $orders));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_ORDER_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'X-API-PUBLIC-KEY: ' . $public_key,
            'X-API-PRIVATE-KEY: ' . $private_key
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $this->log->write('CodGuard: API cURL error - ' . $curl_error);
            return false;
        }

        $this->log->write('CodGuard: Order API response - HTTP ' . $http_code . ': ' . $response);

        return ($http_code == 200 || $http_code == 201);
    }
}
