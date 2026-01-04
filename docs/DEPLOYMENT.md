# Production Deployment Guide

## Overview

This guide covers deploying Travium NPC system to production with optimal performance configurations.

---

## OPcache Configuration

### Production Settings (`/etc/php/8.4/fpm/php.ini`)

```ini
[opcache]
; Enable OPcache
opcache.enable=1
opcache.enable_cli=0

; Memory Configuration
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000

; Validation Settings (CRITICAL for production)
opcache.validate_timestamps=0  ; Disable file checking
opcache.revalidate_freq=0      ; Never revalidate (requires restart to update code)

; Optimization
opcache.save_comments=1        ; Required for Doctrine annotations
opcache.fast_shutdown=1
opcache.enable_file_override=1

; JIT Compiler (PHP 8.4)
opcache.jit_buffer_size=128M
opcache.jit=tracing
```

### Development Settings (`local php.ini`)

```ini
[opcache]
opcache.enable=1
opcache.enable_cli=1

; Lower memory for dev
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000

; Enable validation for development
opcache.validate_timestamps=1  ; Enable file checking
opcache.revalidate_freq=2      ; Check every 2 seconds

opcache.save_comments=1
opcache.jit_buffer_size=64M
opcache.jit=tracing
```

---

## Deployment Procedure

### Step 1: Prepare Code

```bash
# On local machine
cd /path/to/travium
git add .
git commit -m "Your commit message"
git push origin main
```

### Step 2: Pull on Production Server

```bash
# SSH to server
ssh user@yourserver.com

# Navigate to web root
cd /home/travium/htdocs

# Pull latest code as travium user
sudo -u travium git pull origin main
```

### Step 3: Run Database Migrations

```bash
# Apply any new migrations
mysql -u maindb -pYOUR_PASSWORD maindb < migrations/XXX_migration.sql

# Run query optimization audit
mysql -u maindb -pYOUR_PASSWORD maindb < scripts/audit_npc_queries.sql
```

### Step 4: Clear OPcache

**Method 1: Restart PHP-FPM (Recommended)**
```bash
sudo systemctl restart php8.4-fpm
```

**Method 2: Reload PHP-FPM (Faster, less downtime)**
```bash
sudo systemctl reload php8.4-fpm
```

**Method 3: Apache Restart (If using mod_php)**
```bash
sudo systemctl restart apache2
```

**Method 4: Programmatic Reset (Create admin endpoint)**

Create `admin/opcache_reset.php`:
```php
<?php
// Requires admin authentication
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die('Unauthorized');
}

opcache_reset();
echo json_encode([
    'success' => true,
    'message' => 'OPcache cleared',
    'timestamp' => date('Y-m-d H:i:s')
]);
```

Then access: `https://yoursite.com/admin/opcache_reset.php`

### Step 5: Verify Deployment

```bash
# Check PHP-FPM status
sudo systemctl status php8.4-fpm

# Check OPcache status
curl https://yoursite.com/admin/opcache_status.php

# Monitor error logs
sudo tail -f /var/log/php8.4-fpm.log
sudo tail -f /var/log/apache2/error.log  # If using Apache
```

---

## Redis Configuration

### Installation (if not already installed)

```bash
sudo apt update
sudo apt install redis-server

# Configure Redis
sudo nano /etc/redis/redis.conf
```

### Redis Configuration (`/etc/redis/redis.conf`)

```conf
# Network
bind 127.0.0.1
port 6379

# Memory
maxmemory 512mb
maxmemory-policy allkeys-lru  # Evict least recently used keys

# Persistence (optional for cache)
save ""  # Disable RDB snapshots for pure cache
appendonly no  # Disable AOF for pure cache

# Performance
tcp-keepalive 300
timeout 0
```

### Start Redis

```bash
sudo systemctl start redis-server
sudo systemctl enable redis-server
sudo systemctl status redis-server
```

### Test Redis Connection

```bash
redis-cli ping
# Expected output: PONG
```

---

## Environment Variables

Create `/home/travium/htdocs/.env` (if using Dotenv):

```env
# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_DB=0
# REDIS_PASSWORD=your_password  # If auth enabled

# Performance
ENABLE_OPCACHE=true
ENABLE_REDIS_CACHE=true
ENABLE_NPC_DEBUG_LOGS=false  # true only for debugging
```

---

## Performance Monitoring

### Monitor NPC Performance

```bash
# Watch performance logs
tail -f logs/npc_performance.log

# Monitor Redis
redis-cli --stat

# Monitor PHP-FPM
sudo tail -f /var/log/php8.4-fpm-slow.log
```

### Check Cache Hit Rate

Access admin dashboard or use CLI:
```bash
redis-cli info stats | grep keyspace
```

Good hit rate: >80%

---

## Rollback Procedure

If deployment causes issues:

```bash
# 1. Revert code
cd /home/travium/htdocs
sudo -u travium git reset --hard HEAD~1

# 2. Restart services
sudo systemctl restart php8.4-fpm

# 3. Clear Redis cache (if needed)
redis-cli flushdb
```

---

## Maintenance Tasks

### Weekly
- Check error logs for issues
- Monitor cache hit rates
- Review slow query log

### Monthly
- Analyze performance metrics
- Optimize slow queries
- Update indexes if needed

### When Needed
- Clear Redis cache after major code changes
- Restart PHP-FPM after config changes
- Run query audit after schema changes

---

## Troubleshooting

### OPcache Issues

**Problem:** Code changes not appearing

**Solution:**
```bash
sudo systemctl restart php8.4-fpm
```

**Problem:** High memory usage

**Solution:** Reduce `opcache.memory_consumption` in php.ini

### Redis Issues

**Problem:** Connection refused

**Solution:**
```bash
sudo systemctl status redis-server
sudo systemctl start redis-server
```

**Problem:** High memory usage

**Solution:** Reduce `maxmemory` in redis.conf or enable eviction

### Performance Issues

**Problem:** Slow NPC ticks

**Solution:**
1. Check Redis hit rate (should be >80%)
2. Run query audit script
3. Enable debug logging temporarily
4. Monitor `npc_performance.log`

---

## Security Considerations

1. **Admin Tools Access:**
   - Restrict admin panel by IP
   - Require strong authentication
   - Never expose test tools in production

2. **Redis Security:**
   - Bind to localhost only
   - Enable password authentication for public servers
   - Configure firewall rules

3. **File Permissions:**
```bash
# Set proper permissions
sudo chown -R travium:www-data /home/travium/htdocs
sudo chmod -R 755 /home/travium/htdocs
sudo chmod -R 775 /home/travium/htdocs/logs
```

---

## Production Checklist

Before going live:
- [ ] OPcache configured with `validate_timestamps=0`
- [ ] Redis installed and running
- [ ] All database indexes created
- [ ] Query audit script passes
- [ ] Admin tools access restricted
- [ ] Debug logging disabled
- [ ] Error logs monitored
- [ ] Backup strategy in place
- [ ] Rollback procedure tested

---

## Performance Targets

**Acceptable Ranges:**
- NPC tick duration: <2s (p95)
- Redis hit rate: >80%
- Query count per tick: <20
- PHP memory usage: <128MB per request
- Redis memory usage: <512MB total

**Monitor and adjust configurations to maintain these targets.**
