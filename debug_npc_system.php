<?php
/**
 * Comprehensive NPC System Debugger
 * 
 * This script provides detailed debugging and monitoring for the entire NPC system:
 * - Database schema verification
 * - NPC configuration
 * - Personality AI
 * - Raid AI
 * - Automation integration
 * 
 * Usage: php debug_npc_system.php
 */

// Define IS_DEV constant before bootstrap
define('IS_DEV', true);

require_once __DIR__ . '/src/bootstrap.php';

use Core\NpcConfig;
use Core\AI\PersonalityAI;
use Core\AI\RaidAI;
use Core\Database\DB;

// Color output helpers
function colorize($text, $color) {
    $colors = [
        'red' => "\033[0;31m",
        'green' => "\033[0;32m",
        'yellow' => "\033[0;33m",
        'blue' => "\033[0;34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

function section($title) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo colorize($title, 'blue') . "\n";
    echo str_repeat("=", 80) . "\n\n";
}

function success($msg) { echo colorize("✅ ", 'green') . $msg . "\n"; }
function warning($msg) { echo colorize("⚠️  ", 'yellow') . $msg . "\n"; }
function error($msg) { echo colorize("❌ ", 'red') . $msg . "\n"; }
function info($msg) { echo "ℹ️  $msg\n"; }

$db = DB::getInstance();

echo "\n";
echo colorize("╔════════════════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorize("║           COMPREHENSIVE NPC SYSTEM DEBUGGER & MONITOR                     ║", 'blue') . "\n";
echo colorize("╚════════════════════════════════════════════════════════════════════════════╝", 'blue') . "\n";

// ============================================================================
// SECTION 1: DATABASE SCHEMA CHECK
// ============================================================================
section("1. DATABASE SCHEMA VERIFICATION");

$requiredColumns = [
    'npc_personality' => "ENUM('aggressive','economic','balanced','diplomat','assassin')",
    'npc_difficulty' => "ENUM('beginner','intermediate','advanced','expert')",
    'npc_info' => 'JSON',
    'last_npc_action' => 'INT',
    'goldclub' => 'TINYINT'
];

echo "Checking users table for NPC columns...\n\n";

$allColumnsExist = true;
foreach ($requiredColumns as $column => $expectedType) {
    $result = $db->query("SHOW COLUMNS FROM users LIKE '$column'");
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        success("Column '$column' exists ({$row['Type']})");
    } else {
        error("Column '$column' MISSING!");
        $allColumnsExist = false;
    }
}

if (!$allColumnsExist) {
    error("❗ CRITICAL: Missing NPC columns! Run migrations/002_add_npc_columns.sql");
    exit(1);
}

// Check indexes
echo "\nChecking indexes...\n\n";
$indexes = $db->query("SHOW INDEX FROM users WHERE Key_name IN ('npc_processing', 'npc_personality', 'npc_difficulty')")->fetch_all(MYSQLI_ASSOC);

if (count($indexes) >= 3) {
    success("NPC indexes exist (" . count($indexes) . " found)");
} else {
    warning("Some NPC indexes may be missing (" . count($indexes) . "/3 found)");
}

// ============================================================================
// SECTION 2: FAKE USERS / NPCS OVERVIEW
// ============================================================================
section("2. FAKE USERS / NPCs OVERVIEW");

$totalNpcs = $db->fetchScalar("SELECT COUNT(*) FROM users WHERE access=3");
echo "Total NPCs (access=3): " . colorize($totalNpcs, 'green') . "\n\n";

if ($totalNpcs == 0) {
    warning("No NPCs found! Create fake users first.");
} else {
    // NPCs by personality
    echo "Distribution by Personality:\n";
    $byPersonality = $db->query("SELECT npc_personality, COUNT(*) as count 
                                 FROM users 
                                 WHERE access=3 
                                 GROUP BY npc_personality")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($byPersonality as $row) {
        $personality = $row['npc_personality'] ?? 'NULL';
        $count = $row['count'];
        $bar = str_repeat("█", min(50, $count * 5));
        echo sprintf("  %-12s: %2d %s\n", $personality, $count, $bar);
    }
    
    // NPCs by difficulty
    echo "\nDistribution by Difficulty:\n";
    $byDifficulty = $db->query("SELECT npc_difficulty, COUNT(*) as count 
                                FROM users 
                                WHERE access=3 
                                GROUP BY npc_difficulty")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($byDifficulty as $row) {
        $difficulty = $row['npc_difficulty'] ?? 'NULL';
        $count = $row['count'];
        $iterations = NpcConfig::getIterationCount($difficulty);
        echo sprintf("  %-14s: %2d NPCs (%-2d iterations/cycle)\n", $difficulty, $count, $iterations);
    }
    
    // Unconfigured NPCs
    $unconfigured = $db->fetchScalar("SELECT COUNT(*) FROM users 
                                      WHERE access=3 
                                      AND (npc_personality IS NULL OR npc_difficulty IS NULL)");
    
    if ($unconfigured > 0) {
        echo "\n";
        error("$unconfigured NPC(s) not configured! Run: php update_existing_fake_users.php");
    } else {
        echo "\n";
        success("All NPCs are properly configured!");
    }
}

// ============================================================================
// SECTION 3: NPC CONFIGURATION DETAILS
// ============================================================================
section("3. DETAILED NPC CONFIGURATIONS");

if ($totalNpcs > 0) {
    $npcs = $db->query("SELECT id, name, npc_personality, npc_difficulty, npc_info, 
                               last_npc_action, goldclub, kid
                        FROM users 
                        WHERE access=3 
                        LIMIT 10")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($npcs as $npc) {
        echo colorize("NPC: {$npc['name']} (ID: {$npc['id']})", 'yellow') . "\n";
        echo str_repeat("-", 80) . "\n";
        
        $config = NpcConfig::getNpcConfig($npc['id']);
        
        if ($config) {
            $stats = $config['personality_stats'];
            $freq = NpcConfig::getRaidFrequency($config['npc_personality']);
            
            echo sprintf("  Personality:     %s\n", colorize($config['npc_personality'], 'green'));
            echo sprintf("  Difficulty:      %s (%d iterations/cycle)\n", 
                        $config['npc_difficulty'], $config['iterations']);
            echo sprintf("  Military Focus:  %d%%\n", $stats['military_focus']);
            echo sprintf("  Economy Focus:   %d%%\n", $stats['economy_focus']);
            echo sprintf("  Raid Frequency:  Every %.1f-%.1f hours\n", 
                        $freq['min']/3600, $freq['max']/3600);
            echo sprintf("  Alliance Tendency: %d%%\n", $stats['alliance_tendency']);
            
            if ($config['npc_info']) {
                echo sprintf("  Raids Sent:      %d\n", $config['npc_info']['raids_sent'] ?? 0);
                echo sprintf("  Buildings Built: %d\n", $config['npc_info']['total_buildings_built'] ?? 0);
                echo sprintf("  Troops Trained:  %d\n", $config['npc_info']['total_troops_trained'] ?? 0);
                
                $lastRaid = $config['npc_info']['last_raid_time'] ?? 0;
                if ($lastRaid > 0) {
                    $hoursSince = round((time() - $lastRaid) / 3600, 1);
                    echo sprintf("  Last Raid:       %s (%s hours ago)\n", 
                                date('Y-m-d H:i:s', $lastRaid), $hoursSince);
                } else {
                    echo "  Last Raid:       Never\n";
                }
            }
            
            $lastAction = $config['last_npc_action'] ?? 0;
            if ($lastAction > 0) {
                echo sprintf("  Last Action:     %s\n", date('Y-m-d H:i:s', $lastAction));
            } else {
                echo "  Last Action:     Never processed\n";
            }
            
            echo sprintf("  Gold Club:       %s\n", $config['goldclub'] ? 'Yes' : 'No');
            echo sprintf("  Village ID:      %d\n", $npc['kid']);
            
        } else {
            error("  Configuration NOT found! Run update_existing_fake_users.php");
        }
        
        echo "\n";
    }
    
    if ($totalNpcs > 10) {
        info("Showing first 10 of $totalNpcs NPCs");
        echo "\n";
    }
}

// ============================================================================
// SECTION 4: RAID AI STATUS
// ============================================================================
section("4. RAID AI STATUS & STATISTICS");

if ($totalNpcs > 0) {
    echo "Raid Activity Summary:\n\n";
    
    // Total raids sent
    $totalRaids = $db->fetchScalar("SELECT SUM(JSON_EXTRACT(npc_info, '$.raids_sent')) 
                                    FROM users WHERE access=3");
    echo sprintf("  Total Raids Sent (All Time): %d\n", $totalRaids ?? 0);
    
    // Ongoing raids
    $ongoingRaids = $db->fetchScalar("SELECT COUNT(*) FROM movement m
                                      JOIN vdata v ON m.kid = v.kid
                                      JOIN users u ON v.owner = u.id
                                      WHERE u.access=3 AND m.attack_type=4");
    echo sprintf("  Ongoing Raids:                %d\n", $ongoingRaids);
    
    // Raids by personality
    echo "\n  Raids by Personality:\n";
    $raidsByPersonality = $db->query("SELECT npc_personality, 
                                             SUM(JSON_EXTRACT(npc_info, '$.raids_sent')) as total_raids
                                      FROM users 
                                      WHERE access=3 
                                      GROUP BY npc_personality")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($raidsByPersonality as $row) {
        $personality = $row['npc_personality'] ?? 'NULL';
        $raids = $row['total_raids'] ?? 0;
        echo sprintf("    %-12s: %d raids\n", $personality, $raids);
    }
    
    // NPCs that should raid now
    echo "\n  NPCs Ready to Raid:\n";
    $readyCount = 0;
    
    $allNpcs = $db->query("SELECT id, name, npc_personality FROM users WHERE access=3")->fetch_all(MYSQLI_ASSOC);
    foreach ($allNpcs as $npc) {
        if (RaidAI::shouldRaid($npc['id'])) {
            echo sprintf("    - %s (%s)\n", $npc['name'], $npc['npc_personality']);
            $readyCount++;
        }
    }
    
    if ($readyCount == 0) {
        echo "    (None - all on cooldown)\n";
    }
    
    // Recent raids
    echo "\n  Recent Raids (Last 24 hours):\n";
    $recentRaids = $db->query("SELECT m.id, u.name as attacker, u.npc_personality,
                                      v1.name as from_village, v2.name as to_village,
                                      m.start_time, m.end_time
                               FROM movement m
                               JOIN vdata v1 ON m.kid = v1.kid
                               JOIN vdata v2 ON m.to_kid = v2.kid
                               JOIN users u ON v1.owner =u.id
                               WHERE u.access=3 AND m.attack_type=4
                                 AND m.start_time > (UNIX_TIMESTAMP() - 86400) * 1000
                               ORDER BY m.start_time DESC
                               LIMIT 10")->fetch_all(MYSQLI_ASSOC);
    
    if (!empty($recentRaids)) {
        foreach ($recentRaids as $raid) {
            $startTime = date('H:i:s', $raid['start_time'] / 1000);
            $timeLeft = ($raid['end_time'] / 1000) - time();
            $status = $timeLeft > 0 ? "Arriving in " . round($timeLeft / 60) . " min" : "Completed";
            
            echo sprintf("    [%s] %s (%s) → %s [%s]\n",
                        $startTime,
                        $raid['attacker'],
                        $raid['npc_personality'],
                        $raid['to_village'],
                        $status);
        }
    } else {
        echo "    No raids in last 24 hours\n";
    }
}

// ============================================================================
// SECTION 5: BUILDING AI STATUS
// ============================================================================
section("5. BUILDING AI & PERSONALITY VERIFICATION");

if ($totalNpcs > 0) {
    echo "Checking if NPCs build according to personality...\n\n";
    
    $sampleNpcs = $db->query("SELECT u.id, u.name, u.npc_personality, u.kid
                              FROM users u WHERE u.access=3 LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    
    foreach ($sampleNpcs as $npc) {
        $kid = $npc['kid'];
        
        // Get buildings
        $buildingsResult = $db->query("SELECT * FROM fdata WHERE kid=$kid");
        if (!$buildingsResult || $buildingsResult->num_rows == 0) continue;
        
        $row = $buildingsResult->fetch_assoc();
        $buildings = [];
        for ($i = 1; $i <= 40; $i++) {
            $buildings[$i] = [
                'item_id' => isset($row["f{$i}t"]) ? $row["f{$i}t"] : 0,
                'level' => isset($row["f{$i}"]) ? $row["f{$i}"] : 0,
            ];
        }
        
        $debugInfo = PersonalityAI::getSelectionDebugInfo($npc['id'], $buildings);
        
        echo colorize($npc['name'] . " ({$npc['npc_personality']})", 'yellow') . "\n";
        echo sprintf("  Military buildings: %d\n", $debugInfo['current_buildings']['military']);
        echo sprintf("  Economy buildings:  %d\n", $debugInfo['current_buildings']['economy']);
        echo sprintf("  Total buildings:    %d\n", $debugInfo['current_buildings']['total']);
        
        $expectedMilitary = $debugInfo['military_focus'];
        $actualMilitary = $debugInfo['current_buildings']['total'] > 0 ?
                         round(($debugInfo['current_buildings']['military'] / $debugInfo['current_buildings']['total']) * 100) : 0;
        
        echo sprintf("  Expected military:  %d%%\n", $expectedMilitary);
        echo sprintf("  Actual military:    %d%%\n", $actualMilitary);
        
        if (abs($expectedMilitary - $actualMilitary) <= 20) {
            success("  Building pattern matches personality!");
        } else {
            warning("  Building pattern may not match yet (needs more time)");
        }
        
        echo "\n";
    }
}

// ============================================================================
// SECTION 6: CODE INTEGRATION CHECK
// ============================================================================
section("6. CODE INTEGRATION VERIFICATION");

echo "Checking file existence and integration...\n\n";

$files = [
    'src/Core/NpcConfig.php' => 'NPC Configuration System',
    'src/Core/AI/PersonalityAI.php' => 'Personality-Driven Building AI',
    'src/Core/AI/RaidAI.php' => 'Raid AI System',
];

foreach ($files as $file => $description) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        success("$description");
        echo "       → $file\n";
    } else {
        error("$description MISSING!");
        echo "       → $file\n";
    }
}

echo "\nChecking code integration...\n\n";

// Check AutoUpgradeAI integration
$autoUpgradeFile = __DIR__ . '/src/Game/Buildings/AutoUpgradeAI.php';
$content = file_get_contents($autoUpgradeFile);

if (strpos($content, 'PersonalityAI::selectBuildingByPersonality') !== false) {
    success("PersonalityAI integrated into AutoUpgradeAI");
} else {
    error("PersonalityAI NOT integrated into AutoUpgradeAI");
}

// Check AI.php integration
$aiFile = __DIR__ . '/src/Core/AI.php';
$content = file_get_contents($aiFile);

if (strpos($content, 'RaidAI::processRaid') !== false) {
    success("RaidAI integrated into AI decision loop");
} else {
    error("RaidAI NOT integrated into AI decision loop");
}

if (strpos($content, 'isNpc') !==false || strpos($content, '$isNpc') !== false) {
    success("NPC detection logic present in AI");
} else {
    error("NPC detection logic missing in AI");
}

// Check RegisterModel integration
$registerFile = __DIR__ . '/src/Model/RegisterModel.php';
$content = file_get_contents($registerFile);

if (strpos($content, 'NpcConfig::assignRandom') !== false) {
    success("NPC auto-configuration in RegisterModel");
} else {
    error("NPC auto-configuration NOT in RegisterModel");
}

// ============================================================================
// SECTION 7: RECOMMENDATIONS
// ============================================================================
section("7. RECOMMENDATIONS & NEXT STEPS");

if ($totalNpcs == 0) {
    warning("Create fake users to test the NPC system!");
    echo "  Command: Access admin panel → Create Fake Users\n";
    echo "  Or use: RegisterModel::addFakeUser()\n\n";
}

if ($unconfigured > 0) {
    warning("Update existing fake users:");
    echo "  Command: php update_existing_fake_users.php\n\n";
}

if ($totalRaids == 0 && $totalNpcs > 0) {
    info("No raids sent yet. NPCs will start raiding after automation processes them.");
    echo "  Monitor with: watch -n 5 'mysql -e \"SELECT COUNT(*) FROM movement WHERE attack_type=4\"'\n\n";
}

echo "Recommended monitoring:\n";
echo "  1. Watch automation logs: tail -f /var/log/travium/automation.log\n";
echo "  2. Monitor raids: php test_raid_ai.php\n";
echo "  3. Check NPC activity: SELECT name, last_npc_action FROM users WHERE access=3\n";
echo "  4. Re-run this debugger periodically: php debug_npc_system.php\n\n";

success("Debug complete!");

echo "\n";
echo colorize("╔════════════════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorize("║                          END OF DEBUG REPORT                               ║", 'blue') . "\n";
echo colorize("╚════════════════════════════════════════════════════════════════════════════╝", 'blue') . "\n";
echo "\n";
