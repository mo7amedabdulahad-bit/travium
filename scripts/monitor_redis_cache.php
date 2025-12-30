<?php
/**
 * Redis Cache Monitoring Script
 * 
 * Purpose: Monitor Redis cache performance and usage
 * Usage: php scripts/monitor_redis_cache.php
 */

// Bootstrap the game
define('GAME_SPEED', 1);
define('INCLUDE_PATH', __DIR__ . '/../');
require_once __DIR__ . '/../src/bootstrap.php';

use Core\Cache\RedisCache;
use Core\Cache\BuildingCostCache;

echo "=== Redis Cache Monitoring ===\n\n";

$cache = RedisCache::getInstance();

if (!$cache->isAvailable()) {
    echo "❌ ERROR: Redis is not available!\n";
    echo "   Check Redis status: sudo systemctl status redis-server\n";
    exit(1);
}

echo "✅ Redis is connected and available\n\n";

// Get Redis stats
$stats = $cache->getStats();

if  ($stats === null) {
    echo "⚠️  Unable to retrieve Redis statistics\n";
    exit(1);
}

echo "--- Redis Server Info ---\n";
echo "Version: " . $stats['version'] . "\n";
echo "Memory Used: " . $stats['memory_used'] . "\n";
echo "Total Keys: " . number_format($stats['total_keys']) . "\n";
echo "Cache Hits: " . number_format($stats['hits']) . "\n";
echo "Cache Misses: " . number_format($stats['misses']) . "\n";
echo "Hit Rate: " . $stats['hit_rate'] . "%\n";

if ($stats['hit_rate'] < 80) {
    echo "⚠️  Warning: Hit rate is below 80%, cache may need tuning\n";
} elseif ($stats['hit_rate'] >= 95) {
    echo "✅ Excellent hit rate!\n";
}

echo "\n";

// Building Cost Cache Stats
echo "--- Building Cost Cache ---\n";
$bcStats = BuildingCostCache::getStats();

if ($bcStats['cached']) {
    echo "✅ Building cost cache is populated\n";
    echo "   Building Types: " . $bcStats['type_count'] . "\n";
    echo "   Total Cached Costs: " . number_format($bcStats['total_costs']) . "\n";
    echo "   Redis Available: " . ($bcStats['redis_available'] ? 'Yes' : 'No') . "\n";
} else {
    echo "⚠️  Building cost cache is NOT populated\n";
    echo "   Run: bash scripts/warmup_redis_cache.sh\n";
}

echo "\n";

// Check for Travian-specific cache keys
echo "--- Application Cache Keys ---\n";

// This would require connecting to Redis directly
// For now, just show a sample
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$patterns = [
    'travian_npc_personality_*' => 'NPC Personalities',
    'travian_village_buildings_*' => 'Village Buildings',
    'travian_building_costs_all' => 'Building Costs (Pre-calculated)',
];

foreach ($patterns as $pattern => $description) {
    $keys = $redis->keys($pattern);
    $count = count($keys);
    
    if ($count > 0) {
        echo "✅ $description: $count keys\n";
        
        // Show sample TTL if keys exist
        if ($count > 0 && $pattern !== 'travian_building_costs_all') {
            $sampleKey = $keys[0];
            $ttl = $redis->ttl($sampleKey);
            if ($ttl > 0) {
                echo "   Sample TTL: " . gmdate("H:i:s", $ttl) . "\n";
            } elseif ($ttl === -1) {
                echo "   TTL: No expiration\n";
            }
        }
    } else {
        echo "⚠️  $description: No keys found\n";
    }
}

$redis->close();

echo "\n";

// Performance recommendations
echo "--- Performance Recommendations ---\n";

if ($stats['hit_rate'] < 90) {
    echo "⚠️  Consider increasing cache TTL for frequently accessed data\n";
}

if ($stats['total_keys'] > 10000) {
    echo "⚠️  High number of cached keys, consider increasing maxmemory\n";
}

if ($stats['total_keys'] < 10) {
    echo "ℹ️  Very few cached keys, make sure caching is being used\n";
}

echo "\n=== Monitoring Complete ===\n";
