# CodGuard OpenCart 4.x - Troubleshooting Guide

## Issue: "You do not have permission to access this page"

### Quick Fix - Run SQL Script

1. Go to your database management tool (phpMyAdmin, MySQL Workbench, etc.)
2. Select your OpenCart database
3. Run the SQL script from `fix-permissions.sql`
4. Refresh your OpenCart admin panel
5. The pencil icon should now work!

**Important:** If your database prefix is NOT `oc_`, edit the SQL script and replace `oc_` with your actual prefix.

### Alternative Fix - Manual Database Update

Run this SQL command directly:

```sql
-- Replace 'oc_' with your database prefix if different
INSERT IGNORE INTO `oc_user_group_permission` (`user_group_id`, `type`, `route`)
SELECT `user_group_id`, 'access', 'extension/codguard/module/codguard'
FROM `oc_user_group`;

INSERT IGNORE INTO `oc_user_group_permission` (`user_group_id`, `type`, `route`)
SELECT `user_group_id`, 'modify', 'extension/codguard/module/codguard'
FROM `oc_user_group`;
```

### Why This Happens

OpenCart 4.x has changed how extensions handle permissions. The `install()` method in the controller **should** add permissions automatically, but there may be cases where:

1. The install method doesn't execute (OpenCart bug)
2. Database constraints prevent the INSERT
3. The extension was installed via "Installer" instead of proper installation flow

### Manual UI Fix

If you prefer using the OpenCart UI:

1. Go to **System → Users → User Groups**
2. Click the **Edit** (pencil) icon for **Administrator**
3. Scroll down to find **Access Permission** section
4. Check the box for: `extension/codguard/module/codguard`
5. Scroll to **Modify Permission** section
6. Check the box for: `extension/codguard/module/codguard`
7. Click **Save**

---

## Issue: Title Shows "codguard_heading_title" Instead of "CodGuard"

This is a known OpenCart 4.x language loading issue in certain versions (4.0.1.1, 4.0.2.0, 4.0.2.1).

### Temporary Fix

The extension will still function correctly - this is purely cosmetic and only affects the module list display.

### Why This Happens

OpenCart 4.x has bugs in how it loads language files for extensions in the marketplace/extension listing page. The extension itself works fine once you click through to configure it.

### Verification

Click on the extension (even if it shows the language key). If you see "CodGuard" at the top of the configuration page and all the form fields are properly labeled, then your language file is loading correctly everywhere except the listing page.

---

## Issue: Extension Doesn't Appear in Modules List

### Check Installation Status

1. Go to **Extensions → Installer**
2. Look for `codguard-opencart-v2.0.2-oc4.ocmod.zip` in the installed list
3. If it's there, click the **Uninstall** button (trash icon)
4. Re-upload and install

### Clear Caches

1. Go to **Dashboard**
2. Click the blue **Refresh** button in the top right
3. Try accessing **Extensions → Extensions → Modules** again

---

## Issue: Database Tables Not Created

The install method should create two tables:
- `oc_codguard_block_events`
- `oc_codguard_order_queue`

### Verify Tables Exist

Run this SQL:

```sql
SHOW TABLES LIKE 'oc_codguard%';
```

### Manually Create Tables

If they don't exist, run:

```sql
CREATE TABLE IF NOT EXISTS `oc_codguard_block_events` (
    `event_id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(255) NOT NULL,
    `rating` decimal(5,4) NOT NULL,
    `timestamp` int(11) NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    PRIMARY KEY (`event_id`),
    KEY `timestamp` (`timestamp`),
    KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oc_codguard_order_queue` (
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
```

---

## Issue: Events Not Registered

### Check Event Registration

Run this SQL to verify the event exists:

```sql
SELECT * FROM `oc_event` WHERE code = 'codguard_order_status_change';
```

### Manually Register Event

If missing, run:

```sql
INSERT INTO `oc_event` SET
    `code` = 'codguard_order_status_change',
    `description` = 'CodGuard order status change handler',
    `trigger` = 'catalog/model/checkout/order/addHistory/after',
    `action` = 'extension/codguard/module/codguard.eventOrderStatusChange',
    `status` = 1,
    `sort_order` = 0;
```

---

## Common Installation Steps

For a **clean installation**:

1. **Uninstall** any previous version via Extensions → Installer
2. **Delete** old files manually if they exist
3. **Upload** `codguard-opencart-v2.0.2-oc4.ocmod.zip`
4. Click **Install** (green + icon)
5. **Run the SQL script** (`fix-permissions.sql`) to ensure permissions
6. Go to Extensions → Extensions → Modules
7. Find **CodGuard** and click the **Edit** icon
8. Configure your API credentials

---

## Getting Help

If you're still experiencing issues:

1. Check OpenCart error logs: `system/storage/logs/error.log`
2. Check your hosting's PHP error logs
3. Verify PHP version is 8.1 or higher
4. Verify OpenCart version is 4.0.x or higher
5. Ensure database user has full permissions (CREATE, INSERT, DELETE, UPDATE)

---

**Last Updated:** 2025-11-21
**Version:** 2.0.2
