#!/usr/bin/env php
<?php
/**
 * Test StringChecker validation
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Helper\StringChecker;

echo "===== StringChecker Validation Test =====\n\n";

$testCases = [
    'MyVillage' => 'Simple name',
    'Test Village' => 'Name with space',
    'Village123' => 'Name with numbers',
    'Test-Village' => 'Name with hyphen',
    'Village_1' => 'Name with underscore',
    'MyAlliance!' => 'Name with exclamation',
    '@Test' => 'Name starting with @',
    '' => 'Empty string',
];

foreach ($testCases as $name => $description) {
    $isValid = StringChecker::isValidName($name);
    echo sprintf("%-20s %-30s: %s\n", 
        $name, 
        "($description)", 
        $isValid ? '✓ VALID' : '✗ INVALID'
    );
}

echo "\n===== Testing clearString() =====\n\n";
$testNames = ['MyVillage', 'Test-Village', 'Village_123', 'Test@Village'];
foreach ($testNames as $name) {
    $cleared = \Core\Helper\StringChecker::clearString($name);
    echo sprintf("%-20s → %-20s (empty: %s)\n", 
        $name, 
        "'$cleared'", 
        empty($cleared) ? 'YES' : 'NO'
    );
}

echo "\n===== Complete =====\n";
