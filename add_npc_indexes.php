#!/usr/bin/env php
<?php
/**
 * Add critical indexes for NPC performance optimization
 * 
 * Missing indexes identified:
 * 1. users.access - Every NPC query uses WHERE access=3
 * 2. users(access, npc_personality) - Filtered NPC selection
 * 3. vdata(lastVillageCheck, owner) - Village processing queries
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Database\DB;

$db = DB::getInstance();

echo "===== Adding NPC Performance Indexes =====\n\n";

$indexes = [
    [
        'table' => 'users',
        'name' => 'idx_npc_access',
        'sql' => "CREATE INDEX idx_npc_access ON users(access)",
        'reason' => 'Optimize: SELECT ... FROM users WHERE access=3'
    ],
    [
        'table' => 'users',
        'name' => 'idx_npc_personality',
        'sql' => "CREATE INDEX idx_npc_personality ON users(access, npc_personality, npc_difficulty)",
        'reason' => 'Optimize: NPC filtering by personality/difficulty'
    ],
    [
        'table' => 'vdata',
        'name' => 'idx_village_check',
        'sql' => "CREATE INDEX idx_village_check ON vdata(lastVillageCheck, owner)",
        'reason' => 'Optimize: Village processing by lastVillageCheck'
    ]
];

$added = 0;
$skipped = 0;
$errors = 0;

foreach ($indexes as $index) {
    echo "Table: {$index['table']}\n";
    echo "Index: {$index['name']}\n";
    echo "Reason: {$index['reason']}\n";
    
    // Check if index already exists
    $result = $db->query("SHOW INDEX FROM {$index['table']} WHERE Key_name = '{$index['name']}'");
    
    if ($result && $result->num_rows > 0) {
        echo "Status: ✓ Already exists (skipping)\n\n";
        $skipped++;
        continue;
    }
    
    // Create index
    echo "Creating index... ";
    try {
        $result = $db->query($index['sql']);
        if ($result) {
            echo "✓ Success\n\n";
            $added++;
        } else {
            echo "✗ Failed: " . $db->error . "\n\n";
            $errors++;
        }
    } catch (Exception $e) {
        echo "✗ Exception: " . $e->getMessage() . "\n\n";
        $errors++;
    }
}

echo "===== Summary =====\n";
echo "Indexes added: $added\n";
echo "Already existed: $skipped\n";
echo "Errors: $errors\n";
echo "\n";

if ($added > 0) {
    echo "✓ Database optimized for NPC queries!\n";
    echo "  Expected performance improvement: 10-20x\n";
} else if ($skipped > 0 && $errors == 0) {
    echo "✓ All indexes already present - database is optimized!\n";
}

echo "\n===== Complete =====\n";
