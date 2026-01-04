<?php

// Phase 6 Verification Script
// Run this on the server after pulling changes and running migration 011

define('ROOT_PATH', dirname(__DIR__) . '/');
define('GLOBAL_CONFIG_FILE', ROOT_PATH . 'config.php');
define('CONNECTION_FILE', ROOT_PATH . 'servers/s1/include/connection.php');

echo "debug: loading bootstrap...\n";
require_once ROOT_PATH . 'src/bootstrap.php';
echo "debug: bootstrap loaded.\n\n";

echo "Phase 6 Verification Started...\n";

$errors = 0;

// Test 1: Check database schema
echo "\n=== Database Schema ===\n";

$db = \Core\Database\DB::getInstance();

// Check ww_operation_state column
$result = $db->query("SHOW COLUMNS FROM users LIKE 'ww_operation_state'");
if ($result && $result->num_rows > 0) {
    echo "[OK] Column users.ww_operation_state exists.\n";
} else {
    echo "[ERROR] Column users.ww_operation_state not found.\n";
    $errors++;
}

// Check ww_alliance_role column
$result = $db->query("SHOW COLUMNS FROM users LIKE 'ww_alliance_role'");
if ($result && $result->num_rows > 0) {
    echo "[OK] Column users.ww_alliance_role exists.\n";
} else {
    echo "[ERROR] Column users.ww_alliance_role not found.\n";
    $errors++;
}

// Check WW event types
$result = $db->query("SHOW COLUMNS FROM npc_world_events LIKE 'event_type'");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $enumValues = $row['Type'];
    
    $requiredEvents = ['WWPlanReleased', 'WWUnderAttack', 'WWLevelUp', 'WWDefeated'];
    $allFound = true;
    
    foreach ($requiredEvents as $eventType) {
        if (strpos($enumValues, $eventType) === false) {
            echo "[ERROR] Event type '$eventType' not in enum.\n";
            $errors++;
            $allFound = false;
        }
    }
    
    if ($allFound) {
        echo "[OK] All WW event types present in npc_world_events.\n";
    }
} else {
    echo "[ERROR] Cannot check event_type column.\n";
    $errors++;
}

// Test 2: Check class existence
echo "\n=== Class Existence ===\n";

$classes = [
    'Core\\NpcWWOperations',
    'Core\\NpcWWContender',
    'Core\\NpcWWSpoiler',
    'Core\\NpcWWDefender',
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
    ['Core\\NpcWWOperations', 'onPlansReleased'],
    ['Core\\NpcWWOperations', 'progressWWOperation'],
    ['Core\\NpcWWOperations', 'handleWWDefeat'],
    ['Core\\NpcWWContender', 'executePlanCapture'],
    ['Core\\NpcWWContender', 'startWWConstruction'],
    ['Core\\NpcWWSpoiler', 'executeSpoilerActions'],
    ['Core\\NpcWWDefender', 'defendWorldWonder'],
    ['Core\\NpcWWDefender', 'attackWWOnLevelUp'],
];

foreach ($methodChecks as [$class, $method]) {
    if (method_exists($class, $method)) {
        echo "[OK] Method $class::$method exists.\n";
    } else {
        echo "[ERROR] Method $class::$method not found.\n";
        $errors++;
    }
}

// Test 4: Simulate WWPlanReleased event
echo "\n=== Functional Test: WWPlanReleased ===\n";

try {
    // Create test event
    $db->query("
        INSERT INTO npc_world_events (server_id, event_type) 
        VALUES (1, 'WWPlanReleased')
    ");
    
    // Process it
    \Core\NpcScheduler::processDueNpcs(1, 5);
    
    // Check if any NPCs entered PlanHunting state
    $planHunters = (int)$db->fetchScalar("
        SELECT COUNT(*) FROM users 
        WHERE access = 3 AND ww_operation_state = 'PlanHunting'
    ");
    
    if ($planHunters > 0) {
        echo "[OK] $planHunters NPC(s) entered PlanHunting state.\n";
    } else {
        echo "[WARN] No NPCs entered PlanHunting (may be expected if no alliances exist).\n";
    }
    
    // Cleanup
    $db->query("DELETE FROM npc_world_events WHERE event_type = 'WWPlanReleased'");
    
} catch (Exception $e) {
    echo "[ERROR] Functional test failed: " . $e->getMessage() . "\n";
    $errors++;
}

echo "\n=== Phase 6 Verification Complete ===\n";
echo "Errors: $errors\n";

if ($errors == 0) {
    echo "✅ All systems operational!\n";
    echo "\n📋 Manual Steps Required:\n";
    echo "1. Test WW plan capture by creating contender alliance\n";
    echo "2. Test spoiler behavior by creating spoiler alliance\n";
    echo "3. Test defense coordination by attacking WW village\n";
    echo "4. Monitor logs for WW operation state transitions\n";
}

exit($errors > 0 ? 1 : 0);
