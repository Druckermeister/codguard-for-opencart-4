# CodGuard for OpenCart v2.9.0 - Installation Guide

## What's New in v2.9.0

- ✅ **Real-time order uploads from admin panel** (not just customer checkout)
- ✅ **Database trigger** automatically detects order status changes
- ✅ **UTF-8 encoding fixed** for Central European languages (HU, CZ, SK, RO, HR)
- ✅ **Dynamic status configuration** - reads from admin panel settings

## Installation Steps

### 1. Install the Extension

1. Go to **Admin Panel → Extensions → Installer**
2. Upload `codguard-oc4-v2.9.0.ocmod.zip`
3. Click **Install**
4. Go to **Extensions → Extensions → Fraud**
5. Find "CodGuard" and click **Install**
6. Click **Edit** to configure

### 2. Upload Queue Processor Script

After installing the extension, you need to upload one additional file:

1. Download `codguard_process_queue.php` from the package
2. Upload it to your OpenCart root directory (same folder as `index.php`)
3. Set file permissions: `chmod 644 codguard_process_queue.php`

### 3. Set Up Cron Job (REQUIRED)

The queue processor must run every minute to upload orders in real-time.

**Option A: Via cPanel/Plesk**
1. Go to **Cron Jobs** in your hosting panel
2. Add a new cron job:
   - **Frequency:** Every minute (`* * * * *`)
   - **Command:**
     ```bash
     cd /path/to/opencart && /usr/bin/php codguard_process_queue.php > /dev/null 2>&1
     ```
   - Replace `/path/to/opencart` with your actual OpenCart path

**Option B: Via SSH**
```bash
crontab -e
```
Add this line:
```bash
* * * * * cd /path/to/opencart && /usr/bin/php codguard_process_queue.php > /dev/null 2>&1
```

**Option C: Via External Cron Service**
- Use services like EasyCron or cron-job.org
- Set URL: `https://yourstore.com/codguard_process_queue.php`
- Note: You'll need to modify the script to allow HTTP access

### 4. Verify Installation

After installation, verify everything is working:

1. **Check Database:**
   ```sql
   SHOW TABLES LIKE '%codguard%';
   ```
   You should see: `oc_codguard_upload_queue`, `oc_codguard_block_events`

2. **Check Trigger:**
   ```sql
   SHOW TRIGGERS LIKE 'oc_order_history';
   ```
   You should see: `codguard_order_history_trigger`

3. **Test Order Upload:**
   - Create a test order
   - Change its status to "Complete" or "Denied" (or whatever you configured)
   - Wait 1 minute
   - Check CodGuard database for the order

4. **Check Logs:**
   ```bash
   tail -f storage/logs/error.log | grep QUEUE-PROCESSOR
   ```
   You should see messages like:
   - `Starting queue processor`
   - `Found X pending uploads`
   - `SUCCESS - X orders uploaded`

## Configuration

### Admin Panel Settings

1. **API Configuration:**
   - Shop ID
   - Public Key
   - Private Key

2. **Order Status Mapping:**
   - **Good Status:** Orders to report as successful (e.g., "Complete")
   - **Refused Status:** Orders to report as denied (e.g., "Denied")

3. **Payment Methods:**
   - Select which payment methods require COD validation

4. **Rating Settings:**
   - Minimum rating tolerance (0-100%)
   - Rejection message shown to customers

## How It Works

### Customer Checkout (Real-time via Events)
1. Customer places order with COD
2. Order status changes to "Complete" or "Denied"
3. OpenCart event fires → uploads immediately

### Admin Panel (Real-time via Database Trigger)
1. Admin changes order status to "Complete" or "Denied"
2. Database trigger fires → adds to queue
3. Cron runs every minute → uploads queued orders
4. **Maximum delay: 60 seconds**

## Troubleshooting

### Orders Not Uploading

1. **Check cron is running:**
   ```bash
   grep "QUEUE-PROCESSOR" storage/logs/error.log
   ```

2. **Check queue table:**
   ```sql
   SELECT * FROM oc_codguard_upload_queue WHERE processed = 0;
   ```

3. **Check trigger exists:**
   ```sql
   SHOW TRIGGERS LIKE 'oc_order_history';
   ```

4. **Test manually:**
   ```bash
   php codguard_process_queue.php
   ```

### UTF-8 Characters Corrupted

The script automatically handles UTF-8 encoding. If you still see issues:

1. Check database charset:
   ```sql
   SHOW CREATE TABLE oc_order;
   ```
   Should show: `CHARSET=utf8mb4`

2. The queue processor sets `utf8mb4` charset automatically

### Cron Not Running

1. **Check crontab:**
   ```bash
   crontab -l
   ```

2. **Check cron logs:**
   ```bash
   grep CRON /var/log/syslog
   ```

3. **Test script manually:**
   ```bash
   cd /path/to/opencart && php codguard_process_queue.php
   ```

## Upgrading from v2.8.0 or Earlier

If you're upgrading from v2.8.0:

1. Uninstall old version via **Extensions → Fraud → Uninstall**
2. **Important:** Delete old extension folder:
   ```bash
   rm -rf extension/codguard
   ```
3. Follow installation steps above
4. Your settings will be preserved (API keys, statuses, etc.)

## File Structure

```
codguard-oc4-v2.9.0.ocmod.zip
├── install.json                           # Extension metadata
├── install.php                            # Database setup script
├── codguard_process_queue.php            # Queue processor (upload to root)
├── admin/
│   ├── controller/fraud/codguard.php
│   ├── language/en-gb/fraud/codguard.php
│   ├── model/fraud/codguard.php
│   └── view/template/fraud/codguard.twig
└── catalog/
    ├── controller/fraud/codguard.php
    ├── language/en-gb/fraud/codguard.php
    ├── model/fraud/codguard.php
    └── view/javascript/codguard.js
```

## Support

- **Documentation:** https://codguard.com/docs
- **Issues:** https://github.com/codguard/opencart-extension/issues
- **Email:** support@codguard.com

## Version History

- **v2.9.0** - Database trigger for admin uploads, UTF-8 fix
- **v2.8.0** - Real-time uploads via events
- **v2.7.0** - Removed bundling delay
- **v2.6.0** - Initial order sync system

---

**Important:** The cron job is **required** for admin panel uploads to work. Without it, only customer checkout orders will upload in real-time.
