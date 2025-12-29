#!/usr/bin/env php
<?php
/**
 * Add npc_difficulty column to npc_info table
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Database\DB;

echo "===== Adding npc_difficulty Column =====\n\n";

$db = DB::getInstance();

// Check if column exists
$checkCol = $db->query("SHOW COLUMNS FROM npc_info LIKE 'npc_difficulty'");

if ($checkCol && $checkCol->num_rows > 0) {
    echo "✓ npc_difficulty column already exists\n";
} else {
    // Add column
    $db->query("ALTER TABLE npc_info ADD COLUMN npc_difficulty VARCHAR(10) DEFAULT 'medium' AFTER npc_personality");
    echo "✓ Added npc_difficulty column\n";
    
    // Update existing NPCs randomly
    $npcs = $db->query("SELECT uid FROM npc_info");
    if ($npcs) {
        $difficulties = ['easy', 'medium', 'hard'];
        while ($row = $npcs->fetch_assoc()) {
            $difficulty = $difficulties[array_rand($difficulties)];
            $db->query("UPDATE npc_info SET npc_difficulty='$difficulty' WHERE uid={$row['uid']}");
        }
        echo "✓ Updated " . $npcs->num_rows . " existing NPCs with random difficulties\n";
    }
}

echo "\n===== Complete =====\n";
