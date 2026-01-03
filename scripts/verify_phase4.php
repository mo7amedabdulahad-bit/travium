<?php

// Run this after deploying Phase 4 code

define('ROOT_PATH', dirname(__DIR__) . '/');
define('GLOBAL_CONFIG_FILE', ROOT_PATH . 'config.php');
define('CONNECTION_FILE', ROOT_PATH . 'servers/s1/include/connection.php');
echo "debug: loading bootstrap...\n";
require_once ROOT_PATH . 'src/bootstrap.php';
echo "debug: bootstrap loaded.\n\n";

echo "Phase 4 Verification Started...\n";

$errors = 0;

// Test 1: Check if new classes exist
$classes = [
    'Core\NpcTargetSelector',
    'Core\NpcScoutingManager',
    'Core\NpcRaidManager',
    'Core\NpcAttackManager',
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "[OK] Class $class exists.\n";
    } else {
        echo "[ERROR] Class $class not found.\n";
        $errors++;
    }
}

// Test 2: Check NpcTargetSelector::selectTarget method
if (method_exists('Core\NpcTargetSelector', 'selectTarget')) {
    echo "[OK] NpcTargetSelector::selectTarget exists.\n";
} else {
    echo "[ERROR] NpcTargetSelector::selectTarget not found.\n";
    $errors++;
}

// Test 3: Check NpcScriptEngine has executeWarVillageLogic (private method - can't check directly)
$reflection = new ReflectionClass('Core\NpcScriptEngine');
if ($reflection->hasMethod('executeWarVillageLogic')) {
    echo "[OK] NpcScriptEngine::executeWarVillageLogic exists.\n";
} else {
    echo "[ERROR] NpcScriptEngine::executeWarVillageLogic not found.\n";
    $errors++;
}

// Test 4: Simulate target selection
echo "\nTesting Target Selection...\n";
try {
    $db = \Core\Database\DB::getInstance();
    
    // Find any NPC with a war village
    $npcRow = $db->query("SELECT id, war_village_id FROM users WHERE access=3 AND war_village_id IS NOT NULL LIMIT 1")->fetch_assoc();
    
    if ($npcRow && $npcRow['war_village_id']) {
        $warVillageId = (int)$npcRow['war_village_id'];
        $template = []; // Mock template
        $policy = [];   // Mock policy
        
        $target = \Core\NpcTargetSelector::selectTarget($warVillageId, $template, $policy);
        
        if ($target) {
            echo "[OK] selectTarget() returned target: $target\n";
        } else {
            echo "[WARN] selectTarget() returned null (no valid targets in range)\n";
        }
    } else {
        echo "[SKIPPED] No NPC with war village found for testing.\n";
    }
} catch (Exception $e) {
    echo "[ERROR] selectTarget() threw: " . $e->getMessage() . "\n";
    $errors++;
}

echo "\nVerification Complete. Errors: $errors\n";
exit($errors > 0 ? 1 : 0);
