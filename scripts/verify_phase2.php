<?php
// Verification Script for Phase 2: NPC Scheduler
// Run this after applying migration 005_npc_scheduler_columns.sql

define('ROOT_PATH', dirname(__DIR__) . '/');
define('GLOBAL_CONFIG_FILE', __DIR__ . '/dummy_global_config.php');
echo "debug: loading bootstrap...\n";
require_once ROOT_PATH . 'src/bootstrap.php';
echo "debug: bootstrap loaded.\n";

use Core\Database\DB;
use Core\NpcScheduler;
use Core\NpcWarVillageManager;

echo "Phase 2 Verification Started...\n";
$db = DB::getInstance();
$errors = 0;

// 1. Check Schema Columns
echo "Checking 'users' table columns...\n";
$cols = $db->query("SHOW COLUMNS FROM users");
$foundCols = [];
while ($row = $cols->fetch_assoc()) {
    $foundCols[] = $row['Field'];
}

$required = ['next_tick_at', 'tick_interval_seconds', 'war_village_id'];
foreach ($required as $req) {
    if (!in_array($req, $foundCols)) {
        echo "[FAIL] Column '$req' missing in 'users' table.\n";
        $errors++;
    } else {
        echo "[OK] Column '$req' found.\n";
    }
}

// 2. Setup Test NPC
echo "\nSetting up test NPC for scheduler...\n";
// Find or create a dummy NPC
$npcId = $db->fetchScalar("SELECT id FROM users WHERE access=3 LIMIT 1");
if (!$npcId) {
    echo "[WARN] No NPC found (access=3). Skipping functional test.\n";
} else {
    // Reset next_tick_at to PAST
    $db->query("UPDATE users SET next_tick_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id=$npcId");
    
    // 3. functional Test: Scheduler
    echo "Running NpcScheduler::processDueNpcs()...\n";
    $processed = NpcScheduler::processDueNpcs(1, 10);
    echo "Processed count: $processed\n";
    
    // Check if next_tick_at pushed to future
    $newTick = $db->fetchScalar("SELECT next_tick_at FROM users WHERE id=$npcId");
    if (strtotime($newTick) > time()) {
        echo "[OK] NPC next_tick_at updated to future: $newTick\n";
    } else {
        echo "[FAIL] NPC next_tick_at NOT updated (still in past/present): $newTick\n";
        $errors++;
    }
}

// 4. Test War Village Manager
if ($npcId) {
    echo "\nTesting War Village Manager...\n";
    $warVillage = NpcWarVillageManager::updateWarVillage($npcId);
    if ($warVillage) {
        echo "[OK] War Village selected: $warVillage\n";
    } else {
        echo "[INFO] No villages found for NPC, so no War Village selected (Expected if new NPC)\n";
    }
}

echo "\nVerification Complete. Errors: $errors\n";
exit($errors === 0 ? 0 : 1);
