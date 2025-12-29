#!/usr/bin/env php
<?php
/**
 * Add npc_difficulty column to users table
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Database\DB;

echo "===== Adding npc_difficulty Column =====\n\n";

$db = DB::getInstance();

// Check if column exists in users table
$checkCol = $db->query("SHOW COLUMNS FROM users LIKE 'npc_difficulty'");

if ($checkCol && $checkCol->num_rows > 0) {
    echo "✓ npc_difficulty column already exists in users table\n";
} else {
    // Add column to users table
    echo "Adding npc_difficulty column to users table...\n";
    $result = $db->query("ALTER TABLE users ADD COLUMN npc_difficulty VARCHAR(10) NOT NULL DEFAULT 'medium' AFTER npc_personality");
    
    if ($result) {
        echo "✓ Added npc_difficulty column to users table\n";
        
        // Update existing NPCs randomly
        $npcs = $db->query("SELECT id FROM users WHERE access = 3");
        if ($npcs && $npcs->num_rows > 0) {
            $difficulties = ['easy', 'medium', 'hard'];
            $updated = 0;
            while ($row = $npcs->fetch_assoc()) {
                $difficulty = $difficulties[array_rand($difficulties)];
                if ($db->query("UPDATE users SET npc_difficulty='$difficulty' WHERE id={$row['id']}")) {
                    $updated++;
                }
            }
            echo "✓ Updated $updated existing NPCs with random difficulties\n";
        } else {
            echo "✓ No existing NPCs to update\n";
        }
    } else {
        echo "✗ Failed to add column\n";
    }
}

echo "\n===== Complete =====\n";


