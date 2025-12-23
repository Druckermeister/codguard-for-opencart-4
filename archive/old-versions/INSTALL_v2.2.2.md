# CodGuard for OpenCart v2.2.2 - Installation Guide

## 📦 Package Information

- **File:** `codguard-oc4-v2.2.2.ocmod.zip`
- **Size:** 36 KB
- **MD5:** `c3b8719b877f95f6c0248a2c646bd7a6`
- **Version:** 2.2.2
- **Release Date:** January 24, 2025
- **Compatibility:** OpenCart 4.x

---

## 🆕 New Installation

### Step 1: Upload Extension

1. Log in to your **OpenCart Admin Panel**
2. Navigate to **Extensions → Installer**
3. Click **Upload** button
4. Select `codguard-oc4-v2.2.2.ocmod.zip`
5. Wait for upload to complete
6. You should see a success message

### Step 2: Install Extension

1. Navigate to **Extensions → Extensions**
2. Choose **Fraud** from the extension type dropdown
3. Find **CodGuard** in the list
4. Click the **Install** button (green plus icon)
5. Wait for installation to complete

### Step 3: Configure Extension

1. Click the **Edit** button (blue pencil icon) next to CodGuard
2. Configure the following settings:

   **Required Settings:**
   - **Status:** Enabled
   - **Shop ID:** Your CodGuard shop ID (from CodGuard dashboard)
   - **Public Key:** Your CodGuard public API key (from CodGuard dashboard)
   - **Private Key:** Your CodGuard private API key (from CodGuard dashboard)

   **COD Payment Settings:**
   - **COD Payment Methods:** Enter your COD payment method code(s)
     - Common: `cod`, `cash_on_delivery`, `cash`
     - To find yours: Go to Extensions → Payments and note the code
     - Multiple methods: Separate with commas (e.g., `cod,cash`)

   **Rating Settings:**
   - **Rating Tolerance:** Percentage threshold (default: 70)
     - Customers with rating below this won't be able to use COD
     - 70 = 70% = 0.7 rating threshold

   **Order Sync Settings:**
   - **Good Order Status:** Status for successful COD orders (e.g., Complete)
   - **Refused Order Status:** Status for refused/returned orders (e.g., Canceled)

   **Optional Settings:**
   - **Rejection Message:** Custom message shown when COD is blocked
     - Default: "Cash on Delivery is not available for your account."

3. Click **Save** (blue disk icon in top-right)

### Step 4: Verify Installation

**Run the test endpoint:**
```
https://your-opencart-site.com/index.php?route=extension/codguard/fraud/codguard.testApi&email=test@example.com
```

You should see a JSON response like:
```json
{
    "test_time": "2025-01-24 14:30:00",
    "email": "test@example.com",
    "configuration": {
        "module_enabled": "YES",
        "shop_id": "12345",
        "public_key": "abcdef1234... (64 chars)",
        "cod_methods": "cod",
        "rating_tolerance": "70%"
    },
    "api_test": {
        "status": "SUCCESS",
        "rating": 0.85,
        "tolerance": 0.7,
        "would_allow": "YES"
    }
}
```

**Check the error log:**
- Admin → System → Maintenance → Error Logs
- Look for entries starting with `CodGuard`
- You should see detailed logging of the test API call

### Step 5: Test with Checkout

1. Add a product to cart
2. Go to checkout
3. Enter an email address
4. Select COD payment method
5. Check error log - you should see validation logs

---

## ⬆️ Upgrading from Previous Version

### From v2.2.0 or v2.2.1

**Easy upgrade - no configuration changes needed!**

1. **Backup** (recommended but optional - no database changes)
2. Navigate to **Extensions → Installer**
3. Upload `codguard-oc4-v2.2.2.ocmod.zip`
4. Navigate to **Extensions → Extensions** → choose **Fraud**
5. Click **Install** on CodGuard (green plus)
6. Your settings are **automatically preserved**
7. Run test endpoint to verify: `.../index.php?route=extension/codguard/fraud/codguard.testApi`

### From v2.1.x or Earlier

1. **Backup your configuration** (write down your API keys and settings)
2. Navigate to **Extensions → Extensions** → choose **Fraud**
3. **Uninstall** the old version (red minus icon)
4. Follow **New Installation** steps above
5. **Reconfigure** with your API keys and settings
6. Run test endpoint to verify

---

## 🔧 Configuration Details

### Finding Your Payment Method Code

**Method 1: Via Admin Panel**
1. Go to **Extensions → Extensions**
2. Choose **Payments** from dropdown
3. Look at your COD payment extension
4. The **code** is visible in the list (usually `cod`)

**Method 2: Via Test Checkout**
1. Add item to cart and go to checkout
2. Open browser developer tools (F12)
3. Go to Network tab
4. Select COD payment
5. Look at the POST request - the `payment_method` value is your code

**Common COD Payment Codes:**
- `cod` (most common)
- `cash_on_delivery`
- `cash`
- `cod_fee`
- `cashondelivery`

### Getting Your CodGuard API Keys

1. Log in to your **CodGuard Dashboard**
2. Go to **Settings** or **API Keys** section
3. Copy the following:
   - **Shop ID** (numeric)
   - **Public Key** (long string, 64+ characters)
   - **Private Key** (long string, 64+ characters)
4. Paste into OpenCart CodGuard settings

### Rating Tolerance Explained

The rating tolerance is a percentage (0-100) that determines the minimum customer rating required for COD:

- **70%** (default) = Customer needs rating ≥ 0.70 to use COD
- **80%** (strict) = Customer needs rating ≥ 0.80 to use COD
- **50%** (lenient) = Customer needs rating ≥ 0.50 to use COD

**Recommendations:**
- **New shops:** Start with 60-70%
- **Established shops:** Use 70-80%
- **High-risk areas:** Consider 80-90%

### Order Status Configuration

**Good Order Status:**
- Choose the status that indicates successful COD delivery
- Usually: "Complete", "Delivered", "Shipped"
- This tells CodGuard the customer paid successfully

**Refused Order Status:**
- Choose the status for refused/returned COD orders
- Usually: "Canceled", "Returned", "Failed"
- This tells CodGuard the customer refused delivery

---

## ✅ Post-Installation Checklist

After installation, verify:

- [ ] Extension shows as **Installed** in Extensions → Fraud
- [ ] Module status is **Enabled** in settings
- [ ] Shop ID is configured
- [ ] Public Key is configured
- [ ] Private Key is configured
- [ ] At least one COD payment method is configured
- [ ] Rating tolerance is set
- [ ] Good/Refused order statuses are selected
- [ ] Test endpoint returns JSON response
- [ ] Error log shows CodGuard entries
- [ ] Test checkout shows validation in logs

---

## 🔍 Troubleshooting Installation

### Extension Won't Upload

**Error:** "File type not allowed" or upload fails

**Solutions:**
1. Check file extension is `.ocmod.zip`
2. Verify file isn't corrupted (check MD5: `c3b8719b877f95f6c0248a2c646bd7a6`)
3. Increase PHP upload limits:
   - `upload_max_filesize = 10M`
   - `post_max_size = 10M`
4. Try uploading via FTP:
   - Extract `codguard-oc4-v2.2.2.ocmod.zip`
   - Upload contents to OpenCart root
   - Files go to: `admin/`, `catalog/`

### Extension Won't Install

**Error:** Installation fails or shows errors

**Solutions:**
1. Check file permissions (755 for directories, 644 for files)
2. Verify OpenCart version is 4.x
3. Check error logs for specific error messages
4. Try uninstalling old version first
5. Clear OpenCart cache: System → Maintenance → Clear cache

### Extension Won't Enable

**Error:** Can't set status to "Enabled" or settings won't save

**Solutions:**
1. Check API keys are correctly entered (no extra spaces)
2. Verify Shop ID is numeric
3. Check COD payment method code exists
4. Clear browser cache
5. Try different browser

### Test Endpoint Shows Errors

**Error:** Test endpoint returns error or 404

**Solutions:**
1. Verify URL format: `index.php?route=extension/codguard/fraud/codguard.testApi`
2. Check mod_rewrite/SEO URLs if using pretty URLs
3. Verify extension is installed (not just uploaded)
4. Check file exists: `catalog/controller/fraud/codguard.php`
5. Check error log for specific errors

---

## 📖 Next Steps

After successful installation:

1. **Read the documentation:**
   - **QUICK_DEBUG.md** - Quick reference for common issues
   - **DEBUGGING.md** - Comprehensive troubleshooting guide
   - **RELEASE_NOTES_v2.2.2.md** - What's new in this version

2. **Test thoroughly:**
   - Run test endpoint with various emails
   - Do test checkouts with COD payment
   - Monitor error logs

3. **Monitor logs:**
   - Check logs daily for first week
   - Look for any `[ERROR]` entries
   - Verify API calls are working

4. **Fine-tune settings:**
   - Adjust rating tolerance based on results
   - Update rejection message to match your brand
   - Configure order statuses to match your workflow

---

## 🆘 Getting Help

### Self-Help Resources

1. **Test Endpoint:**
   ```
   https://your-site.com/index.php?route=extension/codguard/fraud/codguard.testApi&email=test@example.com
   ```
   Shows current configuration and API status

2. **Error Logs:**
   ```
   Admin → System → Maintenance → Error Logs
   ```
   Look for `CodGuard` entries with detailed diagnostics

3. **Documentation:**
   - QUICK_DEBUG.md - Fast solutions
   - DEBUGGING.md - Detailed troubleshooting

### Requesting Support

When contacting support, provide:

1. **Test endpoint output** (JSON response)
2. **Error log entries** (grep for "CodGuard")
3. **Configuration screenshot** (with API keys masked)
4. **OpenCart version**
5. **Plugin version** (should be 2.2.2)
6. **Description of issue**

This information enables much faster diagnosis!

---

## 🔐 Security Notes

- **Protect your API keys** - never share them publicly
- **Restrict test endpoint** in production (optional):
  - Use .htaccess to limit access by IP
  - Or remove after testing
- **Monitor logs** for suspicious activity
- **Regular backups** of configuration

---

## 📊 System Requirements

- **OpenCart:** 4.x (4.0.0.0 or higher)
- **PHP:** 7.4 or higher
- **MySQL:** 5.6 or higher
- **cURL:** Enabled (for API calls)
- **SSL/TLS:** Enabled (for secure API communication)
- **Disk Space:** Minimal (~1 MB)
- **Memory:** No special requirements

**Recommended:**
- PHP 8.0+
- MySQL 8.0+
- HTTPS enabled
- Regular backups

---

## 📁 File Structure

After installation, files are located at:

```
admin/
├── controller/
│   ├── fraud/codguard.php
│   └── module/codguard.php
├── model/
│   ├── fraud/codguard.php
│   └── module/codguard.php
├── language/en-gb/
│   ├── fraud/codguard.php
│   └── module/codguard.php
└── view/template/
    ├── fraud/codguard.twig
    └── module/codguard.twig

catalog/
├── controller/
│   ├── fraud/codguard.php (with testApi method)
│   └── module/codguard.php
├── model/
│   ├── fraud/codguard.php (enhanced logging)
│   └── module/codguard.php
└── language/en-gb/
    ├── fraud/codguard.php
    └── module/codguard.php
```

---

## ✨ What's New in v2.2.2

- ✅ Comprehensive logging (40+ log points)
- ✅ API test endpoint
- ✅ Enhanced error diagnostics
- ✅ Configuration validation
- ✅ Complete execution flow visibility
- ✅ Better debugging than WooCommerce version!

See **RELEASE_NOTES_v2.2.2.md** for full details.

---

**Installation Complete! 🎉**

You now have CodGuard v2.2.2 installed with comprehensive debugging capabilities. If you encounter any issues, the enhanced logging will show you exactly what's happening at every step.
