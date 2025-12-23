# CodGuard Payment Filter Implementation

## Summary of Changes

The CodGuard extension has been modified to filter payment methods at the optimal point in the checkout flow. This ensures COD payment methods are filtered out BEFORE the customer even sees them on the payment selection page.

---

## What Changed?

### 1. New Event Added (PRIMARY)

**File**: `codguard-oc4/admin/model/fraud/codguard.php`

Added a new event registration in the `install()` method:

```php
// MAIN EVENT: Filter payment methods when they're loaded
$this->model_setting_event->addEvent([
    'code' => 'codguard_filter_payment_methods',
    'description' => 'CodGuard filter payment methods based on customer rating',
    'trigger' => 'catalog/model/checkout/payment_method/getMethods/after',
    'action' => 'extension/codguard/fraud/codguard.filterPaymentMethods',
    'status' => 1,
    'sort_order' => 0
]);
```

**Event Trigger**: `catalog/model/checkout/payment_method/getMethods/after`
- Fires when payment methods are being loaded
- Happens AFTER customer clicks "Continue" from Delivery Method step
- Happens BEFORE payment methods are displayed to customer

### 2. New Method Added

**File**: `codguard-oc4/catalog/controller/fraud/codguard.php`

Added new `filterPaymentMethods()` method (lines 37-165):

**Key Features**:
- ✅ Checks if module is enabled
- ✅ Gets customer email from session (guest or logged-in)
- ✅ Fetches customer rating from CodGuard API
- ✅ Compares rating against tolerance threshold
- ✅ **Removes COD methods from payment options if rating too low**
- ✅ Logs all actions with detailed debug info
- ✅ Fail-open approach (allows checkout if API fails)

---

## How It Works

### Checkout Flow Comparison

#### Before (Old Approach):
```
1. Customer fills shipping info → Continue
2. Payment methods page loads (COD visible)
3. Customer selects COD
4. JavaScript tries to validate (often fails to trigger)
5. Multiple backup events try to catch it
6. Sometimes validation happens too late
```

#### After (New Approach):
```
1. Customer fills shipping info → Continue
2. ✅ API call happens HERE (getMethods/after event)
3. ✅ COD filtered out if rating low
4. Payment methods page loads (COD already removed if blocked)
5. Customer only sees allowed payment methods
6. Smooth checkout, no errors
```

---

## Technical Details

### Event Timing

The event fires at the perfect moment:

```
Customer Information Step
        ↓
    [Continue]
        ↓
Delivery Method Step
        ↓
    [Continue] ← Customer clicks this
        ↓
OpenCart calls getMethods() to load payment options
        ↓
    🔴 filterPaymentMethods() EVENT FIRES HERE
        ↓
API checks customer rating
        ↓
COD removed from array if rating < tolerance
        ↓
Payment Method page displays (with filtered list)
        ↓
Customer selects from allowed methods
        ↓
    [Continue]
        ↓
Confirm Order page
```

### Filtering Logic

The method removes COD methods from the `$output` array:

```php
// Direct match
if (isset($output[$cod_method])) {
    unset($output[$cod_method]);
}

// Pattern match for prefixed codes (e.g., "cod.cod")
foreach ($output as $key => $method) {
    if (strpos($key, $cod_method . '.') === 0) {
        unset($output[$key]);
    }
}
```

---

## Installation Instructions

### For Existing Installations

If you already have CodGuard installed, you need to **reinstall** to register the new event:

1. **Backup your settings** (they will be preserved in `codguard_settings` table)

2. Go to: **Extensions → Extensions → Choose extension type: Fraud**

3. Find **CodGuard** and click the **Uninstall** button (red minus icon)

4. Click the **Install** button (green plus icon)

5. Click **Edit** to verify your settings are still there

6. Test with a low-rated customer

### For New Installations

Just install normally - the new event will be registered automatically.

### Verifying Installation

Check the OpenCart event table:

```sql
SELECT * FROM oc_event
WHERE code = 'codguard_filter_payment_methods';
```

You should see:
- **code**: `codguard_filter_payment_methods`
- **trigger**: `catalog/model/checkout/payment_method/getMethods/after`
- **action**: `extension/codguard/fraud/codguard.filterPaymentMethods`
- **status**: `1` (enabled)

---

## Testing

### Test Scenario 1: Low-Rated Customer

1. Configure tolerance to 35% (0.35)
2. Use test customer with rating < 0.35
3. Proceed to checkout
4. Fill shipping info → Continue
5. Select delivery method → Continue
6. **Expected**: COD should NOT appear in payment method list

### Test Scenario 2: Good-Rated Customer

1. Use test customer with rating >= 0.35
2. Proceed to checkout
3. **Expected**: COD appears normally in payment method list

### Test Scenario 3: API Failure

1. Configure with invalid API keys
2. Proceed to checkout
3. **Expected**: All payment methods shown (fail-open policy)

### Monitoring

Check logs at: `system/storage/logs/error.log`

Look for:
```
CodGuard [DEBUG]: filterPaymentMethods() EVENT FIRED!
CodGuard [INFO]: Checking customer rating for filtering - Email: customer@example.com
CodGuard [INFO]: Customer rating OK (0.75 >= 0.35) - allowing all payment methods including COD
```

Or if blocked:
```
CodGuard [WARNING]: Customer rating BELOW tolerance - FILTERING OUT COD methods
CodGuard [INFO]: REMOVED payment method: cod (Cash on Delivery)
CodGuard [INFO]: Payment methods filtered - remaining count: 2
```

---

## Advantages of This Approach

### Compared to Previous CodGuard Implementation:

1. **✅ More Reliable**: Event always fires when payment methods load
2. **✅ Better UX**: Customer never sees COD option if blocked (vs. error after selection)
3. **✅ Cleaner**: No JavaScript dependencies, no AJAX validation
4. **✅ Fail-Safe**: Still has backup events for edge cases

### Compared to AJAX/JavaScript Validation:

1. **✅ Always Works**: Server-side, can't be bypassed
2. **✅ No Race Conditions**: Happens before page render
3. **✅ No Timing Issues**: Not dependent on DOM ready events
4. **✅ Works Without JS**: Functions even if JavaScript disabled

---

## Backward Compatibility

The existing validation methods are **still in place** as backup:
- `validatePaymentMethodController()` - AJAX validation
- `validatePaymentMethod()` - Model-level validation
- `validateCheckout()` - Order creation validation
- `validateCheckoutConfirm()` - Confirm page validation

These provide additional safety layers, but the **primary mechanism** is now the payment method filter.

---

## Configuration

No configuration changes needed! Uses existing settings:

| Setting | Purpose |
|---------|---------|
| `module_codguard_status` | Enable/disable module |
| `module_codguard_shop_id` | Your shop ID |
| `module_codguard_public_key` | API public key |
| `module_codguard_rating_tolerance` | Minimum rating (%) |
| `module_codguard_cod_methods` | COD payment codes to filter |

---

## Troubleshooting

### COD Still Appearing for Low-Rated Customers

1. **Check event is registered**:
   ```sql
   SELECT * FROM oc_event WHERE code = 'codguard_filter_payment_methods';
   ```

2. **Check event is enabled**:
   - Status should be `1`

3. **Verify COD method code matches**:
   - Go to admin settings
   - Check "COD Payment Methods" setting
   - Ensure it includes your COD method code (e.g., "cod")

4. **Check logs**:
   - Look for "filterPaymentMethods() EVENT FIRED"
   - If not present, event isn't triggering

### Event Not Firing

1. **Reinstall the extension** (uninstall → install)
2. **Clear OpenCart cache**:
   - Delete `system/storage/cache/*`
3. **Check OpenCart version**: Must be 4.x

---

## Files Modified

1. **codguard-oc4/admin/model/fraud/codguard.php**
   - Added event registration (line ~70)
   - Added event cleanup (line ~161)
   - Updated installation log message (line ~152)

2. **codguard-oc4/catalog/controller/fraud/codguard.php**
   - Added `filterPaymentMethods()` method (lines 37-165)

---

## Implementation Details

### Key Implementation Features

| Feature | Implementation |
|---------|----------------|
| Event trigger | `getMethods/after` |
| Filters before display | ✅ Yes |
| Fail-open on API error | ✅ Yes |
| Detailed logging | ✅ Yes |
| Pattern matching | ✅ Supports "cod" and "cod.cod" |

---

## Next Steps

1. ✅ **Reinstall** CodGuard to register the new event
2. ✅ **Test** with low-rated and high-rated customers
3. ✅ **Monitor logs** to verify event is firing
4. ✅ **Enjoy** reliable COD blocking!

---

## Support

If you encounter issues:

1. Check `system/storage/logs/error.log`
2. Verify event is registered and enabled
3. Ensure COD method codes match configuration
4. Test with known rating values

---

## Credits

Implementation uses the `getMethods/after` event to filter payment methods in real-time during checkout, ensuring optimal timing and reliability.
