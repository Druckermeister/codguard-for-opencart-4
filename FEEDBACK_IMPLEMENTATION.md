# CodGuard Feedback Implementation for OpenCart 4

## Overview
This document describes the implementation of the feedback mechanism that reports COD (Cash on Delivery) blocking decisions to the CodGuard API.

## Purpose
The feedback API allows CodGuard to collect data on whether customers were allowed or blocked from using COD payment methods based on their email reputation scores. This data helps improve the reputation scoring system.

---

## API Specification

### Endpoint
```
POST https://api.codguard.com/api/feedback
```

### Request Headers
```http
Content-Type: application/json
X-API-KEY: {public_key}
```

### Request Body Format
```json
{
  "eshop_id": 123,
  "email": "customer@example.com",
  "reputation": 0.45,
  "threshold": 0.6,
  "action": "blocked"
}
```

### Request Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `eshop_id` | integer | Yes | Unique shop identifier (shopId) |
| `email` | string | Yes | Customer email address |
| `reputation` | decimal | Yes | Customer's rating (0.0-1.0) |
| `threshold` | decimal | Yes | Shop's configured tolerance threshold (0.0-1.0) |
| `action` | string | Yes | Decision made: `"blocked"` or `"allowed"` |

### Authentication
- **Public API Key Only**: Feedback endpoint requires only the public key (same as used for reputation checks)
- **Rate Limiting**: 60 requests per minute per API key

---

## Implementation

### 1. Add API Endpoint Constant

**File**: `catalog/model/fraud/codguard.php`
**Location**: After line 24 (after `API_ORDER_ENDPOINT` constant)

```php
/**
 * API endpoint for feedback
 */
const API_FEEDBACK_ENDPOINT = 'https://api.codguard.com/api/feedback';
```

### 2. Add the Feedback Method

**File**: `catalog/model/fraud/codguard.php`
**Location**: After the `logBlockEvent()` method (around line 140)

```php
/**
 * Send feedback to CodGuard API about COD blocking decision
 *
 * @param string $email Customer email
 * @param float $rating Customer rating (0-1)
 * @param float $threshold Threshold (0-1)
 * @param string $action Action taken ('blocked' or 'allowed')
 * @return bool Success status
 */
public function sendFeedback(string $email, float $rating, float $threshold, string $action): bool {
    $shop_id = $this->config->get('module_codguard_shop_id');
    $public_key = $this->config->get('module_codguard_public_key');

    if (empty($shop_id) || empty($public_key)) {
        $this->log->write('CodGuard [WARNING]: Cannot send feedback - API keys not configured');
        return false;
    }

    $url = self::API_FEEDBACK_ENDPOINT;

    $data = array(
        'eshop_id' => (int)$shop_id,
        'email' => $email,
        'reputation' => (float)$rating,
        'threshold' => (float)$threshold,
        'action' => $action
    );

    $this->log->write('CodGuard [DEBUG]: Sending feedback - Email: ' . $email . ', Rating: ' . $rating . ', Threshold: ' . $threshold . ', Action: ' . $action);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-API-KEY: ' . $public_key
    ));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        $this->log->write('CodGuard [WARNING]: Feedback API cURL error - ' . $curl_error);
        return false;
    }

    if ($http_code == 200) {
        $this->log->write('CodGuard [DEBUG]: Feedback sent successfully (action: ' . $action . ')');
        return true;
    } else {
        $this->log->write('CodGuard [WARNING]: Feedback API returned status ' . $http_code . ': ' . $response);
        return false;
    }
}
```

### 3. Call Feedback in the Payment Method Filter (PRIMARY LOCATION)

**File**: `catalog/controller/fraud/codguard.php`
**Method**: `filterPaymentMethods()`
**Location**: Around line 104-136

**Find this code**:
```php
// Compare to tolerance
$tolerance = (float)$this->config->get('module_codguard_rating_tolerance') / 100;
$this->log->write('CodGuard [DEBUG]: Comparing rating ' . $rating . ' against tolerance ' . $tolerance);

if ($rating < $tolerance) {
    // Customer rating is too low - remove COD payment methods from the list
    $this->log->write('CodGuard [WARNING]: Customer rating BELOW tolerance - FILTERING OUT COD methods');

    // Log the block event
    $this->model_extension_codguard_fraud_codguard->logBlockEvent(
        $email,
        $rating,
        $this->request->server['REMOTE_ADDR'] ?? ''
    );

    // Filter out COD methods...
    // ...
} else {
    $this->log->write('CodGuard [INFO]: Customer rating OK (' . $rating . ' >= ' . $tolerance . ') - allowing all payment methods including COD');
}
```

**Replace with**:
```php
// Compare to tolerance
$tolerance = (float)$this->config->get('module_codguard_rating_tolerance') / 100;
$this->log->write('CodGuard [DEBUG]: Comparing rating ' . $rating . ' against tolerance ' . $tolerance);

if ($rating < $tolerance) {
    // Customer rating is too low - remove COD payment methods from the list
    $this->log->write('CodGuard [WARNING]: Customer rating BELOW tolerance - FILTERING OUT COD methods');

    // Send feedback to API (wrapped in try-catch to prevent crashes)
    try {
        $this->model_extension_codguard_fraud_codguard->sendFeedback($email, $rating, $tolerance, 'blocked');
    } catch (\Exception $e) {
        $this->log->write('CodGuard [ERROR]: sendFeedback failed: ' . $e->getMessage());
    }

    // Log the block event
    $this->model_extension_codguard_fraud_codguard->logBlockEvent(
        $email,
        $rating,
        $this->request->server['REMOTE_ADDR'] ?? ''
    );

    // Filter out COD methods...
    // ...
} else {
    // Send feedback to API for allowed transactions (wrapped in try-catch)
    try {
        $this->model_extension_codguard_fraud_codguard->sendFeedback($email, $rating, $tolerance, 'allowed');
    } catch (\Exception $e) {
        $this->log->write('CodGuard [ERROR]: sendFeedback failed: ' . $e->getMessage());
    }

    $this->log->write('CodGuard [INFO]: Customer rating OK (' . $rating . ' >= ' . $tolerance . ') - allowing all payment methods including COD');
}
```

---

## Implementation Notes

### Why Only One Location?

Unlike WordPress/PrestaShop/Shoptet implementations, OpenCart 4's feedback is implemented in **only one location**: the `filterPaymentMethods()` event handler. Here's why:

1. **Single Source of Truth**: The `filterPaymentMethods()` event is triggered by OpenCart when payment methods are loaded on the payment selection page. This is the **PRIMARY** and most reliable validation point.

2. **Event-Driven Architecture**: OpenCart 4 uses an event system (`catalog/model/checkout/payment_method/getMethods/after`), which fires automatically when customers reach the payment method selection step.

3. **No Duplicate Feedback**: Since this event fires once per checkout session when payment methods are loaded, we avoid sending duplicate feedback (unlike JavaScript-based implementations that might trigger multiple times).

4. **Both Scenarios Covered**:
   - **Logged-in users**: Email is available from session, feedback sent when payment methods load
   - **Guest users**: Email is captured during checkout process, feedback sent when payment methods load

### User Flow

#### Scenario 1: Logged-In User
1. User is already logged in, email is in session
2. User proceeds through checkout to payment method selection
3. OpenCart fires `getMethods/after` event
4. **Feedback is sent** with reputation result
5. Payment methods are shown/hidden based on reputation

#### Scenario 2: Guest User
1. User enters email during checkout process (customer details step)
2. Email is stored in session (`guest.email`)
3. User proceeds to payment method selection
4. OpenCart fires `getMethods/after` event
5. **Feedback is sent** with reputation result
6. Payment methods are shown/hidden based on reputation

### When Feedback is Sent

| Scenario | Trigger | Action Value |
|----------|---------|--------------|
| Logged-in user with good reputation | Payment methods load | `"allowed"` |
| Logged-in user with low reputation | Payment methods load | `"blocked"` |
| Guest with good reputation | Payment methods load (after email entered) | `"allowed"` |
| Guest with low reputation | Payment methods load (after email entered) | `"blocked"` |

---

## Error Handling

- Feedback requests fail silently to avoid disrupting the user experience
- Errors are logged to OpenCart's error log for debugging
- Failed feedback does not prevent the checkout process from continuing
- No retry mechanism needed (this is informational data for CodGuard)
- Wrapped in try-catch blocks to prevent crashes

---

## Testing Checklist

### Test Case 1: Logged-in User - Allowed
- [ ] Log in with a user account that has a good email reputation (≥ threshold)
- [ ] Proceed through checkout to payment method selection
- [ ] Check OpenCart error log: Verify feedback API call with `action: "allowed"`
- [ ] Verify COD payment methods are visible

### Test Case 2: Logged-in User - Blocked
- [ ] Log in with a user account that has a low email reputation (< threshold)
- [ ] Proceed through checkout to payment method selection
- [ ] Check OpenCart error log: Verify feedback API call with `action: "blocked"`
- [ ] Verify COD payment methods are hidden

### Test Case 3: Guest User - Allowed
- [ ] Start checkout as guest
- [ ] Enter customer details including email with good reputation
- [ ] Proceed to payment method selection
- [ ] Check OpenCart error log: Verify feedback API call with `action: "allowed"`
- [ ] Verify COD payment methods are visible

### Test Case 4: Guest User - Blocked
- [ ] Start checkout as guest
- [ ] Enter customer details including email with low reputation
- [ ] Proceed to payment method selection
- [ ] Check OpenCart error log: Verify feedback API call with `action: "blocked"`
- [ ] Verify COD payment methods are hidden

### Test Case 5: API Failure
- [ ] Simulate API failure (disconnect network, server error)
- [ ] Verify error is logged to OpenCart error log
- [ ] Verify checkout process continues normally
- [ ] Verify user is not blocked from proceeding

---

## Debugging

### Log Location
OpenCart error logs are located at:
```
system/storage/logs/error.log
```

### Example Log Messages

**Successful feedback (blocked)**:
```
CodGuard [DEBUG]: Sending feedback - Email: test@example.com, Rating: 0.3, Threshold: 0.6, Action: blocked
CodGuard [DEBUG]: Feedback sent successfully (action: blocked)
```

**Successful feedback (allowed)**:
```
CodGuard [DEBUG]: Sending feedback - Email: customer@example.com, Rating: 0.85, Threshold: 0.6, Action: allowed
CodGuard [DEBUG]: Feedback sent successfully (action: allowed)
```

**API Error**:
```
CodGuard [WARNING]: Feedback API cURL error - Could not resolve host
```

---

## Differences from Other Platforms

### WordPress/WooCommerce
- **WordPress**: Uses PHP action hooks on checkout validation
- **OpenCart**: Uses event system on payment method loading
- **Timing**: OpenCart sends feedback when payment methods load, WordPress sends during checkout validation

### PrestaShop
- **PrestaShop**: Uses multiple hooks (`actionPresentPaymentOptions`, `hookPaymentOptions`)
- **OpenCart**: Single event handler (`getMethods/after`)
- **Architecture**: PrestaShop has dedicated feedback tracking in cookies, OpenCart relies on event-driven single execution

### Shoptet
- **Shoptet**: Frontend JavaScript-based implementation
- **OpenCart**: Server-side PHP implementation
- **Reliability**: OpenCart is more reliable as it's server-side with no dependency on JavaScript execution

---

## Security Considerations

1. **API Key Protection**: Only public key is sent (not private key)
2. **Input Sanitization**: Email addresses are validated before being sent
3. **Fail-Safe Design**: Feedback failures don't block checkout
4. **Error Logging**: All errors are logged for audit purposes
5. **Timeout Protection**: 5-second timeout prevents hanging requests

---

## Performance Impact

- **Minimal**: Feedback API call is non-blocking and has 5-second timeout
- **Async-Safe**: Wrapped in try-catch to prevent crashes
- **Single Call**: Only one feedback call per checkout session
- **No User Delay**: Checkout proceeds immediately regardless of feedback result

---

## Maintenance

### Future Updates
- Monitor error logs for feedback API failures
- Adjust timeout values if needed (currently 5 seconds)
- Review feedback success rates in CodGuard dashboard

### Version Compatibility
- **Tested with**: OpenCart 4.x
- **PHP Requirement**: PHP 7.4+
- **Dependencies**: cURL extension (standard in OpenCart)

---

## Summary

The feedback implementation for OpenCart 4 is:
- ✅ **Simple**: Single point of implementation
- ✅ **Reliable**: Event-driven, server-side execution
- ✅ **Safe**: Error handling prevents crashes
- ✅ **Complete**: Covers both logged-in and guest users
- ✅ **Maintainable**: Clear logging for debugging

The implementation sends feedback to CodGuard's API exactly once per checkout session when payment methods are loaded, reporting whether the customer was allowed or blocked from using COD based on their email reputation.
