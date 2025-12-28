#!/usr/bin/env php
<?php
/**
 * Migration Script: Grant Gold Club + Create Farm-Lists for Existing NPCs
 * 
 * This script should be run ONCE to upgrade existing NPCs with:
 * 1. Gold club access (permanent)
 * 2. Auto-created farm-lists with 10 targets
 * 
 * Usage: php migrate_npc_farmlists.php
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Database\DB;
use Core\NpcConfig;

echo "===== NPC Farm-List Migration =====\n\n";

$db = DB::getInstance();

// Get all existing NPCs
$npcs = $db->query("SELECT u.id as uid, v.kid, u.name 
                    FROM users u
                    JOIN vdata v ON u.id = v.owner
                    WHERE u.access = 3
                    GROUP BY u.id");

if (!$npcs || $npcs->num_rows == 0) {
    echo "No NPCs found. Exiting.\n";
    exit(0);
}

$total = $npcs->num_rows;
$processed = 0;
$goldGranted = 0;
$farmListsCreated = 0;

echo "Found $total NPCs to process.\n\n";

while ($npc = $npcs->fetch_assoc()) {
    $uid = $npc['uid'];
    $kid = $npc['kid'];
    $name = $npc['name'];
    
    echo "Processing NPC: $name (ID: $uid)\n";
    
    // 1. Grant gold club
    $hasGold = $db->fetchScalar("SELECT goldclub FROM users WHERE id=$uid");
    if (!$hasGold || $hasGold < time()) {
        NpcConfig::grantGoldClub($uid);
        echo "  ✓ Granted gold club\n";
        $goldGranted++;
    } else {
        echo "  - Already has gold club\n";
    }
    
    // 2. Create farm-list if doesn't exist
    $hasFarmList = $db->fetchScalar("SELECT COUNT(*) FROM farmlist WHERE owner=$uid");
    if (!$hasFarmList) {
        $listId = NpcConfig::createNpcFarmList($uid, $kid);
        if ($listId) {
            echo "  ✓ Created farm-list (ID: $listId)\n";
            $farmListsCreated++;
        } else {
            echo "  ✗ Failed to create farm-list\n";
        }
    } else {
        echo "  - Already has farm-list\n";
    }
    
    $processed++;
    echo "\n";
}

echo "===== Migration Complete =====\n";
echo "NPCs processed: $processed/$total\n";
echo "Gold club granted: $goldGranted\n";
echo "Farm-lists created: $farmListsCreated\n";
echo "==============================\n";
