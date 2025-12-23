# CodGuard for OpenCart - Cron Setup Guide

## The Problem

OpenCart's built-in cron system only runs when someone visits your website. If your site has low traffic, orders may not be uploaded to CodGuard promptly.

## The Solution

Set up a **system cron job** that triggers OpenCart's cron every hour, ensuring orders are uploaded regardless of site traffic.

## Option 1: System Cron Job (Recommended)

Add this to your server's crontab:

```bash
# Run every hour at minute 0
0 * * * * curl -s 'http://yoursite.com/index.php?route=cron/cron' > /dev/null 2>&1
```

### How to Add:

**On Linux/Unix servers:**

1. SSH to your server
2. Edit crontab:
   ```bash
   crontab -e
   ```
3. Add the line above (replace `yoursite.com` with your actual domain)
4. Save and exit

**Alternative with wget:**
```bash
0 * * * * wget -q -O /dev/null 'http://yoursite.com/index.php?route=cron/cron' > /dev/null 2>&1
```

## Option 2: External Cron Service

Use a free service like:

- **EasyCron** (https://www.easycron.com)
- **cron-job.org** (https://cron-job.org)
- **SetCronJob** (https://www.setcronjob.com)

Configure them to call:
```
http://yoursite.com/index.php?route=cron/cron
```

Every hour (or more frequently if needed).

## Option 3: Hosting Control Panel

Many hosting providers (cPanel, Plesk, etc.) have cron job interfaces:

1. Login to your hosting control panel
2. Find "Cron Jobs" section
3. Add new cron job:
   - **Command/URL**: `http://yoursite.com/index.php?route=cron/cron`
   - **Frequency**: Every hour
   - **Method**: GET

## Verification

After setup, check that orders are being uploaded:

```bash
# Check cron execution logs
grep "CRON.*codguard" storage/logs/error.log | tail -10

# Check last cron run time
mysql -u username -p database_name -e "SELECT date_modified FROM oc_cron WHERE code = 'codguard_order_sync'"

# Check order queue
mysql -u username -p database_name -e "SELECT COUNT(*) as pending FROM oc_codguard_order_queue WHERE status = 'pending'"
```

## How It Works

1. System cron calls `index.php?route=cron/cron` every hour
2. OpenCart checks all registered cron jobs in `oc_cron` table  
3. For hourly crons (like CodGuard), it runs if more than 1 hour has passed
4. CodGuard's cron:
   - Finds orders older than 1 hour in the queue
   - Sends them to CodGuard API
   - Marks them as sent
   - Cleans up old records

## Troubleshooting

**Cron not running?**
- Check your server allows outgoing HTTP requests
- Verify the URL is correct
- Check server logs for errors

**Orders still not uploading?**
- Check if orders are being queued: `SELECT * FROM oc_codguard_order_queue`
- Check error logs: `tail -100 storage/logs/error.log | grep CRON`
- Manually trigger: Visit `http://yoursite.com/index.php?route=cron/cron` in browser

**Multiple crons running?**
- OpenCart prevents duplicate runs, so it's safe to have both traffic-based and system cron
- The first one to run will process the orders

## Best Practices

1. **Monitor logs** regularly to ensure cron is working
2. **Keep bundling delay** at 1 hour to optimize API usage
3. **Don't set cron too frequently** - once per hour is optimal
4. **Use HTTPS** if your site has SSL certificate

## Notes

- OpenCart cron runs ALL registered crons, not just CodGuard's
- Other extensions may also benefit from reliable cron execution
- No code changes needed in your OpenCart installation
- Works for all OpenCart 4.x installations

