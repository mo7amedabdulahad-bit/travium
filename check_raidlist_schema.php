#!/usr/bin/env php
<?php
/**
 * Quick script to check raidlist table structure
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Database\DB;

$db = DB::getInstance();

echo "===== Checking raidlist table structure =====\n\n";

$result = $db->query("DESCRIBE raidlist");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "{$row['Field']} ({$row['Type']}) - {$row['Null']} - {$row['Key']}\n";
    }
} else {
    echo "Failed to describe table\n";
}

echo "\n===== Sample row =====\n\n";
$sample = $db->query("SELECT * FROM raidlist LIMIT 1");
if ($sample && $sample->num_rows > 0) {
    print_r($sample->fetch_assoc());
} else {
    echo "No rows found\n";
}
