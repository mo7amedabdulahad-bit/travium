#!/usr/bin/env php
<?php
/**
 * Debug alliance description saving
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Database\DB;
use Core\Session;

echo "===== Alliance Description Debug =====\n\n";

if (!Session::getInstance()->isValid()) {
    echo "ERROR: Not logged in\n";
    exit(1);
}

$aid = Session::getInstance()->getAllianceId();
if (!$aid) {
    echo "ERROR: Not in an alliance\n";
    exit(1);
}

echo "Alliance ID: $aid\n\n";

$db = DB::getInstance();
$current = $db->query("SELECT desc1, desc2 FROM alidata WHERE id=$aid")->fetch_assoc();

echo "Current descriptions in database:\n";
echo "desc1: " . substr($current['desc1'], 0, 100) . "...\n";
echo "desc2: " . substr($current['desc2'], 0, 100) . "...\n\n";

// Simulate the save
$test_desc1 = "Test Description 1 - " . time();
$test_desc2 = "Test Description 2 - " . time();

echo "Testing save with:\n";
echo "desc1: $test_desc1\n";
echo "desc2: $test_desc2\n\n";

// Use same sanitization as the controller
$test_desc1 = $db->real_escape_string(filter_var($test_desc1, FILTER_SANITIZE_STRING));
$test_desc2 = $db->real_escape_string(filter_var($test_desc2, FILTER_SANITIZE_STRING));

echo "After sanitization:\n";
echo "desc1: $test_desc1\n";
echo "desc2: $test_desc2\n\n";

// Check validation
$valid1 = \Core\Helper\StringChecker::isValidMessage($test_desc1);
$valid2 = \Core\Helper\StringChecker::isValidMessage($test_desc2);

echo "Validation results:\n";
echo "desc1 valid: " . ($valid1 ? 'YES' : 'NO') . "\n";
echo "desc2 valid: " . ($valid2 ? 'YES' : 'NO') . "\n\n";

if ($valid1 && $valid2) {
    echo "Both valid - attempting UPDATE...\n";
    $result = $db->query("UPDATE alidata SET desc1='$test_desc1', desc2='$test_desc2' WHERE id=$aid");
    echo "Update result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Affected rows: " . $db->affectedRows() . "\n\n";
    
    // Check if it saved
    $check = $db->query("SELECT desc1, desc2 FROM alidata WHERE id=$aid")->fetch_assoc();
    echo "Values in database after update:\n";
    echo "desc1: " . $check['desc1'] . "\n";
    echo "desc2: " . $check['desc2'] . "\n";
} else {
    echo "Validation FAILED - this is why it's not saving!\n";
}

echo "\n===== Complete =====\n";
