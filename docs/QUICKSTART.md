# Quick Start Guide - CodGuard for OpenCart

Get started with CodGuard in 5 minutes!

## Prerequisites

- OpenCart 3.0+ installed
- PHP 7.0+ with cURL enabled
- CodGuard account with API keys

## Installation (2 minutes)

1. **Upload Extension**
   - Go to **Extensions > Installer**
   - Upload `codguard-opencart-v1.0.0.ocmod.zip`
   - Wait for success message

2. **Install Module**
   - Go to **Extensions > Extensions**
   - Select **Modules** from dropdown
   - Find **CodGuard**
   - Click green **Install** button
   - Click blue **Edit** button

## Configuration (3 minutes)

### Step 1: API Configuration

Enter your CodGuard credentials:

```
Shop ID: [Your Shop ID from CodGuard]
Public Key: [Your Public Key]
Private Key: [Your Private Key]
Status: Enabled
```

**Click Save**

### Step 2: Order Status Mapping

```
Successful Order Status: Complete
Refused Order Status: Canceled
```

**Click Save**

### Step 3: Payment Methods

Check all COD payment methods:

```
☑ Cash On Delivery
☑ Pay on Delivery
☑ [Any other COD methods]
```

**Click Save**

### Step 4: Rating Settings

```
Rating Tolerance: 35 (%)
Rejection Message: "Unfortunately, we cannot offer Cash on Delivery for this order. Please select an alternative payment method."
```

**Click Save**

## That's It!

Your store is now protected by CodGuard.

## What Happens Now?

✅ **Customers with low ratings** will be blocked from using COD
✅ **Orders are automatically synced** to CodGuard every hour
✅ **Statistics tracked** in admin panel

## Test It

1. Try checkout with COD payment
2. Check **Statistics** tab for blocks
3. View logs at `system/storage/logs/error.log`

## Need Help?

- **Full Documentation:** See README.md
- **Installation Issues:** See INSTALL.md
- **Support:** info@codguard.com

---

**Pro Tips:**

💡 Start with 35% tolerance and adjust based on results
💡 Check statistics weekly to monitor effectiveness
💡 Customize rejection message to match your brand
💡 Set up cron jobs for optimal performance

**Recommended Tolerance Levels:**
- 🔴 **Conservative (25%):** Blocks more risky customers
- 🟡 **Balanced (35%):** Recommended for most stores
- 🟢 **Liberal (45%):** Fewer blocks, more risk
