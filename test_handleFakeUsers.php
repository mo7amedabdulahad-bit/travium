#!/usr/bin/env php
<?php
require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

echo "===== Testing handleFakeUsers =====\n\n";

$fakeModel = new \Model\FakeUserModel();

echo "Calling handleFakeUsers()...\n";
try {
    $fakeModel->handleFakeUsers();
    echo "✓ handleFakeUsers() completed successfully\n";
} catch (\Exception $e) {
    echo "✗ EXCEPTION: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n===== Complete =====\n";
