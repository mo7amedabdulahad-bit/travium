<?php
/**
 * NPC System Diagnostic Script (Standalone)
 * Run: php diagnose_npcs.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials (update if needed)
$dbHost = 'localhost';
$dbUser = 'maindb';
$dbPass = 'E+BtbIW6rlBhFqzRI4L6NAAE';
$dbName = 'maindb';

echo "=== NPC SYSTEM DIAGNOSTIC ===\n\n";

try {
    // Connect to database
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    
    if ($mysqli->connect_error) {
        die("X Database connection failed: " . $mysqli->connect_error . "\n");
    }
    
    echo "OK Connected to database: $dbName\n\n";

    // Check 1: Game started?
    $result = $mysqli->query("SELECT startTime FROM config LIMIT 1");
    $config = $result->fetch_assoc();
    $startTime = $config['startTime'] ?? 0;
    $elapsed = time() - $startTime;
    
    echo "1. Game status:\n";
    echo "   Start time: " . ($startTime > 0 ? date('Y-m-d H:i:s', $startTime) : 'NOT SET') . "\n";
    echo "   Elapsed: " . number_format($elapsed) . " seconds\n";
    echo "   Status: " . ($elapsed > 0 ? "OK RUNNING" : "X NOT STARTED") . "\n\n";

    // Check 2: fakeAccountProcess enabled?
    $result = $mysqli->query("SELECT fakeAccountProcess FROM config LIMIT 1");
    $config = $result->fetch_assoc();
    $fakeProcess = $config['fakeAccountProcess'] ?? 0;
    
    echo "2. fakeAccountProcess flag:\n";
    echo "   Value: " . var_export($fakeProcess, true) . "\n";
    echo "   Status: " . ($fakeProcess ? "OK ENABLED" : "X DISABLED") . "\n\n";

    // Check 3: Count NPCs
    $result = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE access=3");
    $row = $result->fetch_assoc();
    $npcCount = $row['count'] ?? 0;
    
    echo "3. NPC count:\n";
    echo "   Total NPCs (access=3): $npcCount\n";
    echo "   Status: " . ($npcCount > 0 ? "OK NPCs EXIST" : "X NO NPCs") . "\n\n";

    // Check 4: NPC with personality/difficulty
    $result = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE access=3 AND npc_personality IS NOT NULL");
    $row = $result->fetch_assoc();
    $configuredNpcs = $row['count'] ?? 0;
    
    echo "4. Configured NPCs:\n";
    echo "   NPCs with personality: $configuredNpcs\n";
    echo "   Status: " . ($configuredNpcs > 0 ? "OK HAS CONFIG" : "X NOT CONFIGURED") . "\n\n";

    // Check 5: Sample NPC data
    $result = $mysqli->query("SELECT id, name, npc_personality, npc_difficulty, last_npc_action FROM users WHERE access=3 LIMIT 1");
    if ($result && $sampleNpc = $result->fetch_assoc()) {
        echo "5. Sample NPC:\n";
        echo "   ID: {$sampleNpc['id']}\n";
        echo "   Name: {$sampleNpc['name']}\n";
        echo "   Personality: " . ($sampleNpc['npc_personality'] ?? 'NULL') . "\n";
        echo "   Difficulty: " . ($sampleNpc['npc_difficulty'] ?? 'NULL') . "\n";
        echo "   Last action: " . ($sampleNpc['last_npc_action'] ? date('Y-m-d H:i:s', $sampleNpc['last_npc_action']) : 'NEVER') . "\n\n";
    } else {
        echo "5. Sample NPC: None found\n\n";
    }

    // Check 6: Village data for NPCs
    $result = $mysqli->query("SELECT COUNT(*) as count FROM vdata v JOIN users u ON v.owner = u.id WHERE u.access=3");
    $row = $result->fetch_assoc();
    $npcVillages = $row['count'] ?? 0;
    
    echo "6. NPC villages:\n";
    echo "   Total villages owned by NPCs: $npcVillages\n";
    echo "   Status: " . ($npcVillages > 0 ? "OK HAS VILLAGES" : "X NO VILLAGES") . "\n\n";

    // DIAGNOSIS
    echo "=== DIAGNOSIS ===\n";
    
    $issues = [];
    
    if ($elapsed <= 0) {
        $issues[] = "X Game has not started yet (start time in future)";
    }
    
    if (!$fakeProcess) {
        $issues[] = "X fakeAccountProcess is DISABLED in database";
    }
    
    if ($npcCount == 0) {
        $issues[] = "X No NPCs exist in database";
    }
    
    if ($configuredNpcs == 0 && $npcCount > 0) {
        $issues[] = "! NPCs exist but have no personality/difficulty configured";
    }
    
    if ($npcVillages == 0 && $npcCount > 0) {
        $issues[] = "! NPCs exist but have no villages";
    }
    
    if (empty($issues)) {
        echo "OK All checks passed! NPCs should be working.\n\n";
        echo "If NPCs still aren't active:\n";
        echo "1. Check automation is running: systemctl status travium@s1\n";
        echo "2. Check logs: journalctl -u travium@s1 -f\n";
        echo "3. Look for NPC-related errors in logs\n\n";
    } else {
        echo "PROBLEMS FOUND:\n\n";
        foreach ($issues as $issue) {
            echo "  $issue\n";
        }
        echo "\n";
        
        echo "RECOMMENDED FIXES:\n\n";
        
        if (!$fakeProcess) {
            echo "1. Enable fakeAccountProcess:\n";
            echo "   SQL: UPDATE config SET fakeAccountProcess=1;\n";
            echo "   OR: Admin panel > Configuration > Fake Account Process = On\n\n";
        }
        
        if ($npcCount == 0) {
            echo "2. Create NPCs:\n";
            echo "   Go to: https://s1.travium.local/admin.php?action=fakeUser\n\n";
        }
        
        if ($configuredNpcs == 0 && $npcCount > 0) {
            echo "3. Configure existing NPCs:\n";
            echo "   Run: mysql -u maindb -p maindb < migrations/002_add_npc_columns_safe.sql\n\n";
        }
    }

    $mysqli->close();

} catch (Exception $e) {
    echo "X ERROR: " . $e->getMessage() . "\n";
}
