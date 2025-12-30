<?php
/**
 * Redis Connection Test Script
 * 
 * Purpose: Verify Redis is properly installed and accessible
 * Usage: php scripts/test_redis.php
 */

echo "=== Redis Connection Test ===\n\n";

// Check if Redis extension is loaded
if (!extension_loaded('redis')) {
    echo "❌ ERROR: Redis extension is NOT loaded!\n";
    echo "   Install with: sudo apt install php8.4-redis\n";
    echo "   Then restart PHP-FPM: sudo systemctl restart php8.4-fpm\n";
    exit(1);
}

echo "✅ Redis extension is loaded\n";

// Try to connect to Redis
try {
    $redis = new Redis();
    $connected = $redis->connect('127.0.0.1', 6379, 2.0); // 2 second timeout
    
    if (!$connected) {
        throw new Exception("Connection failed");
    }
    
    echo "✅ Redis connected successfully\n";
    
    // Test PING
    $pong = $redis->ping();
    if ($pong === '+PONG' || $pong === true) {
        echo "✅ PING test successful\n";
    } else {
        echo "⚠️  PING returned: " . var_export($pong, true) . "\n";
    }
    
    // Test SET operation
    $testKey = 'travian_test_' . time();
    $testValue = 'hello_' . rand(1000, 9999);
    $setResult = $redis->set($testKey, $testValue, 60); // 60 second TTL
    
    if ($setResult) {
        echo "✅ SET operation successful\n";
    } else {
        echo "❌ SET operation failed\n";
        exit(1);
    }
    
    // Test GET operation
    $getValue = $redis->get($testKey);
    if ($getValue === $testValue) {
        echo "✅ GET operation successful: $getValue\n";
    } else {
        echo "❌ GET operation failed. Expected: $testValue, Got: $getValue\n";
        exit(1);
    }
    
    // Test TTL
    $ttl = $redis->ttl($testKey);
    if ($ttl > 0 && $ttl <= 60) {
        echo "✅ TTL check successful: {$ttl}s remaining\n";
    } else {
        echo "⚠️  TTL unexpected: $ttl\n";
    }
    
    // Test DELETE operation
    $delResult = $redis->del($testKey);
    if ($delResult === 1) {
        echo "✅ DELETE operation successful\n";
    } else {
        echo "❌ DELETE operation failed\n";
    }
    
    // Get Redis info
    echo "\n--- Redis Server Info ---\n";
    $info = $redis->info();
    
    if (isset($info['redis_version'])) {
        echo "Redis Version: " . $info['redis_version'] . "\n";
    }
    
    if (isset($info['used_memory_human'])) {
        echo "Memory Used: " . $info['used_memory_human'] . "\n";
    }
    
    if (isset($info['connected_clients'])) {
        echo "Connected Clients: " . $info['connected_clients'] . "\n";
    }
    
    if (isset($info['total_commands_processed'])) {
        echo "Total Commands: " . number_format($info['total_commands_processed']) . "\n";
    }
    
    // Count existing keys
    $dbSize = $redis->dbSize();
    echo "Total Keys in DB: " . number_format($dbSize) . "\n";
    
    // List Travian cache keys (if any)
    $travianKeys = $redis->keys('travian_*');
    if (!empty($travianKeys)) {
        echo "\nExisting Travian Cache Keys (" . count($travianKeys) . "):\n";
        foreach (array_slice($travianKeys, 0, 10) as $key) {
            $ttl = $redis->ttl($key);
            $ttlStr = $ttl === -1 ? 'no expiration' : "{$ttl}s";
            echo "  - $key (TTL: $ttlStr)\n";
        }
        if (count($travianKeys) > 10) {
            echo "  ... and " . (count($travianKeys) - 10) . " more\n";
        }
    } else {
        echo "\nNo existing Travian cache keys found (this is normal on fresh install)\n";
    }
    
    echo "\n=== All Redis Tests Passed! ===\n";
    echo "✅ Redis is ready for use\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    echo "Troubleshooting:\n";
    echo "1. Check Redis is running: sudo systemctl status redis-server\n";
    echo "2. Check Redis logs: sudo journalctl -u redis-server -n 50\n";
    echo "3. Restart Redis: sudo systemctl restart redis-server\n";
    echo "4. Check Redis config: sudo nano /etc/redis/redis.conf\n";
    exit(1);
}
