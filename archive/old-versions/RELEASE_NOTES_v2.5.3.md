# Release Notes - CodGuard for OpenCart v2.5.3

**Release Date:** November 25, 2025
**Type:** Bug Fix Release
**Severity:** Critical

---

## Overview

Version 2.5.3 fixes a critical bug that caused JavaScript errors during checkout when customers with low ratings attempted to select Cash on Delivery (COD) payment method.

---

## Critical Fix

### JSON Response Error During Checkout

**Problem:**
- When a customer with rating below tolerance selected COD payment, the checkout displayed:
  - Browser console error: `SyntaxError: Unexpected token 'E', "Error: Unf"... is not valid JSON`
  - Payment method selection became unresponsive
  - Customers could not complete checkout

**Root Cause:**
- Validation code was throwing PHP exceptions during AJAX request
- OpenCart's error handler converted exception to plain text "Error: ..."
- JavaScript expected JSON response, received malformed text instead
- JSON.parse() failed, breaking checkout flow

**Solution:**
- Completely rewrote validation approach
- Moved from exception-based blocking to JSON response modification
- Validation now happens in `after` event hook
- Properly modifies `{"success": "..."}` to `{"error": "..."}`
- Maintains valid JSON throughout the entire process

---

## What Changed

### For End Users

**Before v2.5.3:**
- COD payment either:
  - Disappeared from payment list entirely (silent blocking), OR
  - Caused JavaScript errors when selected
- Poor user experience with broken checkout

**After v2.5.3:**
- COD payment shows for everyone in payment list ✅
- Low-rated customers see friendly error message when selecting COD ✅
- Error message: "Unfortunately, we cannot offer Cash on Delivery for this order." ✅
- Checkout flow remains smooth and responsive ✅

### For Developers

**Event Configuration Changes:**
- `codguard_filter_payment_methods` - **DISABLED**
  - Previously: Hid COD from payment list
  - Now: Disabled to allow COD to show

- `codguard_validate_payment_controller` - **DISABLED**
  - Previously: Threw exceptions (caused the bug)
  - Now: Disabled in favor of JSON modification approach

- `codguard_payment_save_intercept` - **ENHANCED**
  - Previously: Only intercepted existing errors
  - Now: Performs full validation and modifies JSON response

**Code Changes:**
- File: `catalog/controller/fraud/codguard.php`
- Method: `interceptPaymentSave()` - completely rewritten
- New logic:
  1. Checks if payment method successfully selected (`$json_data['success']`)
  2. Validates if selected method is COD
  3. Gets customer email from session
  4. Calls Codguard API for rating
  5. Compares rating against tolerance
  6. If below tolerance:
     - Removes `success` from JSON
     - Adds `error` with custom message
     - Logs block event
  7. Returns valid JSON (no exceptions)

---

## Installation

### For New Installations

1. Download `codguard-oc4-v2.5.3.ocmod.zip`
2. Login to OpenCart Admin
3. Navigate to: **Extensions → Installer**
4. Upload the `.ocmod.zip` file
5. Go to: **Extensions → Extensions → Fraud**
6. Install and configure CodGuard

### For Existing Installations (Upgrading)

**Important:** This is a critical bug fix. All existing installations should upgrade immediately.

**Upgrade Steps:**

1. **Backup First** (Recommended)
   ```bash
   # Backup the extension folder
   cp -r www/extension/codguard www/extension/codguard.backup

   # Backup database tables
   mysqldump -u user -p database oc_codguard_block_events oc_codguard_order_queue > codguard_backup.sql
   ```

2. **Upload New Version**
   - Login to OpenCart Admin
   - Go to: **Extensions → Installer**
   - Upload `codguard-oc4-v2.5.3.ocmod.zip`
   - Files will be automatically overwritten

3. **Clear Cache**
   - Go to: **Dashboard** (top right corner)
   - Click the blue **Settings** gear icon
   - Click **Refresh** buttons to clear cache

4. **Verify Events** (Manual Check)
   - The installer should configure events automatically
   - To verify manually, check database:

   ```sql
   SELECT event_id, code, `trigger`, status
   FROM oc_event
   WHERE code LIKE '%codguard%';
   ```

   Expected results:
   - `codguard_filter_payment_methods` → status = **0** (disabled)
   - `codguard_validate_payment_controller` → status = **0** (disabled)
   - `codguard_payment_save_intercept` → status = **1** (enabled)

5. **Test Checkout**
   - Use test email with low rating (< tolerance)
   - Add item to cart
   - Proceed to checkout
   - Select COD payment
   - Should see error message (not JavaScript error)

---

## Compatibility

- **OpenCart Version:** 4.0+
- **PHP Version:** 7.4+ (8.0+ recommended)
- **Requires:** cURL extension
- **Database:** MySQL 5.7+ / MariaDB 10.2+

---

## Upgrading from Older Versions

### From v2.5.2 or Earlier
- Direct upgrade supported ✅
- No database migrations needed
- Settings preserved
- Event configuration automatically updated

### From v2.4.x or Earlier
- Direct upgrade supported ✅
- Review event configuration after upgrade
- Test checkout flow thoroughly

### From v1.x
- Not recommended - too many changes
- Fresh install recommended

---

## Testing Checklist

After upgrading, verify:

- [ ] COD appears in payment method list for all customers
- [ ] High-rated customer (≥ tolerance) can select COD and complete order
- [ ] Low-rated customer (< tolerance) sees error message when selecting COD
- [ ] Error message displays in checkout UI (not console)
- [ ] No JavaScript errors in browser console
- [ ] Checkout remains responsive after error
- [ ] Block events are logged in admin statistics
- [ ] API calls are logged in OpenCart error log

---

## Known Issues

None identified in this release.

---

## Support

- **Documentation:** See `INSTALL.md` and `TROUBLESHOOTING.md`
- **Logs:** Check `storage/logs/error.log` for CodGuard debug messages
- **Events:** Verify event registration if issues occur
- **API:** Test API connectivity if validation not working

---

## Files Changed

### Modified Files
- `catalog/controller/fraud/codguard.php` - Main validation logic rewritten
- `install.json` - Version number updated to 2.5.3

### Configuration Files
- No configuration file changes
- Settings preserved during upgrade

### Database Schema
- No database changes in this release
- Existing tables remain unchanged

---

## Rollback Instructions

If you need to rollback to v2.5.2:

1. Restore backup files:
   ```bash
   rm -rf www/extension/codguard
   cp -r www/extension/codguard.backup www/extension/codguard
   ```

2. Re-enable old events:
   ```sql
   UPDATE oc_event SET status = 1 WHERE code = 'codguard_filter_payment_methods';
   UPDATE oc_event SET status = 0 WHERE code = 'codguard_payment_save_intercept';
   ```

3. Clear cache

**Note:** Rollback not recommended - v2.5.2 has the critical bug this release fixes.

---

## What's Next?

### Planned for v2.6.0
- Enhanced admin dashboard with charts
- Better email session detection
- Additional payment method support
- Performance optimizations

### Report Issues
If you encounter any issues with this release, please document:
- OpenCart version
- PHP version
- Error messages from `storage/logs/error.log`
- Browser console errors (if any)
- Steps to reproduce

---

**Changelog:** See `CHANGELOG.md` for complete version history
**License:** GPL v2 or later
