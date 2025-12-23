<?php
/**
 * CodGuard v2.9.0 - Installation Script
 * Sets up database trigger and queue table for real-time order uploads
 */

namespace Opencart\Admin\Controller\Extension\Codguard\Module;

class Codguard extends \Opencart\System\Engine\Controller {

    public function install(): void {
        // Create upload queue table
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "codguard_upload_queue` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT NOT NULL,
                `order_status_id` INT NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `processed` TINYINT DEFAULT 0,
                INDEX `idx_processed` (`processed`),
                INDEX `idx_order` (`order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Drop existing trigger if exists
        $this->db->query("DROP TRIGGER IF EXISTS codguard_order_history_trigger");

        // Create dynamic trigger that reads from settings
        $this->db->query("
            CREATE TRIGGER codguard_order_history_trigger
            AFTER INSERT ON `" . DB_PREFIX . "order_history`
            FOR EACH ROW
            BEGIN
                DECLARE good_status INT;
                DECLARE refused_status INT;

                -- Read configured statuses from settings
                SELECT value INTO good_status
                FROM `" . DB_PREFIX . "setting`
                WHERE `key` = 'module_codguard_good_status' AND store_id = 0
                LIMIT 1;

                SELECT value INTO refused_status
                FROM `" . DB_PREFIX . "setting`
                WHERE `key` = 'module_codguard_refused_status' AND store_id = 0
                LIMIT 1;

                -- Only queue if status matches configured values
                IF NEW.order_status_id = good_status OR NEW.order_status_id = refused_status THEN
                    INSERT INTO `" . DB_PREFIX . "codguard_upload_queue` (order_id, order_status_id)
                    VALUES (NEW.order_id, NEW.order_status_id);
                END IF;
            END
        ");

        $this->log->write('CodGuard v2.9.0: Installation completed - Database trigger and queue table created');
    }

    public function uninstall(): void {
        // Drop trigger
        $this->db->query("DROP TRIGGER IF EXISTS codguard_order_history_trigger");

        // Optional: Drop queue table (comment out if you want to preserve upload history)
        // $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "codguard_upload_queue`");

        $this->log->write('CodGuard v2.9.0: Uninstallation completed - Database trigger removed');
    }
}
