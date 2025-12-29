#!/usr/bin/env php
<?php
require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Config;

echo "===== Checking canRun() conditions =====\n\n";

$elapsed = getGameElapsedSeconds();
echo "getGameElapsedSeconds(): $elapsed\n";
echo "Check 1 (elapsed > 0): " . ($elapsed > 0 ? 'PASS' : 'FAIL') . "\n\n";

$setting = Config::getProperty("dynamic", "fakeAccountProcess");
echo "Config fakeAccountProcess: " . var_export($setting, true) . "\n";
echo "Check 2 (setting enabled): " . ($setting ? 'PASS' : 'FAIL') . "\n\n";

if ($elapsed > 0 && $setting) {
    echo "✓ canRun() should return TRUE\n";
} else {
    echo "✗ canRun() returns FALSE - this is why raids aren't processing!\n";
}

echo "\n===== Complete =====\n";
