#!/usr/bin/env php
<?php
/**
 * Test village name change AJAX endpoint
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Session;
use Core\Village;

echo "===== Testing Village Name Change =====\n\n";

// Simulate logged-in user
if (!Session::getInstance()->isValid()) {
    echo "ERROR: Not logged in. Please login first.\n";
    exit(1);
}

$currentName = Village::getInstance()->getName();
$currentKid = Village::getInstance()->getKid();

echo "Current Village: $currentName (ID: $currentKid)\n\n";

// Simulate the AJAX request
$_POST['name'] = 'Test Village 123';
$_POST['did'] = $currentKid;

$response = [];
$controller = new \Controller\Ajax\changeVillageName($response);
$controller->dispatch();

echo "Response:\n";
print_r($response);

echo "\n===== Complete =====\n";
