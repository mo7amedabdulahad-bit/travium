# Fresh Install Verification ✅

**Status**: PRODUCTION READY  
**Verified**: 2025-12-28  
**PHP Version**: 8.4  

---

## Installation Status: ✅ READY

All critical issues resolved. Fresh installation now works completely.

### Critical Fixes Applied

1. ✅ **Fix #54**: Bootstrap null check
2. ✅ **Fix #55**: PHP 8.4 in web installer
3. ✅ **Fix #56**: Auto-create src/config.php
4. ✅ **Fix #58**: Skip updater patches on fresh install
5. ✅ **Fix #59**: Auto-create API config.php
6. ✅ **Fix #61**: Cast mt_srand in server template install.php

---

## One-Command Installation

```bash
curl -sSL https://raw.githubusercontent.com/mo7amedabdulahad-bit/travium/main/install.sh | sudo bash
```

### What Happens

1. System packages installed (PHP 8.4, MariaDB, Nginx)
2. Game cloned from GitHub
3. **NEW**: `src/config.php` auto-created from sample
4. **NEW**: `integrations/api/include/config.php` auto-created
5. Composer dependencies installed
6. Database initialized
7. **NEW**: Updater runs with `--new-installation` flag (skips patches)
8. **NEW**: Installation completes (mt_srand cast fix)
9. Fake users created
10. Automation services started

---

## Post-Install Verification

### 1. Check Installation Completed

```bash
mariadb -u maindb -p'<password>' maindb -e "SELECT installed, maintenance FROM config;"
```

**Expected Output**:
```
+-----------+-------------+
| installed | maintenance |
+-----------+-------------+
|         1 |           0 |
+-----------+-------------+
```

### 2. Verify Services Running

```bash
systemctl status travium@s1.service
systemctl status php8.4-fpm  
systemctl status nginx
systemctl status mariadb
```

**All should show**: `active (running)`

### 3. Check Error Logs (Should Be Clean)

```bash
# PHP errors
sudo tail -50 /var/log/php8.4-fpm.log

# Nginx errors
sudo tail -50 /var/log/nginx/error.log

# Game errors (should be minimal/empty)
tail -100 /home/travium/htdocs/servers/s1/include/error_log.log
```

### 4. Verify Fake Users Created

```bash
mariadb -u maindb -p'<password>' maindb -e "SELECT COUNT(*) as fake_users FROM users WHERE access=3;"
```

**Expected**: `20` fake users

### 5. Check Automation Active

```bash
# Recent automation logs
journalctl -u travium@s1.service -n 50 --no-pager

# Check fake user last activity
mariadb -u maindb -p'<password>' maindb -e "
SELECT name, FROM_UNIXTIME(lastVillageCheck) as last_action
FROM users u 
JOIN vdata v ON u.id=v.owner 
WHERE u.access=3 
ORDER BY lastVillageCheck DESC 
LIMIT 5;"
```

**Expected**: Timestamps within last 15 minutes

### 6. Test Game Access

**Browser Tests**:
1. Navigate to `http://[server-ip]` or `https://[domain]`
2. **Expected**: Game main page loads (server selection)
3. Click on server → **Expected**: Login/register page
4. Register new account → **Expected**: Game loads successfully
5. **No maintenance screen** ✅

---

## Common Post-Install Tasks

### Change Database Password

```bash
# Generate new password
NEW_PASS=$(openssl rand -base64 24)
echo "New password: $NEW_PASS"

# Update MariaDB
mariadb -u root -p -e "ALTER USER 'maindb'@'localhost' IDENTIFIED BY '$NEW_PASS';"

# Update config
sudo nano /home/travium/htdocs/src/config.php
# Find and update database password
```

### Enable SSL (Optional)

```bash
# Via certbot
sudo certbot --nginx -d your-domain.com

# Or configure via CloudPanel
```

### Configure Firewall

```bash
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp
sudo ufw enable
```

---

## Troubleshooting

### Issue: Maintenance Screen After Install

**Cause**: Installation didn't complete (Fix #61 issue)  
**Check**:
```bash
mariadb -u maindb -p maindb -e "SELECT installed FROM config;"
```

**If installed = 0**:
```bash
# Check for errors
tail -50 /home/travium/htdocs/servers/s1/include/error_log.log

# Should NOT see mt_srand errors anymore (Fix #61 applied)
```

### Issue: API CORS Errors

**Cause**: API config missing (Fix #59 issue)  
**Check**:
```bash
ls -la /home/travium/htdocs/integrations/api/include/config.php
```

**If missing** (shouldn't happen anymore):
```bash
cd /home/travium/htdocs/integrations/api/include
cp config.sample.php config.php
```

### Issue: "Installation is not completed" Error

**Cause**: src/config.php missing (Fix #56 issue)  
**Check**:
```bash
ls -la /home/travium/htdocs/src/config.php
```

**If missing** (shouldn't happen anymore):
```bash
cd /home/travium/htdocs/src
cp config.sample.php config.php
```

### Issue: Updater Duplicate Column Errors

**Cause**: Updater running patches on fresh install (Fix #58 issue)  
**Verify**: Web installer now uses `--new-installation` flag  
**Check installer output**: Should show no SQL duplicate errors

---

## Production Checklist

### Pre-Launch
- [ ] Fresh install completed successfully
- [ ] All services running
- [ ] Database password changed
- [ ] Firewall configured
- [ ] SSL certificate installed (optional)
- [ ] No errors in logs
- [ ] Fake users active
- [ ] Automation running

### Security
- [ ] Removed installer key from config
- [ ] Changed default credentials
- [ ] Enabled fail2ban
- [ ] Configured backups
- [ ] Reviewed file permissions

### Monitoring
- [ ] Error log monitoring set up
- [ ] Database backup scheduled
- [ ] Disk space monitoring
- [ ] Service uptime monitoring

---

## Success Indicators

✅ **Database**: `installed=1, maintenance=0`  
✅ **Error Logs**: Clean/minimal  
✅ **Services**: All active  
✅ **Fake Users**: 20 created, actively processing  
✅ **Automation**: Running every 15 minutes  
✅ **Game**: Loads without maintenance screen  
✅ **API**: Responding correctly  
✅ **PHP**: Version 8.4 running  

---

## Files Auto-Created by Installer

The following files are now automatically created (Fixes #56, #59):

1. `/home/travium/htdocs/src/config.php` (from config.sample.php)
2. `/home/travium/htdocs/integrations/api/include/config.php` (from config.sample.php)
3. `/home/travium/htdocs/servers/s1/include/connection.php` (from template)
4. `/home/travium/htdocs/servers/s1/include/config.custom.php` (generated)

**No manual configuration required!** ✅

---

## Known Working Configurations

### Tested On
- Ubuntu 22.04 LTS
- PHP 8.4.15
- MariaDB 10.11
- Nginx 1.18+

### CloudPanel Compatible
- ✅ Works with CloudPanel
- ✅ SSL certificates supported
- ✅ Multi-site configuration supported

---

**Installation Status**: ✅ **VERIFIED WORKING**  
**Production Ready**: ✅ **YES**  
**Support**: See docs/php-8.4-migration/ and docs/COMPLETE_INSTALLATION_GUIDE.md

*Last Verified: 2025-12-28*
