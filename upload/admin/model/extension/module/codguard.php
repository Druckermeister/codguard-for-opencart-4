<?php
/**
 * CodGuard for OpenCart - Admin Model
 *
 * @package    CodGuard
 * @author     CodGuard
 * @copyright  2025 CodGuard
 * @license    GPL v2 or later
 * @version    2.7.0
 */

class ModelExtensionModuleCodguard extends Model {

    /**
     * Install extension
     */
    public function install() {
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
        ");

        // Register event handlers
        $this->load->model('setting/event');

        // Order status change event
        $this->model_setting_event->addEvent(
            'codguard_order_status_change',
            'catalog/model/checkout/order/addOrderHistory/after',
            'extension/module/codguard/eventOrderStatusChange'
        );

        // Log installation
        $this->log->write('CodGuard extension installed successfully');
    }

    /**
     * Uninstall extension
     */
    public function uninstall() {
        // Remove event handlers
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('codguard_order_status_change');

        // Note: We don't drop tables to preserve data
        // Drop tables manually if needed:
        // DROP TABLE IF EXISTS `" . DB_PREFIX . "codguard_block_events`;
        // DROP TABLE IF EXISTS `" . DB_PREFIX . "codguard_order_queue`;

        $this->log->write('CodGuard extension uninstalled');
    }

    /**
     * Get statistics for different time periods
     */
    public function getStatistics() {
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
            WHERE timestamp >= " . (int)$today_start
        );
        $stats['today'] = $query->row['total'];

        // Last 7 days
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "codguard_block_events`
            WHERE timestamp >= " . (int)$week_start
        );
        $stats['week'] = $query->row['total'];

        // Last 30 days
        $query = $this->db->query("
            SELECT COUNT(*) as total
            FROM `" . DB_PREFIX . "codguard_block_events`
            WHERE timestamp >= " . (int)$month_start
        );
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
    public function getRecentBlocks($limit = 10) {
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
    public function addBlockEvent($email, $rating, $ip_address = '') {
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
    public function cleanOldBlockEvents() {
        $ninety_days_ago = strtotime('-90 days');

        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "codguard_block_events`
            WHERE timestamp < " . (int)$ninety_days_ago . "
        ");
    }

    /**
     * Add order to queue
     */
    public function addOrderToQueue($order_id, $order_data) {
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
    public function getPendingOrders() {
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
    public function markOrdersAsSent($queue_ids) {
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
    public function markOrdersAsFailed($queue_ids) {
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
    public function cleanOldQueueItems() {
        $this->db->query("
            DELETE FROM `" . DB_PREFIX . "codguard_order_queue`
            WHERE status = 'sent'
            AND sent_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
    }

    /**
     * Get queue statistics
     */
    public function getQueueStatistics() {
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
}
