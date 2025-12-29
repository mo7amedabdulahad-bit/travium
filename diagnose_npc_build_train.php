<?php
/**
 * NPC Building & Training Diagnostic Script
 * 
 * This script diagnoses why NPCs are not building or training troops.
 * Run on Ubuntu server: sudo -u travium php diagnose_npc_build_train.php
 */

// Bootstrap the game environment
require_once(__DIR__ . '/servers/s1/include/Autoloader.php');

use Core\Database\DB;
use Core\AI;
use Core\NpcConfig;
use Core\AI\NpcLogger;

echo "==============================================\n";
echo "  NPC Building & Training Diagnostics\n";
echo "==============================================\n\n";

$db = DB::getInstance();

// 1. Get all NPCs
echo "1. Checking NPC Count...\n";
$npcCount = $db->fetchScalar("SELECT COUNT(*) FROM users WHERE access=3");
echo "   Total NPCs (access=3): $npcCount\n\n";

if ($npcCount == 0) {
    echo "   ❌ NO NPCs FOUND! This is the problem - no NPCs exist.\n";
    echo "   To create NPCs, use the admin panel or RegisterModel::addFakeUser()\n\n";
    exit(1);
}

// 2. Check a sample of NPCs
echo "2. Analyzing Sample NPCs (showing first 5)...\n\n";
$npcs = $db->query("SELECT u.id, u.name, u.race, u.npc_difficulty, u.npc_personality,
                           u.lastVillageExpand, u.lastHeroExpCheck,
                           (SELECT COUNT(*) FROM vdata WHERE owner=u.id) as village_count
                    FROM users u 
                    WHERE u.access=3 
                    LIMIT 5");

while ($npc = $npcs->fetch_assoc()) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "NPC: {$npc['name']} (ID: {$npc['id']})\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "  Race: {$npc['race']}\n";
    echo "  Difficulty: {$npc['npc_difficulty']}\n";
    echo "  Personality: {$npc['npc_personality']}\n";
    echo "  Villages: {$npc['village_count']}\n\n";
    
    // Get NPC's villages
    $villages = $db->query("SELECT kid, name FROM vdata WHERE owner={$npc['id']}");
    
    while ($village = $villages->fetch_assoc()) {
        $kid = $village['kid'];
        echo "  📍 Village: {$village['name']} (kid: $kid)\n";
        echo "  ───────────────────────────────────────────\n";
        
        // Get resources
        $resources = $db->query("SELECT wood, clay, iron, crop, maxcrop 
                                FROM vdata 
                                WHERE kid=$kid")->fetch_assoc();
        
        echo "  Resources:\n";
        echo "    🪵 Wood: " . floor($resources['wood']) . "\n";
        echo "    🧱 Clay: " . floor($resources['clay']) . "\n";
        echo "    ⚙️  Iron: " . floor($resources['iron']) . "\n";
        echo "    🌾 Crop: " . floor($resources['crop']) . " / {$resources['maxcrop']}\n\n";
        
        // Get buildings
        echo "  Buildings:\n";
        $buildings = $db->query("SELECT f.*, vref FROM fdata f WHERE vref=$kid ORDER BY id ASC");
        
        $hasBarracks = false;
        $hasStable = false;
        $hasWorkshop = false;
        $barracksLevel = 0;
        $stableLevel = 0;
        $workshopLevel = 0;
        $buildingCount = 0;
        
        while ($building = $buildings->fetch_assoc()) {
            $buildingName = \Game\Buildings\Building::getName($building['f' . $building['id']], $npc['race']);
            $level = $building['f' . $building['id'] . 't'];
            
            if ($level > 0) {
                $buildingCount++;
                if ($buildingCount <= 10) { // Only show first 10
                    echo "    [{$building['id']}] $buildingName (Level $level)\n";
                }
                
                // Check for military buildings
                $gid = $building['f' . $building['id']];
                if ($gid == 19 || $gid == 29 || $gid == 39) { // Barracks
                    $hasBarracks = true;
                    $barracksLevel = $level;
                }
                if ($gid == 20 || $gid == 30 || $gid == 40) { // Stable
                    $hasStable = true;
                    $stableLevel = $level;
                }
                if ($gid == 21 || $gid == 31 || $gid == 41) { // Workshop
                    $hasWorkshop = true;
                    $workshopLevel = $level;
                }
            }
        }
        
        if ($buildingCount > 10) {
            echo "    ... and " . ($buildingCount - 10) . " more buildings\n";
        }
        echo "\n";
        
        echo "  Military Buildings:\n";
        echo "    Barracks: " . ($hasBarracks ? "✅ Level $barracksLevel" : "❌ Not built") . "\n";
        echo "    Stable: " . ($hasStable ? "✅ Level $stableLevel" : "❌ Not built") . "\n";
        echo "    Workshop: " . ($hasWorkshop ? "✅ Level $workshopLevel" : "❌ Not built") . "\n\n";
        
        // Check troops
        echo "  Troops:\n";
        $troops = $db->query("SELECT * FROM units WHERE vref=$kid")->fetch_assoc();
        $hasTroops = false;
        $totalTroops = 0;
        
        if ($troops) {
            for ($i = 1; $i <= 11; $i++) {
                $unitCount = $troops['u' . $i] ?? 0;
                if ($unitCount > 0) {
                    $hasTroops = true;
                    $totalTroops += $unitCount;
                    echo "    Unit $i: $unitCount\n";
                }
            }
        }
        
        if (!$hasTroops) {
            echo "    ❌ NO TROOPS!\n";
        } else {
            echo "    ✅ Total troops: $totalTroops\n";
        }
        echo "\n";
        
        // Check training queue
        echo "  Training Queue:\n";
        $training = $db->query("SELECT * FROM training WHERE vref=$kid");
        if ($training->num_rows > 0) {
            echo "    ✅ Has " . $training->num_rows . " training task(s)\n";
            while ($train = $training->fetch_assoc()) {
                echo "       Units training: ";
                for ($i = 1; $i <= 11; $i++) {
                    if ($train['u' . $i] > 0) {
                        echo "u$i={$train['u' . $i]} ";
                    }
                }
                echo "\n";
            }
        } else {
            echo "    ⚠️  No training in progress\n";
        }
        echo "\n";
        
        // Check building queue
        echo "  Building Queue:\n";
        $buildQueue = $db->query("SELECT * FROM building_upgrade WHERE kid=$kid");
        if ($buildQueue->num_rows > 0) {
            echo "    ✅ Has " . $buildQueue->num_rows . " building task(s)\n";
        } else {
            echo "    ⚠️  No buildings in queue\n";
        }
        echo "\n";
        
        // Check last village check time
        $lastCheck = $db->fetchScalar("SELECT lastVillageCheck FROM vdata WHERE kid=$kid");
        $timeSince = time() - $lastCheck;
        echo "  Last AI Check: $timeSince seconds ago\n";
        if ($timeSince > 300) {
            echo "    ⚠️  Village hasn't been processed recently (>5 min)\n";
        }
        echo "\n";
    }
}

// 3. Test AI execution
echo "\n3. Testing AI Execution...\n\n";
$testNpc = $db->query("SELECT u.id, u.name, v.kid, v.name as vname
                       FROM users u
                       JOIN vdata v ON v.owner = u.id
                       WHERE u.access=3
                       LIMIT 1")->fetch_assoc();

if ($testNpc) {
    echo "Testing with NPC: {$testNpc['name']} (ID: {$testNpc['id']})\n";
    echo "Village: {$testNpc['vname']} (kid: {$testNpc['kid']})\n\n";
    
    // Get NPC config
    $config = NpcConfig::getNpcConfig($testNpc['id']);
    $difficulty = $config['npc_difficulty'] ?? 'beginner';
    $iterations = NpcConfig::getRandomizedIterations($difficulty);
    
    echo "NPC Difficulty: $difficulty\n";
    echo "AI Iterations: $iterations\n\n";
    
    echo "Triggering AI::doSomethingRandom()...\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    try {
        AI::doSomethingRandom($testNpc['kid'], $iterations);
        echo "✅ AI execution completed\n\n";
    } catch (Exception $e) {
        echo "❌ AI execution failed: " . $e->getMessage() . "\n\n";
    }
    
    echo "Check logs at /home/travium/logs/npc_activity.log for details\n";
}

// 4. Check FakeUserModel processing
echo "\n4. Checking FakeUserModel Configuration...\n\n";
$gameSpeed = \getGameSpeed();
echo "Game Speed: {$gameSpeed}x\n";

if ($gameSpeed <= 10) {
    $interval = mt_rand(600, 3600);
} else if ($gameSpeed <= 100) {
    $interval = mt_rand(200, 400);
} else if ($gameSpeed <= 1000) {
    $interval = mt_rand(100, 250);
} else {
    $interval = mt_rand(50, 150);
}

echo "Processing Interval: ~" . round($interval / 60, 1) . " minutes\n";
echo "Villages per cycle: 10 (LIMIT)\n\n";

$checkInterval = 200;
$time = time() - $checkInterval;
$pendingVillages = $db->fetchScalar("SELECT COUNT(*) FROM vdata v 
                                     JOIN users u ON v.owner = u.id 
                                     WHERE v.lastVillageCheck < $time 
                                       AND u.access=3");

echo "Villages waiting for AI check: $pendingVillages\n";

if ($pendingVillages > 20) {
    echo "⚠️  WARNING: Many villages are waiting. With LIMIT 10, it may take a while to process all.\n";
}

// 5. Summary
echo "\n==============================================\n";
echo "  DIAGNOSTIC SUMMARY\n";
echo "==============================================\n\n";

// Check for common issues
$issues = [];

// Issue 1: No military buildings
$noMilitaryBuildings = $db->fetchScalar("SELECT COUNT(DISTINCT v.kid) 
                                         FROM vdata v
                                         JOIN users u ON v.owner = u.id
                                         LEFT JOIN fdata f ON f.vref = v.kid 
                                         WHERE u.access=3
                                           AND v.kid NOT IN (
                                               SELECT vref FROM fdata 
                                               WHERE (f19t > 0 OR f29t > 0 OR f39t > 0)
                                           )");

if ($noMilitaryBuildings > 0) {
    $issues[] = "⚠️  $noMilitaryBuildings NPC village(s) don't have barracks built";
}

// Issue 2: No troops
$noTroops = $db->fetchScalar("SELECT COUNT(DISTINCT v.kid)
                              FROM vdata v
                              JOIN users u ON v.owner = u.id
                              LEFT JOIN units un ON un.vref = v.kid
                              WHERE u.access=3
                                AND (un.u1 + un.u2 + un.u3 + un.u4 + un.u5 + un.u6 + un.u7 + un.u8 + un.u9 + un.u10 + un.u11) = 0");

if ($noTroops > 0) {
    $issues[] = "❌ $noTroops NPC village(s) have ZERO troops";
}

// Issue 3: Low resources
$lowResources = $db->fetchScalar("SELECT COUNT(*)
                                  FROM vdata v
                                  JOIN users u ON v.owner = u.id
                                  WHERE u.access=3
                                    AND (v.wood < 100 OR v.clay < 100 OR v.iron < 100)");

if ($lowResources > 0) {
    $issues[] = "⚠️  $lowResources NPC village(s) have very low resources (<100)";
}

if (empty($issues)) {
    echo "✅ No obvious issues detected!\n";
    echo "   NPCs should be building and training.\n";
    echo "   Check /home/travium/logs/npc_activity.log for details.\n";
} else {
    echo "Issues Found:\n\n";
    foreach ($issues as $issue) {
        echo "  $issue\n";
    }
    
    echo "\nRecommendations:\n\n";
    echo "  1. Check if AI is actually running\n";
    echo "     - Look for BUILD/TRAIN logs in npc_activity.log\n";
    echo "     - Verify FakeUserModel::handleFakeUsers() is being called\n\n";
    
    echo "  2. Check if resource production is working\n";
    echo "     - NPCs need resources to build/train\n";
    echo "     - Verify production buildings exist\n\n";
    
    echo "  3. Monitor one NPC over time\n";
    echo "     - Wait 5-10 minutes\n";
    echo "     - Check if buildings/troops increase\n";
    echo "     - Check logs for that specific NPC\n\n";
}

echo "\n==============================================\n";
echo "  Diagnostic Complete!\n";
echo "==============================================\n";
