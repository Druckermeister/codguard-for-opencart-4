<?php
/**
 * CodGuard for OpenCart 4.x - Catalog Model
 *
 * @package    CodGuard
 * @author     CodGuard
 * @copyright  2025 CodGuard
 * @license    GPL v2 or later
 * @version    2.8.0
 */

namespace Opencart\Catalog\Model\Extension\Codguard\Fraud;

class Codguard extends \Opencart\System\Engine\Model {

    /**
     * API endpoint for customer rating
     */
    const API_RATING_ENDPOINT = 'https://api.codguard.com/api/customer-rating';

    /**
     * API endpoint for order import
     */
    const API_ORDER_ENDPOINT = 'https://api.codguard.com/api/orders/import';

    /**
     * Get customer rating from CodGuard API
     *
     * @param string $email Customer email
     * @return float|null Rating (0-1) or null on failure
     */
    public function getCustomerRating(string $email): ?float {
        $shop_id = $this->config->get('module_codguard_shop_id');
        $public_key = $this->config->get('module_codguard_public_key');

        // Enhanced logging for API keys validation
        $this->log->write('CodGuard [DEBUG]: getCustomerRating called for email: ' . $email);
        $this->log->write('CodGuard [DEBUG]: Shop ID configured: ' . (!empty($shop_id) ? 'YES (ID: ' . $shop_id . ')' : 'NO'));
        $this->log->write('CodGuard [DEBUG]: Public Key configured: ' . (!empty($public_key) ? 'YES (' . substr($public_key, 0, 10) . '... ' . strlen($public_key) . ' chars)' : 'NO'));

        if (empty($shop_id) || empty($public_key)) {
            $this->log->write('CodGuard [ERROR]: API keys not configured - shop_id: ' . ($shop_id ?: 'EMPTY') . ', public_key: ' . ($public_key ? 'SET' : 'EMPTY'));
            return null;
        }

        $url = self::API_RATING_ENDPOINT . '/' . urlencode($shop_id) . '/' . urlencode($email);

        $this->log->write('CodGuard [DEBUG]: Full API URL: ' . $url);
        $this->log->write('CodGuard [DEBUG]: Initiating cURL request...');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'x-api-key: ' . $public_key
        ));

        $this->log->write('CodGuard [DEBUG]: Request headers set - Accept: application/json, x-api-key: ' . substr($public_key, 0, 10) . '...');

        $full_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        // Get response details
        $headers = substr($full_response, 0, $header_size);
        $response = substr($full_response, $header_size);

        curl_close($ch);

        // Enhanced logging for the API response
        $this->log->write('CodGuard [INFO]: Rating API called for ' . $email . ' - HTTP ' . $http_code);

        if ($curl_error) {
            $this->log->write('CodGuard [ERROR]: cURL error #' . $curl_errno . ' - ' . $curl_error);
            return null;
        }

        $this->log->write('CodGuard [DEBUG]: Response Headers: ' . str_replace("\r\n", " | ", trim($headers)));
        $this->log->write('CodGuard [DEBUG]: Response Body: ' . $response);

        // 404 = new customer, return 1.0 (allow)
        if ($http_code == 404) {
            $this->log->write('CodGuard [INFO]: Customer not found (404) - treating as new customer, allowing with rating 1.0');
            return 1.0;
        }

        // Non-200 status, fail open
        if ($http_code != 200) {
            $this->log->write('CodGuard [ERROR]: API returned non-200 status: ' . $http_code . ' - Response: ' . substr($response, 0, 500));
            return null;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log->write('CodGuard [ERROR]: JSON decode error: ' . json_last_error_msg() . ' - Response: ' . $response);
            return null;
        }

        if (!isset($data['rating'])) {
            $this->log->write('CodGuard [ERROR]: Invalid API response - missing "rating" field. Response: ' . $response);
            return null;
        }

        $rating = (float)$data['rating'];
        $this->log->write('CodGuard [INFO]: Customer rating retrieved successfully: ' . $rating);

        return $rating;
    }

    /**
     * Log block event
     *
     * @param string $email Customer email
     * @param float $rating Customer rating
     * @param string $ip_address IP address
     */
    public function logBlockEvent(string $email, float $rating, string $ip_address = ''): void {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "codguard_block_events`
            SET email = '" . $this->db->escape($email) . "',
                rating = '" . (float)$rating . "',
                timestamp = " . time() . ",
                ip_address = '" . $this->db->escape($ip_address) . "'
        ");

        $this->log->write('CodGuard: Blocked COD for ' . $email . ' (rating: ' . $rating . ')');
    }

    /**
     * Send order immediately (no queue)
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

        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($order_id);

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
     * Schedule bundled send (immediate)
     */
    private function scheduleBundledSend(): void {
        // Immediately send pending orders (no delay)
        $query = $this->db->query("
            SELECT * FROM `" . DB_PREFIX . "codguard_order_queue`
            WHERE status = 'pending'
            LIMIT 1
        ");

        if ($query->num_rows > 0) {
            // There are orders ready to send, trigger send immediately
            $this->sendBundledOrders();
        }
    }

    /**
     * Send bundled orders to API
     */
    public function sendBundledOrders(): void {
        // Get pending orders (immediate send, no delay)
        $query = $this->db->query("
            SELECT queue_id, order_id, order_data
            FROM `" . DB_PREFIX . "codguard_order_queue`
            WHERE status = 'pending'
            ORDER BY created_at ASC
            LIMIT 100
        ");

        if ($query->num_rows == 0) {
            return;
        }

        $orders = array();
        $queue_ids = array();

        foreach ($query->rows as $row) {
            $orders[] = json_decode($row['order_data'], true);
            $queue_ids[] = $row['queue_id'];
        }

        $this->log->write('CodGuard: Sending ' . count($orders) . ' orders to API (immediate)');

        // Send to API
        $result = $this->sendOrdersToApi($orders);

        if ($result) {
            // Mark as sent
            $this->db->query("
                UPDATE `" . DB_PREFIX . "codguard_order_queue`
                SET status = 'sent',
                    sent_at = NOW()
                WHERE queue_id IN (" . implode(',', array_map('intval', $queue_ids)) . ")
            ");

            $this->log->write('CodGuard: Successfully sent ' . count($orders) . ' orders');
        } else {
            // Mark as failed
            $this->db->query("
                UPDATE `" . DB_PREFIX . "codguard_order_queue`
                SET status = 'failed'
                WHERE queue_id IN (" . implode(',', array_map('intval', $queue_ids)) . ")
            ");

            $this->log->write('CodGuard: Failed to send orders');
        }
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

    /**
     * Clean old records (run this via cron)
     */
    public function cleanOldRecords(): void {
        // Clean old block events (90+ days)
        $ninety_days_ago = strtotime('-90 days');
        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "codguard_block_events`
            WHERE timestamp < " . (int)$ninety_days_ago . "
        ");

        // Clean old sent orders (7+ days)
        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "codguard_order_queue`
            WHERE status = 'sent'
            AND sent_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
    }
}
