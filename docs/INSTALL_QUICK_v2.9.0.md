# CodGuard v2.9.0 - Quick Installation

## Package Contents

- **Main Package:** `codguard-oc4-v2.9.0.ocmod.zip` (61 KB)
- **Installation Instructions:** `INSTALL_v2.9.0.md` (detailed guide)
- **Changelog:** `CHANGELOG.md` (all version changes)

## 3-Step Installation

### Step 1: Install Extension (2 minutes)

1. Go to **Admin → Extensions → Installer**
2. Upload `codguard-oc4-v2.9.0.ocmod.zip`
3. Click **Install**
4. Go to **Extensions → Fraud → CodGuard**
5. Click **Install**, then **Edit** to configure

### Step 2: Upload Queue Processor (1 minute)

1. Extract `codguard_process_queue.php` from the zip
2. Upload to OpenCart root directory (same folder as `index.php`)
3. **Location on server:** `/path/to/opencart/codguard_process_queue.php`

### Step 3: Set Up Cron Job (2 minutes)

Add this cron job to run every minute:

```bash
* * * * * cd /path/to/opencart && /usr/bin/php codguard_process_queue.php > /dev/null 2>&1
```

**Replace `/path/to/opencart`** with your actual OpenCart installation path.

**Example paths:**
- cPanel: `/home/username/public_html`
- Plesk: `/var/www/vhosts/yourdomain.com/httpdocs`
- Custom: `/www/doc/yoursite.com/www`

## That's It!

Orders will now upload to CodGuard within 1 minute when status changes to Complete or Denied.

## What Got Fixed in v2.9.0

✅ **Admin panel order uploads** - now work in real-time (v2.8.0 didn't work)
✅ **UTF-8 encoding** - Hungarian, Czech, Slovak, Romanian, Croatian characters preserved
✅ **Dynamic status configuration** - reads from admin settings, not hardcoded
✅ **Database trigger** - works independently of OpenCart events

## Verification

After installation, test by:

1. Creating a test order
2. Changing status to "Complete" or "Denied"
3. Waiting 1 minute
4. Checking CodGuard database

**Check logs:**
```bash
tail -f storage/logs/error.log | grep QUEUE
```

You should see:
- `[QUEUE-PROCESSOR]: Starting queue processor`
- `[QUEUE-PROCESSOR]: SUCCESS - 1 orders uploaded`

## Support

If you encounter issues, check:
- `INSTALL_v2.9.0.md` - Detailed installation guide
- `CHANGELOG.md` - Technical details and troubleshooting

---

**Important Notes:**

1. **Cron is required** - Without it, only customer checkout uploads work
2. **Settings preserved** - Upgrading keeps your API keys and configuration
3. **No downtime** - Old cron-based system (if any) continues working during upgrade
4. **UTF-8 automatic** - No configuration needed for special characters

---

**Version:** 2.9.0
**Release Date:** 2025-11-29
**Package Size:** 61 KB
**OpenCart Version:** 4.0+
