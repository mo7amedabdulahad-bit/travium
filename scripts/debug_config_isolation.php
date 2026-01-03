<?php
// Debug script for config.php without modifying core files
define('ROOT_PATH', dirname(__DIR__) . '/');
define('INCLUDE_PATH', ROOT_PATH . 'src/');

echo "Inspecting src/config.php...\n";

if (!file_exists(INCLUDE_PATH . 'config.php')) {
    die("FAIL: config.php not found.\n");
}

try {
    // 1. Check for die/exit tokens blindly (rudimentary check)
    $content = file_get_contents(INCLUDE_PATH . 'config.php');
    if (stripos($content, 'die(') !== false || stripos($content, 'exit') !== false) {
        echo "WARN: 'die' or 'exit' found in config.php. This might be normal if conditional, or the cause of your issue.\n";
    }

    // 2. Try including it in isolation
    echo "Attempting to include config.php in isolation...\n";
    $cfg = include INCLUDE_PATH . 'config.php';
    
    echo "Include returned type: " . gettype($cfg) . "\n";
    
    if (is_object($cfg)) {
        echo "Success: Returned an object.\n";
        echo "Keys: " . implode(', ', array_keys((array)$cfg)) . "\n";
    } elseif (is_array($cfg)) {
        echo "Success: Returned an array.\n";
        echo "Keys: " . implode(', ', array_keys($cfg)) . "\n";
    } else {
        echo "FAIL: Returned unexpected type (expected object or array).\n";
    }

} catch (\Throwable $t) {
    echo "CRITICAL: Exception/Error during verify: " . $t->getMessage() . "\n";
    echo $t->getTraceAsString() . "\n";
}
