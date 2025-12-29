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
    // Add column with proper default
    echo "Adding npc_difficulty column...\n";
    $result = $db->query("ALTER TABLE npc_info ADD COLUMN npc_difficulty VARCHAR(10) NOT NULL DEFAULT 'medium' AFTER npc_personality");
    
    if ($result) {
        echo "✓ Added npc_difficulty column\n";
        
        // Update existing NPCs randomly
        $npcs = $db->query("SELECT uid FROM npc_info");
        if ($npcs && $npcs->num_rows > 0) {
            $difficulties = ['easy', 'medium', 'hard'];
            $updated = 0;
            while ($row = $npcs->fetch_assoc()) {
                $difficulty = $difficulties[array_rand($difficulties)];
                if ($db->query("UPDATE npc_info SET npc_difficulty='$difficulty' WHERE uid={$row['uid']}")) {
                    $updated++;
                }
            }
            echo "✓ Updated $updated existing NPCs with random difficulties\n";
        } else {
            echo "✓ No existing NPCs to update\n";
        }
    } else {
        echo "✗ Failed to add column: " . $db->error() . "\n";
    }
}

echo "\n===== Complete =====\n";

