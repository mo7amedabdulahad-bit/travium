# Redis Installation and Configuration Guide

## Purpose
Install and configure Redis for caching frequently-accessed data (NPC personalities, building data, formulas).

**Expected Benefits:**
- 90% reduction in database queries for cached data
- Faster NPC processing
- Reduced database load

---

## Step 1: Install Redis

```bash
# Update package list
sudo apt update

# Install Redis server and PHP extension
sudo apt install redis-server php8.4-redis -y

# Enable Redis to start on boot
sudo systemctl enable redis-server

# Start Redis service
sudo systemctl start redis-server

# Verify installation
redis-cli ping
```

**Expected output**: `PONG`

**Verify PHP extension:**
```bash
php -m | grep redis
```

**Expected output**: `redis`

---

## Step 2: Configure Redis

Edit Redis configuration:

```bash
sudo nano /etc/redis/redis.conf
```

### Recommended Settings

Find and modify these lines:

```conf
# Memory limit (adjust based on server RAM)
maxmemory 512mb

# Eviction policy (remove least recently used keys when full)
maxmemory-policy allkeys-lru

# Disable persistence (we're using Redis as cache, not database)
save ""

# Comment out any existing 'save' directives
# save 900 1
# save 300 10
# save 60 10000

# Bind to localhost only (security)
bind 127.0.0.1 ::1

# Disable protected mode if binding to localhost
protected-mode yes
```

**Restart Redis after configuration:**
```bash
sudo systemctl restart redis-server
sudo systemctl status redis-server
```

**Expected status**: `active (running)`

---

## Step 3: Test Redis Connection

Create test script:

```bash
php scripts/test_redis.php
```

Expected output:
```
✅ Redis connected successfully
✅ Set operation successful
✅ Get operation successful: hello
✅ Delete operation successful
✅ All Redis tests passed!
```

---

## Step 4: Monitor Redis

### Check Redis Info

```bash
# View Redis statistics
redis-cli info stats

# View memory usage
redis-cli info memory

# View connected clients
redis-cli client list

# View all keys (careful in production!)
redis-cli KEYS "travian_*"

# Count keys
redis-cli DBSIZE
```

### Monitor in Real-Time

```bash
# Monitor all commands (debug mode)
redis-cli monitor

# View slow queries
redis-cli slowlog get 10
```

---

## Step 5: Redis CLI Useful Commands

```bash
# Connect to Redis CLI
redis-cli

# Inside Redis CLI:
PING                          # Test connection
DBSIZE                        # Count keys
KEYS travian_*               # List all Travian cache keys
GET travian_npc_personality_5 # Get specific key
TTL travian_npc_personality_5 # Check time-to-live
DEL travian_npc_personality_5 # Delete specific key
FLUSHDB                       # Clear all keys (CAREFUL!)
INFO memory                   # Memory stats
INFO stats                    # General stats
```

---

## Troubleshooting

### Issue: Cannot connect to Redis

**Solution:**
```bash
# Check Redis is running
sudo systemctl status redis-server

# Check Redis logs
sudo journalctl -u redis-server -n 50

# Restart Redis
sudo systemctl restart redis-server
```

### Issue: PHP extension not loaded

**Solution:**
```bash
# Reinstall PHP Redis extension
sudo apt install php8.4-redis --reinstall

# Verify extension loaded
php -m | grep redis

# Check PHP configuration
php --ini | grep redis

# Restart PHP-FPM
sudo systemctl restart php8.4-fpm
```

### Issue: Redis using too much memory

**Solution:**
```bash
# Check current memory usage
redis-cli info memory | grep used_memory_human

# Reduce maxmemory in config
sudo nano /etc/redis/redis.conf
# Change: maxmemory 256mb

# Restart Redis
sudo systemctl restart redis-server

# Or manually flush cache
redis-cli FLUSHDB
```

### Issue: Keys not expiring

**Check TTL:**
```bash
redis-cli TTL travian_npc_personality_5
# -1 means no expiration
# -2 means key doesn't exist
# positive number = seconds until expiration
```

---

## Security Considerations

### Production Checklist

- [ ] Redis bound to localhost only (`bind 127.0.0.1`)
- [ ] Protected mode enabled
- [ ] No password needed (localhost only)
- [ ] Persistence disabled (cache only)
- [ ] Memory limit set appropriately
- [ ] Regular monitoring of memory usage

### Optional: Add Password (if exposing Redis)

```bash
sudo nano /etc/redis/redis.conf
# Add line:
# requirepass your_strong_password_here

sudo systemctl restart redis-server
```

**Update PHP connection:**
```php
$redis->auth('your_strong_password_here');
```

---

## Performance Monitoring

### Check Hit Rate

```bash
# View cache statistics
redis-cli INFO stats | grep -E "keyspace_hits|keyspace_misses"
```

**Calculate hit rate:**
```
Hit Rate = hits / (hits + misses) * 100
```

**Target**: >90% hit rate

### Memory Usage Alert

Create monitoring script:

```bash
# Add to crontab to check memory every hour
0 * * * * /home/travium/htdocs/scripts/check_redis_memory.sh
```

---

## Integration with Automation

Redis cache is automatically used by:
- `src/Core/Cache/RedisCache.php` - Main cache handler
- `src/Core/AI.php` - NPC personality caching
- `src/Core/Cache/BuildingCostCache.php` - Building cost pre-calculation

**Cache invalidation** happens automatically:
- Building cache cleared when building completes
- Personality cache expires after 1 hour
- Cost cache never expires (costs are static)

---

## Backup and Restore

### Manual Backup (rarely needed)

```bash
# Enable persistence temporarily
redis-cli CONFIG SET save "900 1"
redis-cli BGSAVE

# Copy dump file
sudo cp /var/lib/redis/dump.rdb /backup/redis-dump-$(date +%Y%m%d).rdb

# Disable persistence again
redis-cli CONFIG SET save ""
```

### Restore from Backup

```bash
# Stop Redis
sudo systemctl stop redis-server

# Replace dump file
sudo cp /backup/redis-dump-20241230.rdb /var/lib/redis/dump.rdb
sudo chown redis:redis /var/lib/redis/dump.rdb

# Start Redis
sudo systemctl start redis-server
```

**Note**: Since we use Redis as cache only, backups are usually unnecessary.

---

## Uninstall Redis (if needed)

```bash
# Stop service
sudo systemctl stop redis-server
sudo systemctl disable redis-server

# Remove packages
sudo apt remove redis-server php8.4-redis -y
sudo apt autoremove -y

# Remove configuration and data
sudo rm -rf /etc/redis
sudo rm -rf /var/lib/redis
```

---

## Performance Expectations

### Before Redis:
- NPC personality: 1 query per NPC per cycle
- Building data: 1 query per village per cycle
- 100 NPCs = 200+ queries per cycle

### After Redis:
- NPC personality: ~5 queries per hour (99% cache hit)
- Building data: Queries only after building completes
- 100 NPCs = ~20 queries per cycle (90% reduction)

**Expected improvement**: 40-60% faster NPC processing

---

**Status**: Ready for deployment  
**Risk Level**: Low (graceful degradation if Redis fails)  
**Impact**: High (significant performance improvement)
