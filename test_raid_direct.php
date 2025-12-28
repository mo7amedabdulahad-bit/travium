#!/usr/bin/php
<?php
// Use the same setup as the engine
require __DIR__ . "/servers/s1/include/env.php";
require SRC_PATH_PROD . "/bootstrap.php";

echo "=== NPC RAID DIRECT TEST ===\n\n";

$npcId = 5; // Ma7ame7o
$villageId = 411;

echo "Testing NPC: $npcId\n";
echo "Village: $villageId\n\n";

// Test 1: Check NPC config
echo "1. Checking NPC config...\n";
$config = \Core\NpcConfig::getNpcConfig($npcId);
if ($config) {
    echo "  ✅ Personality: {$config['npc_personality']}\n";
    echo "  ✅ Difficulty: {$config['npc_difficulty']}\n";
    echo "  ✅ Last raid: " . ($config['npc_info']['last_raid_time'] ?? 'never') . "\n";
} else {
    echo "  ❌ No NPC config found!\n";
    exit(1);
}

// Test 2: Check raid frequency
echo "\n2. Checking raid frequency...\n";
$freq = \Core\NpcConfig::getRaidFrequency($config['npc_personality']);
echo "  Min: {$freq['min']} seconds (" . round($freq['min']/60, 1) . " min)\n";
echo "  Max: {$freq['max']} seconds (" . round($freq['max']/60, 1) . " min)\n";

// Test 3: Check if should raid
echo "\n3. Checking if should raid...\n";
$shouldRaid = \Core\AI\RaidAI::shouldRaid($npcId);
echo "  " . ($shouldRaid ? "✅ YES" : "❌ NO") . "\n";

if (!$shouldRaid) {
    $lastRaid = $config['npc_info']['last_raid_time'] ?? 0;
    $timeSince = time() - $lastRaid;
    echo "  Last raid was $timeSince seconds ago\n";
    echo "  Needs at least {$freq['min']} seconds\n";
}

// Test 4: Find targets
echo "\n4. Finding targets...\n";
$targets = \Core\AI\RaidAI::findTargets($villageId, 20);
if ($targets) {
    echo "  ✅ Found " . count($targets) . " targets\n";
    echo "  Top 3 targets:\n";
    foreach (array_slice($targets, 0, 3) as $i => $t) {
        echo "    " . ($i+1) . ". {$t['name']} (kid:{$t['kid']}, dist:{$t['distance']}, score:{$t['score']})\n";
    }
} else {
    echo "  ❌ No targets found!\n";
}

// Test 5: Try to send raid
echo "\n5. Attempting to send raid...\n";
$result = \Core\AI\RaidAI::processRaid($npcId, $villageId);
echo "  Result: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . "\n";

// Test 6: Check logs
echo "\n6. Checking logs...\n";
$logFile = '/home/travium/logs/npc_activity.log';
if (file_exists($logFile)) {
    $logs = file($logFile);
    $recent = array_slice($logs, -5);
    if (!empty($recent)) {
        echo "  Last 5 log entries:\n";
        foreach ($recent as $log) {
            echo "    " . trim($log) . "\n";
        }
    } else {
        echo "  ❌ Log file empty\n";
    }
} else {
    echo "  ❌ Log file doesn't exist\n";
}

// Test 7: Get raid stats
echo "\n7. Raid statistics:\n";
$stats = \Core\AI\RaidAI::getRaidStats($npcId);
print_r($stats);

echo "\n=== TEST COMPLETE ===\n";
