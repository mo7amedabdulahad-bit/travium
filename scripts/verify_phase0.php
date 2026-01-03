<?php
// Verification Script for Phase 0
// Run this on the server after pulling changes and running migrations

define('ROOT_PATH', dirname(__DIR__) . '/');
echo "debug: loading bootstrap...\n";
require_once ROOT_PATH . 'src/bootstrap.php';
echo "debug: bootstrap loaded.\n";

use Core\Database\DB;
use Core\NpcConfig;

echo "Phase 0 Verification Started...\n";
$db = DB::getInstance();
$errors = 0;

// 1. Check Tables
$tables = ['npc_villages', 'npc_world_events', 'npc_names', 'server_settings', 'npc_personality_templates', 'npc_difficulty_policies'];
foreach ($tables as $table) {
    if (!$db->query("SELECT 1 FROM $table LIMIT 1")) {
        // It might be empty, so check schema
        $check = $db->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows == 0) {
            echo "[FAIL] Table '$table' is MISSING.\n";
            $errors++;
        } else {
            echo "[OK] Table '$table' exists.\n";
        }
    } else {
        echo "[OK] Table '$table' exists and is readable.\n";
    }
}

// 2. Check NpcConfig Methods
if (!method_exists(NpcConfig::class, 'getNameFromPool')) {
    echo "[FAIL] NpcConfig::getNameFromPool is missing.\n";
    $errors++;
} else {
    echo "[OK] NpcConfig::getNameFromPool exists.\n";
}

if (!method_exists(NpcConfig::class, 'registerNpcVillage')) {
    echo "[FAIL] NpcConfig::registerNpcVillage is missing.\n";
    $errors++;
} else {
    echo "[OK] NpcConfig::registerNpcVillage exists.\n";
}

// 3. functional Test (Simulated)
echo "Simulating Name Fetch...\n";
// Note: This relies on DB having data, which it might not yet if migrations ran but pool is empty
// We populated pool in migration, so it should work if migration ran.
$name = NpcConfig::getNameFromPool();
if ($name) {
    echo "[OK] Fetched name from pool: $name\n";
} else {
    echo "[WARN] Could not fetch name from pool (Pool empty or DB not migrated?)\n";
}

echo "Verification Complete. Errors: $errors\n";
exit($errors === 0 ? 0 : 1);
