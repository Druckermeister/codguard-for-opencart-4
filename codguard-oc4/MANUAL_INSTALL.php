<?php
/**
 * Manual Installation Script for CodGuard
 *
 * If events aren't being registered during normal installation,
 * run this script manually to register them.
 *
 * Access via: https://your-site.com/MANUAL_INSTALL.php
 * (Upload this file to OpenCart root directory)
 */

// Load OpenCart
require_once('config.php');
require_once(DIR_SYSTEM . 'startup.php');

// Start framework
$registry = new \Opencart\System\Engine\Registry();
$config = new \Opencart\System\Engine\Config();
$registry->set('config', $config);

// Load database
$db = new \Opencart\System\Library\DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

// Load log
$log = new \Opencart\System\Library\Log('codguard_install.log');
$registry->set('log', $log);

echo "<h1>CodGuard Manual Installation</h1>";
echo "<pre>";

// 1. Create tables
echo "Step 1: Creating database tables...\n";

try {
    // Block events table
    $db->query("
        CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codguard_block_events` (
            `event_id` int(11) NOT NULL AUTO_INCREMENT,
            `email` varchar(255) NOT NULL,
            `rating` decimal(3,2) NOT NULL,
            `timestamp` int(11) NOT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            PRIMARY KEY (`event_id`),
            KEY `idx_email` (`email`),
            KEY `idx_timestamp` (`timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "✓ codguard_block_events table created\n";

    // Order queue table
    $db->query("
        CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codguard_order_queue` (
            `queue_id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) NOT NULL,
            `order_data` text NOT NULL,
            `status` enum('pending','sent','failed') DEFAULT 'pending',
            `created_at` datetime NOT NULL,
            `sent_at` datetime DEFAULT NULL,
            PRIMARY KEY (`queue_id`),
            UNIQUE KEY `idx_order_id` (`order_id`),
            KEY `idx_status` (`status`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "✓ codguard_order_queue table created\n";

    // Settings table
    $db->query("
        CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codguard_settings` (
            `setting_id` int(11) NOT NULL AUTO_INCREMENT,
            `key` varchar(64) NOT NULL,
            `value` text NOT NULL,
            `serialized` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`setting_id`),
            UNIQUE KEY `key` (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    echo "✓ codguard_settings table created\n";
} catch (Exception $e) {
    echo "✗ Error creating tables: " . $e->getMessage() . "\n";
}

// 2. Register events
echo "\nStep 2: Registering events...\n";

try {
    // Check if events already exist
    $existing_events = $db->query("SELECT code FROM `" . DB_PREFIX . "event` WHERE code LIKE 'codguard_%'");

    if ($existing_events->num_rows > 0) {
        echo "Removing existing CodGuard events...\n";
        $db->query("DELETE FROM `" . DB_PREFIX . "event` WHERE code LIKE 'codguard_%'");
    }

    // Event 1: Order status change
    $db->query("
        INSERT INTO `" . DB_PREFIX . "event` SET
            `code` = 'codguard_order_status_change',
            `description` = 'CodGuard order status change handler',
            `trigger` = 'catalog/model/checkout/order/addHistory/after',
            `action` = 'extension/codguard/fraud/codguard.eventOrderStatusChange',
            `status` = 1,
            `sort_order` = 0
    ");
    echo "✓ codguard_order_status_change event registered\n";

    // Event 2: Checkout validation
    $db->query("
        INSERT INTO `" . DB_PREFIX . "event` SET
            `code` = 'codguard_checkout_validation',
            `description` = 'CodGuard checkout validation before order creation',
            `trigger` = 'catalog/model/checkout/order/addOrder/before',
            `action` = 'extension/codguard/fraud/codguard.validateCheckout',
            `status` = 1,
            `sort_order` = 0
    ");
    echo "✓ codguard_checkout_validation event registered\n";

    // Event 3: JavaScript injection
    $db->query("
        INSERT INTO `" . DB_PREFIX . "event` SET
            `code` = 'codguard_add_javascript',
            `description` = 'CodGuard JavaScript injection for checkout pages',
            `trigger` = 'catalog/view/*/before',
            `action` = 'extension/codguard/fraud/codguard.addJavaScript',
            `status` = 1,
            `sort_order` = 0
    ");
    echo "✓ codguard_add_javascript event registered\n";

    $log->write('CodGuard: Manual installation completed successfully');

} catch (Exception $e) {
    echo "✗ Error registering events: " . $e->getMessage() . "\n";
}

// 3. Verify installation
echo "\nStep 3: Verifying installation...\n";

$events = $db->query("SELECT code, trigger, action, status FROM `" . DB_PREFIX . "event` WHERE code LIKE 'codguard_%' ORDER BY code");

if ($events->num_rows > 0) {
    echo "\nRegistered Events:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-35s %-50s %s\n", "CODE", "TRIGGER", "STATUS");
    echo str_repeat("-", 80) . "\n";

    foreach ($events->rows as $event) {
        printf("%-35s %-50s %s\n",
            $event['code'],
            substr($event['trigger'], 0, 50),
            $event['status'] ? 'ENABLED' : 'DISABLED'
        );
    }
    echo str_repeat("-", 80) . "\n";
    echo "\n✓ Installation successful! All " . $events->num_rows . " events registered.\n";
} else {
    echo "\n✗ No events found! Installation may have failed.\n";
}

$tables = $db->query("SHOW TABLES LIKE '" . DB_PREFIX . "codguard_%'");
echo "\nDatabase Tables: " . $tables->num_rows . " found\n";

echo "\n</pre>";
echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Delete this file (MANUAL_INSTALL.php) from your server for security</li>";
echo "<li>Go to Admin → System → Maintenance → Events to verify</li>";
echo "<li>Configure CodGuard settings in Extensions → Fraud</li>";
echo "<li>Test checkout with COD payment</li>";
echo "</ol>";
?>
