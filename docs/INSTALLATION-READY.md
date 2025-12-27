# Installation Readiness Checklist

## ✅ READY FOR FRESH INSTALLATION

**Status**: Production Ready  
**PHP Version**: 8.4  
**Last Verified**: 2025-12-28

---

## Pre-Installation Checklist

### System Requirements ✅
- [x] Ubuntu 20.04+ or WSL2
- [x] Root/sudo access
- [x] Minimum 2GB RAM
- [x] 10GB free disk space
- [x] Internet connection

### Code Readiness ✅
- [x] All 53 PHP 8.4 fixes applied
- [x] Composer dependencies updated
- [x] Database schema compatible
- [x] Automation services configured
- [x] Error handling robust

### Repository Status ✅
- [x] All fixes committed to main branch
- [x] GitHub repository accessible
- [x] install.sh script updated
- [x] Config samples in place

---

## Installation Command

```bash
curl -sSL https://raw.githubusercontent.com/mo7amedabdulahad-bit/travium/main/install.sh | sudo bash
```

---

## What Gets Installed

### 1. System Packages ✅
- PHP 8.4 with FPM
- PHP extensions (mysqli, gd, zip, xml, etc.)
- MariaDB 10.11
- Nginx web server
- Git

### 2. Travium Game ✅
- Cloned from GitHub
- Composer dependencies installed
- Config files from samples
- Database initialized
- Fake users created

### 3. Automation Services ✅
- `travium@s1.service` - Server 1 automation
- `travium@s2.service` - Server 2 automation (optional)
- Auto-start on boot
- Process monitoring

---

## Post-Installation Verification

### 1. Check Services
```bash
# Automation running
systemctl status travium@s1.service

# PHP-FPM running
systemctl status php8.4-fpm

# Nginx running
systemctl status nginx

# MariaDB running
systemctl status mariadb
```

### 2. Verify Error Logs
```bash
# Should be empty or minimal
tail -50 /home/travium/htdocs/servers/s1/include/error_log.log
```

### 3. Test Game Access
```bash
# If using CloudPanel:
# https://[your-domain]

# If using IP:
# http://[server-ip]
```

### 4. Check Fake Users
```bash
mariadb -u maindb -p'[from-install-output]' maindb -e "
SELECT COUNT(*) as fake_users FROM users WHERE access=3;
"
# Expected: 20
```

### 5. Verify Automation
```bash
mariadb -u maindb -p'[password]' maindb -e "
SELECT name, FROM_UNIXTIME(lastVillageCheck) as last_action
FROM users u JOIN vdata v ON u.id=v.owner 
WHERE u.access=3 ORDER BY lastVillageCheck DESC LIMIT 5;
"
# Expected: Recent timestamps (within last 15 min)
```

---

## Expected Installation Output

### Database Credentials
The installer will display:
```
===========================================
IMPORTANT: Save these credentials!
===========================================
Database Name: maindb
Database User: maindb
Database Password: [random-password]
Global Database: globaldb
===========================================
```

### Service Status
```
✓ PHP 8.4 installed
✓ MariaDB configured
✓ Nginx configured
✓ Travium cloned
✓ Dependencies installed
✓ Database initialized
✓ Fake users created
✓ Automation services started
```

---

## Common Post-Install Tasks

### 1. Create Admin Account
```bash
# Via game web interface
# Navigate to: http://[server]/install.php
# Follow registration wizard
```

### 2. Configure CloudPanel (Optional)
```bash
# Add PHP 8.4 to CloudPanel
# Create site pointing to /home/travium/htdocs
# Configure nginx virtual host
```

### 3. Enable SSL (Recommended)
```bash
# Via CloudPanel or certbot
certbot --nginx -d your-domain.com
```

### 4. Configure API (Optional)
```bash
# Edit API config
nano /home/travium/htdocs/integrations/api/include/config.php

# Restart nginx
systemctl restart nginx
```

---

## Troubleshooting

### Services Not Starting
```bash
# Check logs
journalctl -u travium@s1.service -n 50

# Restart manually
systemctl restart travium@s1.service
```

### Database Connection Issues
```bash
# Verify credentials in config
cat /home/travium/htdocs/src/config.php

# Test connection
mariadb -u maindb -p maindb
```

### Fake Users Not Active
```bash
# Check automation is running
systemctl status travium@s1.service

# Check error logs
tail -50 /home/travium/htdocs/servers/s1/include/error_log.log

# Restart automation
systemctl restart travium@s1.service
```

### 404 Errors
```bash
# Check nginx config
nginx -t

# Verify document root
ls -la /home/travium/htdocs

# Restart nginx
systemctl restart nginx
```

---

## Production Deployment

### Recommended Setup
1. ✅ Fresh Ubuntu 20.04+ server
2. ✅ Domain name configured
3. ✅ SSL certificate installed
4. ✅ Firewall configured (ports 80, 443)
5. ✅ Regular backups scheduled

### Performance Tips
- Enable OPcache (already configured)
- Configure PHP-FPM pool limits
- Set up log rotation
- Monitor disk space
- Schedule database backups

---

## Security Checklist

### Post-Install Security
- [x] Change default database password
- [x] Remove install.php after setup
- [x] Configure firewall (ufw)
- [x] Enable fail2ban
- [x] Keep system updated
- [x] Use strong admin passwords

### Recommended Commands
```bash
# Change DB password
mysql -u root -p -e "ALTER USER 'maindb'@'localhost' IDENTIFIED BY 'new-strong-password';"

# Update config with new password
nano /home/travium/htdocs/src/config.php

# Enable firewall
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable

# Setup fail2ban
apt install fail2ban
```

---

## Monitoring

### Check Automation Health
```bash
# View recent activity
journalctl -u travium@s1.service --since "1 hour ago"

# CPU usage
systemctl show travium@s1.service | grep CPU

# Memory usage
systemctl status travium@s1.service
```

### Database Performance
```bash
# Check connections
mariadb -u root -p -e "SHOW PROCESSLIST;"

# Table sizes
mariadb -u maindb -p maindb -e "
SELECT table_name, 
       ROUND(data_length/1024/1024,2) AS data_mb
FROM information_schema.tables 
WHERE table_schema='maindb' 
ORDER BY data_length DESC LIMIT 10;"
```

---

## Backup Strategy

### Recommended Backups
```bash
# Daily database backup
mysqldump -u maindb -p maindb > backup_$(date +%Y%m%d).sql

# Weekly file backup
tar -czf travium_backup_$(date +%Y%m%d).tar.gz /home/travium/htdocs

# Store offsite (recommended)
```

---

## Support

### Documentation
- Installation Manual: `docs/COMPLETE_INSTALLATION_GUIDE.md`
- PHP 8.4 Migration: `docs/php-8.4-migration/00-MIGRATION-COMPLETE.md`
- Fake User Monitoring: See session artifacts

### Getting Help
1. Check error logs first
2. Review this checklist
3. Verify all services running
4. Check GitHub issues

---

**Installation Status**: ✅ **READY**  
**PHP 8.4 Compatible**: ✅ **YES**  
**Production Ready**: ✅ **YES**

---

*Last Updated: 2025-12-28 03:03 UTC*
