#!/usr/bin/env php
<?php
/**
 * Debug AJAX requests - check what's actually being sent/received
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

echo "===== AJAX Debug Tool =====\n\n";

// Test 1: Check if AJAX endpoint is accessible
echo "Test 1: AJAX Endpoint Check\n";
echo "ajax.php exists: " . (file_exists(__DIR__ . '/servers/s1/public/ajax.php') ? 'YES' : 'NO') . "\n\n";

// Test 2: Check changeVillageName class
echo "Test 2: changeVillageName Controller\n";
$file = __DIR__ . '/src/Controller/Ajax/changeVillageName.php';
echo "File exists: " . (file_exists($file) ? 'YES' : 'NO') . "\n";
if (file_exists($file)) {
    echo "Class loadable: " . (class_exists('\\Controller\\Ajax\\changeVillageName') ? 'YES' : 'NO') . "\n";
}
echo "\n";

// Test 3: Simulate AJAX call
echo "Test 3: Simulate Village Name Change\n";
if (!\Core\Session::getInstance()->isValid()) {
    echo "ERROR: Not logged in\n\n";
} else {
    $_REQUEST['cmd'] = 'changeVillageName';
    $_POST['name'] = 'TestVillage123';
    $_POST['did'] = \Core\Village::getInstance()->getKid();
    
    echo "Village ID: " . $_POST['did'] . "\n";
    echo "New Name: " . $_POST['name'] . "\n";
    
    // Test StringChecker
    echo "StringChecker::isValidName(): ";
    echo (\Core\Helper\StringChecker::isValidName($_POST['name']) ? 'PASS' : 'FAIL') . "\n";
    
    echo "\n";
}

// Test 4: Check alliance validation
echo "Test 4: Alliance Name Validation\n";
$testNames = ['MyAlliance', 'Test-Alliance', 'Alliance 123', 'test@alliance'];
foreach ($testNames as $name) {
    $valid = \Core\Helper\StringChecker::isValidName($name);
    echo sprintf("%-20s: %s\n", $name, $valid ? 'VALID' : 'INVALID');
}

echo "\n===== Complete =====\n";
