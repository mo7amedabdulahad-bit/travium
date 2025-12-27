# Automation Troubleshooting Guide

## Issue
Automation (cron jobs for NPC AI, building queue, etc.) not working after PHP 8.4 migration.

## Possible Causes

### 1. Cron Still Using Old PHP Version
Cron jobs may be calling `/usr/bin/php` which points to PHP 7.3 instead of 8.4.

**Check**:
```bash
# On server
php -v  # Shows default PHP version
which php  # Shows PHP binary location
crontab -l -u travium  # Shows current cron jobs
```

**Fix**:
```bash
# Edit crontab
crontab -e -u travium

# Change from:
* * * * * php /path/to/automation.php

# To explicitly use PHP 8.4:
* * * * * /usr/bin/php8.4 /path/to/automation.php
```

---

### 2. PHP-FPM vs CLI
Web requests use PHP-FPM 8.4, but cron uses PHP CLI which might be different version.

**Check**:
```bash
# CLI version
php -v

# FPM version  
php-fpm8.4 -v

# List all PHP versions
ls -la /usr/bin/php*
```

**Fix**: Update cron to use specific PHP 8.4 CLI binary.

---

### 3. File Permissions
After code changes, automation files may have wrong permissions.

**Check**:
```bash
ls -la /home/travium/htdocs/src/Controller/MainController.php
ls -la /home/travium/htdocs/src/admin/include/automation.php
```

**Fix**:
```bash
sudo chown -R travium:travium /home/travium/htdocs
sudo chmod -R 755 /home/travium/htdocs
```

---

### 4. Lock Files Not Clearing
Old lock files from PHP 7.3 may prevent automation from running.

**Check**:
```bash
find /home/travium/htdocs -name "*.lock" -o -name "*_lock"
```

**Fix**:
```bash
sudo -u travium find /home/travium/htdocs -name "*.lock" -delete
sudo systemctl restart php8.4-fpm
```

---

### 5. Error Logs
Check logs for automation errors.

**Check**:
```bash
# PHP error log
tail -100 /var/log/php8.4-fpm.log

# Application logs
tail -100 /home/travium/htdocs/src/error.log

# Cron logs
tail -100 /var/log/syslog | grep CRON
```

---

## Testing Automation Manually

Test if automation PHP files work:

```bash
# Run as travium user
sudo -u travium php8.4 /home/travium/htdocs/servers/s2/crons/Automation.php

# Check for errors
echo $?  # Should be 0 if successful
```

---

## Common Automation Files

| File | Purpose | Frequency |
|------|---------|-----------|
| `Automation.php` | Main automation loop | Every minute |
| `Buildings.php` | Building queue processor | Every minute |
| `Movement.php` | Troop movement processor | Every minute |
| `Ranking.php` | Update rankings | Every hour |
| `DailyQuest.php` | Daily quest reset | Once daily |

---

## Recommended Crontab

```cron
# Use explicit PHP 8.4 binary
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

# Main automation - every minute
* * * * * /usr/bin/php8.4 /home/travium/htdocs/servers/s2/crons/Automation.php >> /home/travium/htdocs/cron.log 2>&1

# Building queue - every minute  
* * * * * /usr/bin/php8.4 /home/travium/htdocs/servers/s2/crons/Buildings.php >> /home/travium/htdocs/cron.log 2>&1

# Movement - every minute
* * * * * /usr/bin/php8.4 /home/travium/htdocs/servers/s2/crons/Movement.php >> /home/travium/htdocs/cron.log 2>&1

# Rankings - every hour
0 * * * * /usr/bin/php8.4 /home/travium/htdocs/servers/s2/crons/Ranking.php >> /home/travium/htdocs/cron.log 2>&1
```

---

## Debugging Steps

1. **Verify PHP version in cron**:
   ```bash
   * * * * * /usr/bin/php8.4 -v >> /tmp/php_version.txt 2>&1
   ```

2. **Test automation manually**:
   ```bash
   sudo -u travium /usr/bin/php8.4 /home/travium/htdocs/servers/s2/crons/Automation.php
   ```

3. **Check error logs**:
   ```bash
   tail -f /home/travium/htdocs/cron.log
   tail -f /var/log/php8.4-fpm.log
   ```

4. **Enable verbose logging**:
   In automation files, add:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

---

## Next Steps

Run these commands on the server and share the output:

```bash
# 1. Check PHP versions
php -v
php8.4 -v  
ls -la /usr/bin/php*

# 2. Check current crontab
crontab -l -u travium

# 3. Test automation manually
sudo -u travium /usr/bin/php8.4 /home/travium/htdocs/servers/s2/crons/Automation.php

# 4. Check for errors
tail -50 /home/travium/htdocs/cron.log
tail -50 /var/log/php8.4-fpm.log | grep -i error
```
