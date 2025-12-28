#!/usr/bin/php
<?php
/**
 * Test Alliance AI System
 * 
 * Tests NPC alliance joining and creation behavior
 */

require __DIR__ . "/servers/s1/include/env.php";
require SRC_PATH_PROD . "/bootstrap.php";

echo "=== ALLIANCE AI TEST ===\n\n";

$db = Core\Database\DB::getInstance();

// Get all NPCs
$npcs = $db->query("SELECT id, name, npc_personality, npc_difficulty, aid FROM users WHERE access=3");

echo "Current NPC Alliance Status:\n";
echo str_repeat("=", 70) . "\n\n";

$npcData = [];

while ($npc = $npcs->fetch_assoc()) {
    $stats = \Core\AI\AllianceAI::getAllianceStats($npc['id']);
    
    echo "NPC: {$npc['name']}\n";
    echo "  Personality: {$stats['personality']} (Alliance tendency: {$stats['alliance_tendency']})\n";
    echo "  Current Alliance: {$stats['current_alliance']}\n";
    echo "  Last Check: {$stats['last_check']}\n";
    echo "  Time Since: {$stats['time_since_check']}\n";
    echo "  Will Join? " . ($stats['will_join'] === true ? 'YES' : ($stats['will_join'] === false ? 'NO' : $stats['will_join'])) . "\n";
    echo "\n";
    
    $npcData[] = [
        'id' => $npc['id'],
        'name' => $npc['name'],
        'personality' => $stats['personality'],
        'current_aid' => $npc['aid']
    ];
}

echo str_repeat("=", 70) . "\n";
echo "EXISTING ALLIANCES\n";
echo str_repeat("=", 70) . "\n\n";

$alliances = $db->query("SELECT a.id, a.name, a.tag, a.leader, COUNT(u.id) as members, SUM(u.pop) as total_pop
                         FROM alidata a
                         LEFT JOIN users u ON u.aid = a.id
                         WHERE a.id > 0
                         GROUP BY a.id
                         ORDER BY total_pop DESC
                         LIMIT 10");

if ($alliances->num_rows > 0) {
    echo "Top 10 Alliances:\n";
    while ($alliance = $alliances->fetch_assoc()) {
        $leaderName = $db->fetchScalar("SELECT name FROM users WHERE id={$alliance['leader']}");
        echo "  [{$alliance['tag']}] {$alliance['name']} - {$alliance['members']} members (Leader: $leaderName)\n";
    }
} else {
    echo "No alliances found in database.\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "TESTING ALLIANCE PROCESSING\n";
echo str_repeat("=", 70) . "\n\n";

$beforeLog = file_exists('/home/travium/logs/npc_activity.log') 
    ? count(file('/home/travium/logs/npc_activity.log')) 
    : 0;

foreach ($npcData as $npc) {
    if ($npc['current_aid'] > 0) {
        echo "{$npc['name']}: Already in alliance (skipping)\n";
        continue;
    }
    
    echo "{$npc['name']}: Testing alliance join/create...\n";
    
    // Reset cooldown to allow immediate test
    $db->query("UPDATE users SET npc_info = JSON_SET(npc_info, '$.last_alliance_check', 1) WHERE id={$npc['id']}");
    
    // Process alliance
    $result = \Core\AI\AllianceAI::processAlliance($npc['id']);
    
    // Check new status
    $newAid = $db->fetchScalar("SELECT aid FROM users WHERE id={$npc['id']}");
    
    if ($newAid > 0) {
        $alliance = $db->query("SELECT name, tag FROM alidata WHERE id=$newAid")->fetch_assoc();
        echo "  ✅ Joined/Created: {$alliance['name']} [{$alliance['tag']}]\n";
    } else {
        echo "  ❌ Did not join/create (personality decided against it)\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "LOG ENTRIES\n";
echo str_repeat("=", 70) . "\n\n";

if (file_exists('/home/travium/logs/npc_activity.log')) {
    $allLogs = file('/home/travium/logs/npc_activity.log');
    $allianceLogs = array_filter($allLogs, function($log) {
        return strpos($log, 'ALLIANCE') !== false;
    });
    
    if (!empty($allianceLogs)) {
        echo "Alliance-related log entries:\n";
        foreach (array_slice($allianceLogs, -10) as $log) {
            echo "  " . trim($log) . "\n";
        }
    } else {
        echo "No alliance log entries found.\n";
    }
} else {
    echo "No log file found.\n";
}

echo "\n=== TEST COMPLETE ===\n";
