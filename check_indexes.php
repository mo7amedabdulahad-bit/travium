#!/usr/bin/env php
<?php
require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Database\DB;

$db = DB::getInstance();

echo "===== Checking Database Indexes =====\n\n";

// Check indexes on critical NPC tables
$tables = ['users', 'vdata', 'farmlist', 'raidlist', 'movement'];

foreach ($tables as $table) {
    echo "Table: $table\n";
    echo str_repeat('-', 60) . "\n";
    
    $result = $db->query("SHOW INDEX FROM $table");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo sprintf("  %-20s %-15s %-15s\n", 
                $row['Key_name'], 
                $row['Column_name'], 
                $row['Index_type']
            );
        }
    }
    echo "\n";
}

echo "===== Complete =====\n";
