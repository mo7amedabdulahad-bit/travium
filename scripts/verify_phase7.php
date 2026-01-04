<?php

// Phase 7 Verification Script
// Run this on the server after pulling changes and running migration 012

define('ROOT_PATH', dirname(__DIR__) . '/');
define('GLOBAL_CONFIG_FILE', ROOT_PATH . 'config.php');
define('CONNECTION_FILE', ROOT_PATH . 'servers/s1/include/connection.php');

echo "debug: loading bootstrap...\n";
require_once ROOT_PATH . 'src/bootstrap.php';
echo "debug: bootstrap loaded.\n\n";

echo "Phase 7 Verification Started...\n";

$errors = 0;

// Test 1: Check database schema
echo "\n=== Database Schema ===\n";

$db = \Core\Database\DB::getInstance();

// Check expansion_plan_json column
$result = $db->query("SHOW COLUMNS FROM users LIKE 'expansion_plan_json'");
if ($result && $result->num_rows > 0) {
    echo "[OK] Column users.expansion_plan_json exists.\n";
} else {
    echo "[ERROR] Column users.expansion_plan_json not found.\n";
    $errors++;
}

// Check village_number column
$result = $db->query("SHOW COLUMNS FROM npc_villages LIKE 'village_number'");
if ($result && $result->num_rows > 0) {
    echo "[OK] Column npc_villages.village_number exists.\n";
} else {
    echo "[ERROR] Column npc_villages.village_number not found.\n";
    $errors++;
}

// Check founded_at column
$result = $db->query("SHOW COLUMNS FROM npc_villages LIKE 'founded_at'");
if ($result && $result->num_rows > 0) {
    echo "[OK] Column npc_villages.founded_at exists.\n";
} else {
    echo "[ERROR] Column npc_villages.founded_at not found.\n";
    $errors++;
}

// Test 2: Check class existence
echo "\n=== Class Existence ===\n";

$classes = [
    'Core\\NpcExpansionManager',
    'Core\\NpcVillagePlacement',
    'Core\\NpcNameService',
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "[OK] Class $class exists.\n";
    } else {
        echo "[ERROR] Class $class not found.\n";
        $errors++;
    }
}

// Test 3: Check method existence
echo "\n=== Method Existence ===\n";

$methodChecks = [
    ['Core\\NpcExpansionManager', 'checkExpansionEligibility'],
    ['Core\\NpcExpansionManager', 'planExpansion'],
    ['Core\\NpcExpansionManager', 'foundNewVillage'],
    ['Core\\NpcExpansionManager', 'developNewVillage'],
    ['Core\\NpcVillagePlacement', 'selectExpansionSite'],
    ['Core\\NpcNameService', 'getNextVillageName'],
];

foreach ($methodChecks as [$class, $method]) {
    if (method_exists($class, $method)) {
        echo "[OK] Method $class::$method exists.\n";
    } else {
        echo "[ERROR] Method $class::$method not found.\n";
        $errors++;
    }
}

// Test 4: Test naming service
echo "\n=== Functional Test: Village Naming ===\n";

try {
    // Find a test NPC
    $testNpc = $db->query("SELECT id, name FROM users WHERE access = 3 LIMIT 1")->fetch_assoc();
    
    if ($testNpc) {
        $npcId = $testNpc['id'];
        $npcName = $testNpc['name'];
        
        // Test naming
        $villageName = \Core\NpcNameService::getNextVillageName($npcId);
        
        if (strpos($villageName, $npcName) === 0) {
            echo "[OK] Village naming works: '$villageName'\n";
        } else {
            echo "[ERROR] Village name '$villageName' doesn't start with NPC name '$npcName'\n";
            $errors++;
        }
    } else {
        echo "[SKIP] No NPCs found for naming test.\n";
    }
} catch (Exception $e) {
    echo "[ERROR] Naming test failed: " . $e->getMessage() . "\n";
    $errors++;
}

// Test 5: Test expansion eligibility
echo "\n=== Functional Test: Expansion Eligibility ===\n";

try {
    // Find NPCs and check eligibility
    $npcs = $db->query("SELECT * FROM users WHERE access = 3 LIMIT 5");
    
    $eligibleCount = 0;
    $testedCount = 0;
    
    while ($npc = $npcs->fetch_assoc()) {
        $testedCount++;
        $eligible = \Core\NpcExpansionManager::checkExpansionEligibility($npc);
        if ($eligible) {
            $eligibleCount++;
            echo "[INFO] NPC {$npc['id']} is eligible for expansion.\n";
        }
    }
    
    echo "[OK] Tested $testedCount NPCs, $eligibleCount eligible for expansion.\n";
    echo "[INFO] Eligibility depends on: game age, village count, resources, settlers.\n";
    
} catch (Exception $e) {
    echo "[ERROR] Eligibility test failed: " . $e->getMessage() . "\n";
    $errors++;
}

// Test 6: Test site selection
echo "\n=== Functional Test: Site Selection ===\n";

try {
    // Find NPC in alliance
    $testNpc = $db->query("
        SELECT * FROM users 
        WHERE access = 3 AND aid > 0 
        LIMIT 1
    ")->fetch_assoc();
    
    if ($testNpc) {
        $site = \Core\NpcVillagePlacement::selectExpansionSite($testNpc, 2);
        
        if ($site && isset($site['x']) && isset($site['y'])) {
            echo "[OK] Site selection works: ({$site['x']}, {$site['y']}) Role: {$site['role']}\n";
        } else {
            echo "[WARN] No suitable expansion site found (may be expected).\n";
        }
    } else {
        echo "[SKIP] No NPCs in alliances for site selection test.\n";
    }
} catch (Exception $e) {
    echo "[ERROR] Site selection test failed: " . $e->getMessage() . "\n";
    $errors++;
}

echo "\n=== Phase 7 Verification Complete ===\n";
echo "Errors: $errors\n";

if ($errors == 0) {
    echo "✅ All systems operational!\n";
    echo "\n📋 Manual Steps Required:\n";
    echo "1. Fast-forward game time to mid-game (>48 hours)\n";
    echo "2. Give test NPC resources and settlers for expansion\n";
    echo "3. Monitor expansion plan creation and village founding\n";
    echo "4. Verify village naming follows pattern: 'Name', 'Name 2', 'Name 3'\n";
    echo "5. Check new villages receive accelerated development\n";
}

exit($errors > 0 ? 1 : 0);
