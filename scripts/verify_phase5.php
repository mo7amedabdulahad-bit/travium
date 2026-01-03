<?php

// Phase 5 Verification Script

define('ROOT_PATH', dirname(__DIR__) . '/');
define('GLOBAL_CONFIG_FILE', ROOT_PATH . 'config.php');
define('CONNECTION_FILE', ROOT_PATH . 'servers/s1/include/connection.php');

echo "debug: loading bootstrap...\n";
require_once ROOT_PATH . 'src/bootstrap.php';
echo "debug: bootstrap loaded.\n\n";

echo "Phase 5 Verification Started...\n";

$errors = 0;

// Test 1: Check if new classes exist
$classes = [
    'Core\NpcWorldEvents',
    'Core\NpcAllianceCoordination',
    'Core\NpcRetaliationManager',
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "[OK] Class $class exists.\n";
    } else {
        echo "[ERROR] Class $class not found.\n";
        $errors++;
    }
}

// Test 2: Check database changes
$db = \Core\Database\DB::getInstance();

// Check npc_memory_json column
$result = $db->query("SHOW COLUMNS FROM users LIKE 'npc_memory_json'");
if ($result && $result->num_rows > 0) {
    echo "[OK] Column users.npc_memory_json exists.\n";
} else {
    echo "[ERROR] Column users.npc_memory_json not found.\n";
    $errors++;
}

// Check event processing index
$result = $db->query("SHOW INDEX FROM npc_world_events WHERE Key_name='idx_events_processing'");
if ($result && $result->num_rows > 0) {
    echo "[OK] Index idx_events_processing exists.\n";
} else {
    echo "[WARN] Index idx_events_processing not found (may affect performance).\n";
}

// Test 3: Test event recording
echo "\nTesting Event Recording...\n";
try {
    \Core\NpcWorldEvents::recordAllianceAttacked(1, 999, 1, 100);
    
    // Check if event was recorded
    $eventCount = (int)$db->fetchScalar("SELECT COUNT(*) FROM npc_world_events WHERE target_alliance_id=999 AND processed_at IS NULL");
    
    if ($eventCount > 0) {
        echo "[OK] Event recording works.\n";
        
        // Clean up test event
        $db->query("DELETE FROM npc_world_events WHERE target_alliance_id=999");
    } else {
        echo "[ERROR] Event not recorded.\n";
        $errors++;
    }
} catch (Exception $e) {
    echo "[ERROR] Event recording failed: " . $e->getMessage() . "\n";
    $errors++;
}

// Test 4: Test retaliation manager
echo "\nTesting Retaliation Manager...\n";
try {
    // Find an NPC
    $npc = $db->query("SELECT id FROM users WHERE access=3 LIMIT 1")->fetch_assoc();
    
    if ($npc) {
        $npcId = (int)$npc['id'];
        
        // Add test retaliation target
        \Core\NpcRetaliationManager::addRetaliationTarget($npcId, 999, 1.5);
        
        // Retrieve targets
        $targets = \Core\NpcRetaliationManager::getRetaliationTargets($npcId);
        
        if (!empty($targets) && $targets[0]['user_id'] == 999) {
            echo "[OK] Retaliation manager works.\n";
            
            // Clean up
            $db->query("UPDATE users SET npc_memory_json=NULL WHERE id=$npcId");
        } else {
            echo "[ERROR] Retaliation manager not working.\n";
            $errors++;
        }
    } else {
        echo "[SKIP] No NPCs found for testing.\n";
    }
} catch (Exception $e) {
    echo "[ERROR] Retaliation manager test failed: " . $e->getMessage() . "\n";
    $errors++;
}

// Test 5: Check scheduler integration
echo "\nChecking Scheduler Integration...\n";
$reflection = new ReflectionClass('Core\NpcScheduler');
if ($reflection->hasMethod('processWorldEvents')) {
    echo "[OK] NpcScheduler::processWorldEvents exists.\n";
} else {
    echo "[ERROR] NpcScheduler::processWorldEvents not found.\n";
    $errors++;
}

if ($reflection->hasMethod('handleEvent')) {
    echo "[OK] NpcScheduler::handleEvent exists.\n";
} else {
    echo "[ERROR] NpcScheduler::handleEvent not found.\n";
    $errors++;
}

// Test 6: Check retaliation priority in script engine
$reflection = new ReflectionClass('Core\NpcScriptEngine');
if ($reflection->hasMethod('selectRetaliationTarget')) {
    echo "[OK] NpcScriptEngine::selectRetaliationTarget exists.\n";
} else {
    echo "[ERROR] NpcScriptEngine::selectRetaliationTarget not found.\n";
    $errors++;
}

echo "\n=== Phase 5 Verification Complete ===\n";
echo "Errors: $errors\n";

if ($errors == 0) {
    echo "✅ All systems operational!\n";
    echo "\n📋 Manual Steps Required:\n";
    echo "1. Add combat hook to battle resolution code (see PHASE5_COMBAT_HOOK.md)\n";
    echo "2. Attack an NPC village to test event recording\n";
    echo "3. Wait 30 seconds for event processing\n";
    echo "4. Check for alliance reinforcements in movement table\n";
}

exit($errors > 0 ? 1 : 0);
