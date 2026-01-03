<?php
// Verification Script for Phase 3: Script Engine
// Run this after Phase 3 deployment.

define('ROOT_PATH', dirname(__DIR__) . '/');
define('GLOBAL_CONFIG_FILE', __DIR__ . '/dummy_global_config.php');
echo "debug: loading bootstrap...\n";
require_once ROOT_PATH . 'src/bootstrap.php';
echo "debug: bootstrap loaded.\n";

use Core\Database\DB;
use Core\NpcScriptEngine;
use Core\NpcConfig;

echo "Phase 3 Verification Started...\n";
$db = DB::getInstance();
$errors = 0;

// 1. Check Classes
$classes = [
    'Core\NpcScriptEngine',
    'Core\NpcBuildingManager',
    'Core\NpcTroopManager',
    'Core\NpcPassiveVillage'
];

foreach ($classes as $cls) {
    if (class_exists($cls)) {
        echo "[OK] Class $cls exists.\n";
    } else {
        echo "[FAIL] Class $cls NOT found.\n";
        $errors++;
    }
}

// 2. Check NpcConfig Methods
if (!method_exists(NpcConfig::class, 'getPersonalityTemplate')) {
    echo "[FAIL] NpcConfig::getPersonalityTemplate missing.\n";
    $errors++;
} else {
    echo "[OK] NpcConfig::getPersonalityTemplate exists.\n";
}

// 3. Functional Test: Fetch Template
echo "Testing Template Fetch...\n";
$template = NpcConfig::getPersonalityTemplate('Balanced', 'Early'); // Test fetch
// Note: 'Balanced' might not be in DB, we inserted 'Raider', 'Guardian' etc in 004 migration.
// Let's try one we know.
$template = NpcConfig::getPersonalityTemplate('Raider', 'Early');
if ($template) {
    echo "[OK] Fetched 'Raider' template.\n";
} else {
    echo "[WARN] Could not fetch 'Raider' template (Migration 004 not run?)\n";
}

// 4. Functional Test: Script Engine
echo "Testing Script Engine Tick (Simulation)...\n";
// Create a fake user row
$testUser = [
    'id' => 999999, // Fake ID
    'npc_personality' => 'Raider', 
    'npc_difficulty' => 'Medium', 
    'war_village_id' => 1
];

// We can't easily run it without real DB data for the user (villages etc).
// So we just check if the method is callable without crashing.
try {
    // This will likely return early because userId 999999 has no villages, 
    // but ensures no syntax errors in loading settings/policies.
    NpcScriptEngine::executeTick($testUser);
    echo "[OK] NpcScriptEngine::executeTick ran without exception.\n";
} catch (\Exception $e) {
    echo "[FAIL] NpcScriptEngine::executeTick threw exception: " . $e->getMessage() . "\n";
    $errors++;
}

echo "\nVerification Complete. Errors: $errors\n";
exit($errors === 0 ? 0 : 1);
