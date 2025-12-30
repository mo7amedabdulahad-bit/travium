<?php
/**
 * OPcache and JIT Verification Script
 * 
 * Purpose: Verify OPcache and JIT are properly configured
 * Usage: php verify_opcache.php
 */

echo "=== OPcache Configuration Check ===\n\n";

// Check if OPcache is loaded
if (!extension_loaded('Zend OPcache')) {
    echo "❌ ERROR: OPcache extension is NOT loaded!\n";
    echo "   Install with: sudo apt install php8.4-opcache\n";
    exit(1);
}

echo "✅ OPcache extension is loaded\n\n";

// Get OPcache configuration
$config = opcache_get_configuration();
$status = opcache_get_status();

// Display Critical Settings
echo "--- Critical Settings ---\n";
echo "Enabled: " . ($config['directives']['opcache.enable'] ? '✅ YES' : '❌ NO') . "\n";
echo "Memory Consumption: " . $config['directives']['opcache.memory_consumption'] . " MB\n";
echo "Interned Strings Buffer: " . $config['directives']['opcache.interned_strings_buffer'] . " MB\n";
echo "Max Accelerated Files: " . $config['directives']['opcache.max_accelerated_files'] . "\n";
echo "Validate Timestamps: " . ($config['directives']['opcache.validate_timestamps'] ? '⚠️  YES (dev mode)' : '✅ NO (production mode)') . "\n";
echo "\n";

// JIT Status
echo "--- JIT Configuration ---\n";
if (isset($config['directives']['opcache.jit'])) {
    echo "JIT Enabled: " . ($config['directives']['opcache.jit'] ? '✅ YES' : '❌ NO') . "\n";
    echo "JIT Mode: " . $config['directives']['opcache.jit'] . "\n";
    echo "JIT Buffer Size: " . $config['directives']['opcache.jit_buffer_size'] . "\n";
    
    if ($status && isset($status['jit'])) {
        echo "JIT Status: ✅ Active\n";
        echo "JIT Buffer Used: " . number_format($status['jit']['buffer_size']) . " bytes\n";
        echo "JIT Buffer Free: " . number_format($status['jit']['buffer_free']) . " bytes\n";
    }
} else {
    echo "❌ JIT is NOT available (requires PHP 8.0+)\n";
}
echo "\n";

// OPcache Statistics
if ($status) {
    echo "--- OPcache Statistics ---\n";
    echo "Cached Files: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
    echo "Cache Hits: " . number_format($status['opcache_statistics']['hits']) . "\n";
    echo "Cache Misses: " . number_format($status['opcache_statistics']['misses']) . "\n";
    
    if ($status['opcache_statistics']['hits'] + $status['opcache_statistics']['misses'] > 0) {
        $hitRate = ($status['opcache_statistics']['hits'] / 
                   ($status['opcache_statistics']['hits'] + $status['opcache_statistics']['misses'])) * 100;
        echo "Hit Rate: " . number_format($hitRate, 2) . "%\n";
        
        if ($hitRate < 95) {
            echo "⚠️  Warning: Hit rate is below 95%, consider increasing memory_consumption\n";
        } else {
            echo "✅ Excellent hit rate!\n";
        }
    }
    
    echo "Memory Used: " . number_format($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
    echo "Memory Free: " . number_format($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . " MB\n";
    echo "Memory Wasted: " . number_format($status['memory_usage']['wasted_memory'] / 1024 / 1024, 2) . " MB\n";
} else {
    echo "⚠️  OPcache status not available\n";
}

echo "\n=== Verification Complete ===\n";

// Final recommendation
if ($config['directives']['opcache.enable'] && 
    isset($config['directives']['opcache.jit']) && 
    $config['directives']['opcache.jit']) {
    echo "\n✅ OPcache and JIT are properly configured!\n";
    echo "   Expected performance improvement: 30-50%\n";
} else {
    echo "\n⚠️  Configuration needs attention\n";
    echo "   Review settings in /etc/php/8.4/fpm/conf.d/99-opcache.ini\n";
}
