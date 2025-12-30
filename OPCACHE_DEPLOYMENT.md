# OPcache with JIT Deployment Guide

## Purpose
Enable PHP 8.4 OPcache with JIT compiler for 30-50% performance improvement.

## Prerequisites
- PHP 8.4 installed
- Root/sudo access to server
- PHP-FPM running

---

## Step 1: Install OPcache Extension

```bash
# Check if already installed
php -m | grep "Zend OPcache"

# If not installed:
sudo apt update
sudo apt install php8.4-opcache

# Verify installation
php -m | grep "Zend OPcache"
```

**Expected output**: `Zend OPcache`

---

## Step 2: Deploy Configuration File

```bash
# Copy configuration to PHP FPM conf.d directory
sudo cp config/opcache-php8.4.ini /etc/php/8.4/fpm/conf.d/99-opcache.ini

# Verify file copied
cat /etc/php/8.4/fpm/conf.d/99-opcache.ini
```

---

## Step 3: Restart PHP-FPM

```bash
# Restart PHP-FPM to load new configuration
sudo systemctl restart php8.4-fpm

# Verify service running
sudo systemctl status php8.4-fpm
```

**Expected**: `active (running)` status

---

## Step 4: Verify Configuration

```bash
# Run verification script
php scripts/verify_opcache.php
```

**Expected output**:
```
✅ OPcache extension is loaded
✅ JIT Enabled: YES
✅ OPcache and JIT are properly configured!
   Expected performance improvement: 30-50%
```

---

## Step 5: Optional - Create PHPInfo Page

```bash
# Create temporary phpinfo page (REMOVE after testing!)
echo "<?php phpinfo(); ?>" | sudo tee /home/travium/htdocs/servers/s1/public/phpinfo.php
```

Navigate to: `https://yourdomain.com/phpinfo.php`

**Check for**:
- Search for "opcache" section
- Verify `opcache.enable` = On
- Verify `opcache.jit` = 1255
- Verify `opcache.jit_buffer_size` = 128M

**IMPORTANT**: Delete after verification for security:
```bash
sudo rm /home/travium/htdocs/servers/s1/public/phpinfo.php
```

---

## Configuration Explanation

### Key Settings

| Setting | Value | Purpose |
|---------|-------|---------|
| `opcache.enable` | 1 | Enable OPcache |
| `opcache.memory_consumption` | 256 | Cache size (MB) |
| `opcache.max_accelerated_files` | 10000 | Max cached files |
| `opcache.validate_timestamps` | 0 | Production mode (no revalidation) |
| `opcache.jit` | 1255 | JIT mode (tracing + optimization) |
| `opcache.jit_buffer_size` | 128M | JIT compilation buffer |

### JIT Mode Explanation (1255)

- **1**: Enable JIT
- **2**: Tracing mode (best for web apps)
- **5**: Optimization level 5 (maximum)
- **5**: Hot code detection

---

## Troubleshooting

### Issue: OPcache not loading

**Solution**:
```bash
# Check if extension file exists
ls -la /usr/lib/php/20230831/opcache.so

# Check PHP configuration
php --ini

# Manually load if needed
sudo nano /etc/php/8.4/fpm/php.ini
# Add: zend_extension=opcache.so
```

### Issue: JIT not available

**Cause**: PHP version < 8.0 or OPcache not compiled with JIT

**Solution**:
```bash
# Verify PHP version
php -v  # Should be 8.4.x

# Reinstall OPcache
sudo apt reinstall php8.4-opcache
```

### Issue: Settings not taking effect

**Solution**:
```bash
# Ensure file in correct location
ls -la /etc/php/8.4/fpm/conf.d/99-opcache.ini

# Restart PHP-FPM
sudo systemctl restart php8.4-fpm

# Clear OPcache via PHP
# Create clear_opcache.php:
# <?php opcache_reset(); echo "Cache cleared"; ?>
```

---

## Development vs Production

### Development Mode
```ini
opcache.validate_timestamps=1
opcache.revalidate_freq=2
```
- Files automatically reloaded after changes
- Slower but easier development

### Production Mode (Current)
```ini
opcache.validate_timestamps=0
```
- Maximum performance
- **Must restart PHP-FPM after code changes**

---

## Clearing OPcache After Code Updates

After deploying code changes in production:

```bash
# Method 1: Restart PHP-FPM (recommended)
sudo systemctl restart php8.4-fpm

# Method 2: Reload PHP-FPM (graceful)
sudo systemctl reload php8.4-fpm

# Method 3: Use cachetool (if installed)
php cachetool.phar opcache:reset --fcgi=127.0.0.1:9000
```

---

## Performance Monitoring

### Check OPcache Statistics

```bash
# Run verification script periodically
php scripts/verify_opcache.php

# Monitor hit rate (should be >95%)
# Monitor memory usage (should not be full)
```

### Benchmark Performance

**Before OPcache**:
```bash
# Time a complex page load
curl -w "@curl-format.txt" -o /dev/null -s "https://yourdomain.com/karte.php"
```

**After OPcache**:
```bash
# Same command - should be 30-50% faster
curl -w "@curl-format.txt" -o /dev/null -s "https://yourdomain.com/karte.php"
```

---

## Integration with Deployment Workflow

Add to `GIT_WORKFLOW.md`:

**After `git pull` on Ubuntu:**

```bash
# 1. Pull code
git pull origin main

# 2. Clear OPcache
sudo systemctl reload php8.4-fpm

# 3. Verify
php scripts/verify_opcache.php
```

---

## Expected Results

- ✅ Page load times reduced by 30-50%
- ✅ CPU usage decreased
- ✅ Memory usage stable (256MB OPcache + 128MB JIT)
- ✅ No impact on game functionality
- ✅ NPC processing faster

---

## rollback Procedure

If issues occur:

```bash
# 1. Disable OPcache
sudo mv /etc/php/8.4/fpm/conf.d/99-opcache.ini /etc/php/8.4/fpm/conf.d/99-opcache.ini.disabled

# 2. Restart PHP-FPM
sudo systemctl restart php8.4-fpm

# 3. Verify disabled
php -m | grep "Zend OPcache"  # Should return nothing
```

---

**Status**: Ready for deployment  
**Risk Level**: Low (can be easily rolled back)  
**Impact**: High (significant performance improvement)
