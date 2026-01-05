<?php
/**
 * CodGuard Upload Queue Processor
 * Processes orders queued by database trigger and uploads immediately to API
 */

// Direct database connection
require_once(__DIR__ . '/config.php');

$mysqli = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);

if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

// Set UTF-8 encoding for proper character handling (Czech, Slovak, Hungarian, Romanian, Croatian, etc.)
$mysqli->set_charset('utf8mb4');

// Log to error.log
function log_message($message) {
    $log_file = __DIR__ . '/storage/logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, $timestamp . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

// Get module settings
$settings = array();
$result = $mysqli->query("SELECT `key`, value, serialized FROM " . DB_PREFIX . "setting WHERE store_id = 0 AND `code` = 'module_codguard'");
while ($row = $result->fetch_assoc()) {
    $settings[$row['key']] = $row['serialized'] ? json_decode($row['value'], true) : $row['value'];
}

// Check if module is enabled (silent exit if disabled)
if (empty($settings['module_codguard_status'])) {
    exit;
}

$shop_id = $settings['module_codguard_shop_id'] ?? '';
$public_key = $settings['module_codguard_public_key'] ?? '';
$private_key = $settings['module_codguard_private_key'] ?? '';
$good_status = $settings['module_codguard_good_status'] ?? 5;
$refused_status = $settings['module_codguard_refused_status'] ?? 8;

if (empty($shop_id) || empty($public_key) || empty($private_key)) {
    // Only log once per day for missing API keys
    $last_log_file = __DIR__ . '/storage/cache/codguard_last_api_key_warning.txt';
    if (!file_exists($last_log_file) || (time() - filemtime($last_log_file)) > 86400) {
        log_message('CodGuard [QUEUE-PROCESSOR]: API keys not configured');
        touch($last_log_file);
    }
    exit;
}

// Get pending uploads
$queue_result = $mysqli->query("SELECT id, order_id, order_status_id FROM " . DB_PREFIX . "codguard_upload_queue WHERE processed = 0 ORDER BY id ASC LIMIT 50");

// Silent exit if nothing to process
if ($queue_result->num_rows == 0) {
    exit;
}

log_message('CodGuard [QUEUE-PROCESSOR]: Found ' . $queue_result->num_rows . ' pending uploads');

// Function to upload orders to API
function upload_orders_to_api($orders, $shop_id, $public_key, $private_key) {
    $url = 'https://api.codguard.com/api/orders/import';

    $payload = json_encode(array('orders' => $orders));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-API-PUBLIC-KEY: ' . $public_key,
        'X-API-PRIVATE-KEY: ' . $private_key
    ));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return array('http_code' => $http_code, 'response' => $response);
}

// Process each queued order
$orders_to_upload = array();
$queue_ids = array();

while ($row = $queue_result->fetch_assoc()) {
    log_message('CodGuard [QUEUE-PROCESSOR]: Processing Order #' . $row['order_id']);

    // Get order details
    $order_query = $mysqli->query("SELECT * FROM " . DB_PREFIX . "order WHERE order_id = " . (int)$row['order_id']);
    $order = $order_query->fetch_assoc();

    if (!$order || empty($order['email'])) {
        log_message('CodGuard [QUEUE-PROCESSOR]: Order #' . $row['order_id'] . ' not found or has no email');
        // Mark as processed anyway to avoid re-trying
        $mysqli->query("UPDATE " . DB_PREFIX . "codguard_upload_queue SET processed = 1 WHERE id = " . (int)$row['id']);
        continue;
    }

    // Get country ISO code
    $country_code = '';
    if (!empty($order['payment_country_id'])) {
        $country_query = $mysqli->query("SELECT iso_code_2 FROM " . DB_PREFIX . "country WHERE country_id = " . (int)$order['payment_country_id']);
        if ($country = $country_query->fetch_assoc()) {
            $country_code = $country['iso_code_2'];
        }
    }

    // Get status name
    $status_name = 'unknown';
    $status_query = $mysqli->query("SELECT name FROM " . DB_PREFIX . "order_status WHERE order_status_id = " . (int)$row['order_status_id'] . " AND language_id = 1");
    if ($status = $status_query->fetch_assoc()) {
        $status_name = $status['name'];
    }

    // Prepare order data
    $address_parts = array_filter(array(
        $order['payment_address_1'],
        $order['payment_address_2'],
        $order['payment_city'],
        $order['payment_zone']
    ));

    $order_data = array(
        'eshop_id' => (int)$shop_id,
        'email' => $order['email'],
        'code' => $order['order_id'],
        'status' => $status_name,
        'outcome' => ($row['order_status_id'] == $refused_status) ? '-1' : '1',
        'phone' => !empty($order['telephone']) ? $order['telephone'] : 'N/A',
        'country_code' => $country_code,
        'postal_code' => $order['payment_postcode'] ?? '',
        'address' => implode(', ', $address_parts)
    );

    $orders_to_upload[] = $order_data;
    $queue_ids[] = $row['id'];
}

// Upload all orders in one batch
if (!empty($orders_to_upload)) {
    log_message('CodGuard [QUEUE-PROCESSOR]: Uploading ' . count($orders_to_upload) . ' orders to API');
    log_message('CodGuard [QUEUE-PROCESSOR]: Order count: ' . count($orders_to_upload));
    log_message('CodGuard [QUEUE-PROCESSOR]: First order email: ' . ($orders_to_upload[0]['email'] ?? 'MISSING'));
    file_put_contents(__DIR__ . '/codguard_debug_payload.txt', print_r($orders_to_upload, true));
    $json_test = json_encode($orders_to_upload, JSON_PRETTY_PRINT);
    log_message('CodGuard [QUEUE-PROCESSOR]: JSON encode result: ' . ($json_test === false ? 'FALSE - Error: ' . json_last_error_msg() : 'SUCCESS'));

    $result = upload_orders_to_api($orders_to_upload, $shop_id, $public_key, $private_key);

    log_message('CodGuard [QUEUE-PROCESSOR]: API Response - HTTP ' . $result['http_code'] . ': ' . $result['response']);

    if ($result['http_code'] == 200) {
        // Mark all as processed
        foreach ($queue_ids as $id) {
            $mysqli->query("UPDATE " . DB_PREFIX . "codguard_upload_queue SET processed = 1 WHERE id = " . (int)$id);
        }
        log_message('CodGuard [QUEUE-PROCESSOR]: SUCCESS - ' . count($orders_to_upload) . ' orders uploaded');
    } else {
        log_message('CodGuard [QUEUE-PROCESSOR]: FAILED - API returned HTTP ' . $result['http_code']);
    }
}

// Clean up old processed records
$mysqli->query("DELETE FROM " . DB_PREFIX . "codguard_upload_queue WHERE processed = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");

$mysqli->close();
