# CodGuard for OpenCart - Version 1.0.0

A comprehensive OpenCart extension that integrates with the CodGuard API to manage cash-on-delivery (COD) payment options based on customer ratings and automatically synchronize order data.

## Features

### ✅ Customer Rating Check
- **Silent verification at checkout** - Checks customer rating when COD payment method is selected
- **Automatic COD blocking** - Prevents low-rated customers from using COD payment
- **Fail-open approach** - If API is unreachable, customers can still checkout (customer-friendly)
- **Customizable rejection message** - Configure what message customers see when blocked

### ✅ Real-time Order Sync
- **Automatic order synchronization** - Orders are automatically queued when status changes
- **Bundled sync** - Orders are sent in batches every hour to optimize API usage
- **Status-based filtering** - Only syncs orders with configured successful/refused statuses
- **Retry mechanism** - Failed orders remain in queue for retry

### ✅ Admin Settings Panel
- **API Configuration** - Easy setup of Shop ID, Public Key, and Private Key
- **Order Status Mapping** - Map OpenCart statuses to CodGuard outcomes
- **Payment Method Selection** - Choose which payment methods trigger rating checks
- **Rating Tolerance** - Set minimum acceptable customer rating (0-100%)
- **Statistics Dashboard** - View COD block statistics (today, 7 days, 30 days, all time)

### ✅ Block Statistics
- Track how many times COD was blocked
- View recent block events with email, rating, and IP address
- Time-based statistics (today, week, month, all time)

## Requirements

- **OpenCart:** 3.0 or higher
- **PHP:** 7.0 or higher
- **PHP Extensions:** cURL must be enabled
- **CodGuard Account:** Active account with Shop ID and API keys

## Installation

### Method 1: Extension Installer (Recommended)

1. Download the extension package
2. Go to **Extensions > Installer** in OpenCart admin
3. Click **Upload** and select the extension file
4. After upload completes, go to **Extensions > Extensions**
5. Select **Modules** from the extension type dropdown
6. Find **CodGuard** and click **Install** (green plus icon)
7. Click **Edit** to configure settings

### Method 2: Manual Installation

1. Extract the extension package
2. Upload the contents of the `upload` folder to your OpenCart root directory via FTP
3. Go to **Extensions > Extensions** in OpenCart admin
4. Select **Modules** from the extension type dropdown
5. Find **CodGuard** and click **Install** (green plus icon)
6. Click **Edit** to configure settings

## Configuration

### 1. API Configuration

Navigate to **Extensions > Extensions > Modules > CodGuard** and configure:

**Required Settings:**
- **Shop ID** - Your unique shop identifier from CodGuard dashboard
- **Public Key** - Your API public key (minimum 10 characters)
- **Private Key** - Your API private key (minimum 10 characters)
- **Status** - Enable or disable the extension

### 2. Order Status Mapping

Configure which OpenCart order statuses map to CodGuard outcomes:

- **Successful Order Status** - Orders with this status send `outcome = 1` (default: Complete)
- **Refused Order Status** - Orders with this status send `outcome = -1` (default: Canceled)

When an order reaches one of these statuses, it's automatically queued for sync.

### 3. Payment Methods

Select which payment methods should trigger customer rating checks:

- Check all COD payment methods
- Typically includes "Cash On Delivery" or similar methods
- Only checked methods will trigger rating validation

### 4. Rating Settings

Configure customer rating validation:

- **Rating Tolerance** - Minimum acceptable rating (0-100%, recommended: 30-40%)
  - Example: If set to 35%, customers with rating below 35% cannot use COD
- **Rejection Message** - Message displayed to blocked customers
  - Default: "Unfortunately, we cannot offer Cash on Delivery for this order."

## How It Works

### Customer Rating Check Flow

1. **Customer enters checkout** with billing email
2. **Customer selects COD payment** method
3. **Extension checks rating** via CodGuard API
4. **If rating < tolerance:** Order is blocked with error message
5. **If rating ≥ tolerance:** Order proceeds normally
6. **If API fails:** Order proceeds (fail-open approach)

### Order Sync Flow

1. **Order status changes** to configured status (successful/refused)
2. **Order is queued** in database with prepared data
3. **After 1 hour delay**, orders are sent in batch to CodGuard API
4. **On success:** Orders marked as sent and cleaned after 7 days
5. **On failure:** Orders remain in queue for retry

### What Gets Synced

**Order data sent to CodGuard:**
```json
{
  "orders": [
    {
      "eshop_id": 123,
      "email": "customer@example.com",
      "code": "12345",
      "status": "Complete",
      "outcome": "1",
      "phone": "+36701234567",
      "country_code": "HU",
      "postal_code": "1111",
      "address": "Budapest, Example St. 1, District 5"
    }
  ]
}
```

**Outcome values:**
- `"1"` - Successful order (configured successful status)
- `"-1"` - Refused order (configured refused status)

## API Endpoints

### Customer Rating Endpoint
```
GET https://api.codguard.com/api/customer-rating/{shop_id}/{email}
```

**Headers:**
- `Accept: application/json`
- `x-api-key: {public_key}`

**Response (200 OK):**
```json
{
  "rating": 0.75
}
```

**Response (404 Not Found):**
- New customer, rating defaults to 1.0 (allow)

### Order Import Endpoint
```
POST https://api.codguard.com/api/orders/import
```

**Headers:**
- `Content-Type: application/json`
- `X-API-PUBLIC-KEY: {public_key}`
- `X-API-PRIVATE-KEY: {private_key}`

**Body:**
```json
{
  "orders": [...]
}
```

## Statistics & Monitoring

### Admin Dashboard

The statistics tab shows:

**Block Statistics:**
- Today's blocks
- Last 7 days
- Last 30 days
- All time total

**Recent Blocks Table:**
- Date & time of block
- Customer email
- Customer rating
- IP address

### Database Tables

**`oc_codguard_block_events`**
- Stores COD block events
- Cleaned automatically (90+ days old removed)

**`oc_codguard_order_queue`**
- Stores orders pending sync
- Cleaned automatically (sent orders 7+ days old removed)

### Logs

All activity is logged to OpenCart error log:
- Location: `system/storage/logs/error.log`
- Prefix: `CodGuard:`

**Example log entries:**
```
CodGuard: Rating API called for customer@example.com - HTTP 200
CodGuard: Blocked COD for customer@example.com (rating: 0.25)
CodGuard: Order #12345 queued for sync
CodGuard: Sending 5 bundled orders to API
CodGuard: Successfully sent 5 orders
```

## Troubleshooting

### COD Not Being Blocked

**Check:**
1. Extension is enabled (Status = Enabled)
2. API keys are configured correctly
3. Payment method is selected in COD methods list
4. Rating tolerance is set appropriately
5. Check error log for API errors

### Orders Not Syncing

**Check:**
1. Order status matches configured successful/refused status
2. Order has valid email address
3. Queue is not empty: check `oc_codguard_order_queue` table
4. API keys are correct (both public and private)
5. Check error log for API errors

### API Connection Issues

**Common causes:**
- Incorrect API keys
- cURL not enabled on server
- Firewall blocking outbound HTTPS connections
- API endpoint temporarily unavailable

**Solution:**
- Verify API keys in CodGuard dashboard
- Contact hosting provider to enable cURL
- Check server firewall settings
- Extension uses fail-open approach for rating checks

### Statistics Not Showing

**Check:**
1. Tables were created during installation
2. Run this SQL to verify:
```sql
SELECT COUNT(*) FROM oc_codguard_block_events;
SELECT COUNT(*) FROM oc_codguard_order_queue;
```

### Manual Queue Processing

To manually trigger queue processing, you can run:

```php
// In OpenCart admin or via cron
$this->load->model('extension/module/codguard');
$this->model_extension_module_codguard->sendBundledOrders();
```

## Maintenance & Cleanup

### Automatic Cleanup

The extension automatically cleans:
- Block events older than 90 days
- Sent orders older than 7 days

### Manual Cleanup

To manually clean old records:

```php
$this->load->model('extension/module/codguard');
$this->model_extension_module_codguard->cleanOldRecords();
```

### Recommended Cron Job

Add to your server cron to process queue and cleanup:

```bash
# Process queue every hour
0 * * * * php /path/to/opencart/admin/index.php extension/module/codguard/sendBundledOrders

# Cleanup old records daily at 3 AM
0 3 * * * php /path/to/opencart/admin/index.php extension/module/codguard/cleanOldRecords
```

## Uninstallation

### Standard Uninstall

1. Go to **Extensions > Extensions > Modules**
2. Find **CodGuard**
3. Click **Uninstall** (red minus icon)

**Note:** Database tables are preserved to retain historical data.

### Complete Removal

To completely remove including data:

1. Uninstall via admin panel
2. Run these SQL queries:

```sql
DROP TABLE IF EXISTS `oc_codguard_block_events`;
DROP TABLE IF EXISTS `oc_codguard_order_queue`;
DELETE FROM `oc_setting` WHERE `key` LIKE 'module_codguard%';
```

3. Delete extension files:
```bash
rm -rf admin/controller/extension/module/codguard.php
rm -rf admin/model/extension/module/codguard.php
rm -rf admin/language/en-gb/extension/module/codguard.php
rm -rf admin/view/template/extension/module/codguard.twig
rm -rf catalog/controller/extension/module/codguard.php
rm -rf catalog/model/extension/module/codguard.php
rm -rf catalog/language/en-gb/extension/module/codguard.php
```

## Security Considerations

### API Key Storage
- Private key is stored in database settings
- Use OpenCart's built-in security measures
- Never expose API keys in frontend code

### Rating Check Security
- Rating checks happen server-side only
- No sensitive data exposed to frontend
- IP addresses logged for security tracking

### Fail-open Philosophy
- If API is unreachable, orders proceed
- Prevents legitimate customers from being blocked
- Protects business continuity

## Performance Optimization

### Bundled Sync Benefits
- Reduces API calls (batches every hour)
- Lower server load
- Better API rate limit management
- Automatic retry for failed orders

### Database Optimization
- Indexes on frequently queried columns
- Automatic cleanup of old records
- Efficient queue processing

## Version History

### 1.0.0 (2025-11-20)
- Initial release
- Customer rating check at checkout
- Real-time bundled order sync
- Complete admin settings panel
- Statistics dashboard
- Block event tracking
- Fail-open approach
- Automatic cleanup

## Support

**Documentation:** https://codguard.com/docs
**Support Email:** info@codguard.com
**Extension Version:** 1.0.0
**Release Date:** November 20, 2025
**Compatible With:** OpenCart 3.x
**License:** GPL v2 or later

## Credits

**Developed by:** CodGuard Team
**Based on:** CodGuard for WooCommerce plugin
**Special Thanks:** OpenCart community for excellent documentation

---

**Note:** This extension requires an active CodGuard account. Visit https://codguard.com to sign up.
