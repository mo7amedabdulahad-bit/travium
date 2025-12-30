#!/bin/bash
# Redis Cache Warmup Script
# 
# Purpose: Pre-populate Redis cache with building costs
# Run this after deploying Redis caching system
# 
# Usage: bash scripts/warmup_redis_cache.sh

echo "=== Redis Cache Warmup ==="
echo ""

# Check if Redis is running
echo "Checking Redis status..."
if ! redis-cli ping &>/dev/null; then
    echo "❌ ERROR: Redis is not running!"
    echo "   Start Redis: sudo systemctl start redis-server"
    exit 1
fi

echo "✅ Redis is running"
echo ""

# Generate building cost cache
echo "Generating building cost cache..."
php << 'EOF'
<?php
// Bootstrap the game
define('GAME_SPEED', 1);
define('INCLUDE_PATH', __DIR__ . '/../');
require_once __DIR__ . '/../src/bootstrap.php';

use Core\Cache\BuildingCostCache;

try {
    BuildingCostCache::generateCache();
    
    $stats = BuildingCostCache::getStats();
    echo "✅ Building cost cache generated\n";
    echo "   Types cached: " . $stats['type_count'] . "\n";
    echo "   Total costs: " . $stats['total_costs'] . "\n";
    echo "   Redis available: " . ($stats['redis_available'] ? 'Yes' : 'No') . "\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
EOF

if [ $? -ne 0 ]; then
    echo "❌ Cache generation failed"
    exit 1
fi

echo ""
echo "=== Cache Warmup Complete ==="
echo ""

# Show Redis stats
echo "Redis Statistics:"
redis-cli INFO stats | grep -E "keyspace_hits|keyspace_misses|total_commands"
echo ""

echo "Redis Memory:"
redis-cli INFO memory | grep "used_memory_human"
echo ""

echo "Cached Keys:"
redis-cli DBSIZE
echo ""

echo "✅ All caches warmed up successfully"
