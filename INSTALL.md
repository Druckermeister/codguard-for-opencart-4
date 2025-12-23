# CodGuard for OpenCart 4.x - Installation Guide

## Overview

CodGuard protects your business from COD (Cash on Delivery) fraud by validating customer trustworthiness in real-time during checkout. Low-rated customers are automatically prevented from selecting COD payment.

## Installation Steps

### 1. Download the Extension

1. Click the green **"Code"** button on GitHub
2. Select **"Download ZIP"**
3. Extract the ZIP file to your computer

### 2. Prepare for Upload

1. Navigate to the extracted `codguard-for-opencart-4` folder
2. You'll see folders: `admin/`, `catalog/`, and files: `install.json`, `install.php`
3. Create a ZIP archive containing these folders and files
4. Name it `codguard-oc4.ocmod.zip`

### 3. Install via OpenCart Admin Panel

1. Go to **Extensions → Installer**
2. Click **Upload**
3. Select the `codguard-oc4.ocmod.zip` file
4. Wait for upload to complete
5. Go to **Extensions → Extensions**
6. Select **Fraud** from the extension type dropdown
7. Find **CodGuard** and click **Install** (green plus icon)
8. Click **Edit** to configure your settings

## Configuration

### Required Settings

Navigate to **Extensions → Extensions → Fraud → CodGuard** and configure:

#### 1. API Configuration
- **Shop ID** - Your unique shop identifier from CodGuard dashboard
- **Public Key** - Your API public key (minimum 10 characters)
- **Private Key** - Your API private key (minimum 10 characters)

#### 2. Payment Method Selection
Select which payment methods should trigger customer rating validation:
- Typically "Cash On Delivery" or similar COD methods
- Only checked methods will be validated

#### 3. Rating Settings
- **Rating Tolerance** - Minimum acceptable customer rating (0-100%)
  - Recommended: 30-40%
  - Example: If set to 35%, customers with rating below 35% cannot use COD
- **Rejection Message** - Message displayed to blocked customers
  - Default: "Unfortunately, we cannot offer Cash on Delivery for this order."

#### 4. Module Status
- **Status** - Enable or disable the extension

## How It Works

### Customer Checkout Flow

1. Customer enters checkout with billing information
2. Customer selects COD payment method
3. **Extension checks customer rating** via CodGuard API
4. **If rating < tolerance:** Order is blocked with error message
5. **If rating ≥ tolerance:** Order proceeds normally
6. **If API fails:** Order proceeds (fail-open approach for customer-friendliness)

### Security & Privacy

- Rating checks happen **server-side only**
- No sensitive data exposed to frontend
- **Fail-open philosophy:** If API is unreachable, orders proceed to protect business continuity
- IP addresses logged for security tracking

## Verification

After installation, test the extension:

### 1. Check Database Tables

```sql
SHOW TABLES LIKE '%codguard%';
```

You should see:
- `oc_codguard_block_events`
- `oc_codguard_order_queue`

### 2. Test COD Blocking

1. Enable the extension in admin panel
2. Configure your API credentials
3. Set rating tolerance (e.g., 35%)
4. Place a test order as a customer
5. Select COD payment method
6. Extension will validate rating and block/allow accordingly

### 3. Check Logs

View extension activity in OpenCart error log:
```bash
tail -f storage/logs/error.log | grep "CodGuard"
```

You should see entries like:
- `CodGuard: Rating API called for customer@example.com - HTTP 200`
- `CodGuard: Blocked COD for customer@example.com (rating: 0.25)`

## Statistics Dashboard

The admin panel includes a statistics tab showing:

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

**Automatic Cleanup:**
- Block events older than 90 days are automatically removed

## Troubleshooting

### COD Not Being Blocked

**Check:**
1. Extension is enabled (Status = Enabled)
2. API keys are configured correctly
3. Payment method is selected in COD methods list
4. Rating tolerance is set appropriately
5. Check error log for API errors

### API Connection Issues

**Common causes:**
- Incorrect API keys
- cURL not enabled on server
- Firewall blocking outbound HTTPS connections
- API endpoint temporarily unavailable

**Solutions:**
- Verify API keys in CodGuard dashboard
- Contact hosting provider to enable cURL
- Check server firewall settings
- Extension uses fail-open approach (customers can still checkout)

### Statistics Not Showing

**Check:**
1. Tables were created during installation
2. Run this SQL to verify:
   ```sql
   SELECT COUNT(*) FROM oc_codguard_block_events;
   ```

## Uninstallation

### Standard Uninstall

1. Go to **Extensions → Extensions → Fraud**
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

## API Endpoint Used

The extension uses the CodGuard Customer Rating API:

```
GET https://api.codguard.com/api/customer-rating/{shop_id}/{email}
Headers:
  Accept: application/json
  x-api-key: {public_key}
```

**Response (200 OK):**
```json
{
  "rating": 0.75
}
```

**Response (404 Not Found):**
- New customer, rating defaults to 1.0 (allow)

## Requirements

- **OpenCart:** 4.x
- **PHP:** 7.4 or higher
- **PHP Extensions:** cURL must be enabled
- **CodGuard Account:** Active account with Shop ID and API keys

## Support

- **Documentation:** https://codguard.com/docs
- **Support Email:** info@codguard.com
- **Extension Version:** 1.0.0
- **Compatible With:** OpenCart 4.x
- **License:** GPL v2 or later

---

**Note:** This extension requires an active CodGuard account. Visit https://codguard.com to sign up.
