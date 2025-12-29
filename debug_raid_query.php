#!/usr/bin/env php
<?php
require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Database\DB;

$db = DB::getInstance();

echo "===== Raid Query Test =====\n\n";

$query = "SELECT v.kid, v.owner, u.name, u.npc_personality,
                 JSON_EXTRACT(u.npc_info, '$.last_raid_time') as last_raid
          FROM vdata v
          JOIN users u ON v.owner = u.id
          WHERE u.access=3
          LIMIT 10";

echo "Running query...\n";
$result = $db->query($query);

if (!$result) {
    echo "✗ Query FAILED: " . $db->error . "\n";
} else {
    echo "✓ Query succeeded\n";
    echo "Rows returned: " . $result->num_rows . "\n\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "Village {$row['kid']} ({$row['name']}): last_raid = " . ($row['last_raid'] ?? 'NULL') . "\n";
    }
}

echo "\n===== Complete =====\n";
