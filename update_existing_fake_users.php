<?php
/**
 * Update Existing Fake Users Script
 * 
 * This script updates any existing fake users (access=3) to have
 * the new NPC columns (personality, difficulty, npc_info, etc.)
 * 
 * Run this BEFORE testing if you have existing fake users!
 * 
 * Usage: php update_existing_fake_users.php
 */

require_once __DIR__ . '/src/bootstrap.php';

use Core\NpcConfig;
use Core\Database\DB;

echo "\n=== Update Existing Fake Users Script ===\n\n";

$db = DB::getInstance();

// Step 1: Check if migration columns exist
echo "Step 1: Checking if NPC columns exist...\n";
$columns = $db->query("SHOW COLUMNS FROM users LIKE 'npc_%'")->fetch_all(MYSQLI_ASSOC);

$expectedColumns = ['npc_personality', 'npc_difficulty', 'npc_info', 'last_npc_action'];
$foundColumns = array_column($columns, 'Field');

echo "  Found columns: " . implode(', ', $foundColumns) . "\n";

$missingColumns = array_diff($expectedColumns, $foundColumns);
if (!empty($missingColumns)) {
    echo "  ❌ ERROR: Missing columns: " . implode(', ', $missingColumns) . "\n";
    echo "  Please run migrations/002_add_npc_columns.sql first!\n";
    exit(1);
}

echo "  ✅ All NPC columns exist\n\n";

// Step 2: Find existing fake users
echo "Step 2: Finding existing fake users...\n";
$fakeUsers = $db->query("SELECT id, name, npc_personality, npc_difficulty 
                         FROM users 
                         WHERE access=3")->fetch_all(MYSQLI_ASSOC);

if (empty($fakeUsers)) {
    echo "  ⚠️  No fake users found (access=3)\n";
    echo "  Nothing to update.\n\n";
    exit(0);
}

echo "  Found " . count($fakeUsers) . " fake user(s)\n\n";

// Step 3: Update each fake user
echo "Step 3: Updating fake users with NPC data...\n";

$updated = 0;
$alreadyConfigured = 0;

foreach ($fakeUsers as $user) {
    echo "\n  Processing: {$user['name']} (ID: {$user['id']})\n";
    
    // Check if already configured
    if ($user['npc_personality'] && $user['npc_difficulty']) {
        echo "    ✅ Already configured: {$user['npc_personality']} / {$user['npc_difficulty']}\n";
        $alreadyConfigured++;
        continue;
    }
    
    // Assign random personality and difficulty
    $result = NpcConfig::assignRandom($user['id']);
    
    if ($result) {
        echo "    ✅ Updated:\n";
        echo "       Personality: {$result['personality']}\n";
        echo "       Difficulty: {$result['difficulty']}\n";
        
        // Verify it worked
        $config = NpcConfig::getNpcConfig($user['id']);
        if ($config) {
            $stats = $config['personality_stats'];
            echo "       Military Focus: {$stats['military_focus']}%\n";
            echo "       Economy Focus: {$stats['economy_focus']}%\n";
            echo "       Raid Frequency: {$stats['raid_frequency']}\n";
            $updated++;
        } else {
            echo "    ❌ ERROR: Could not verify configuration\n";
        }
    } else {
        echo "    ❌ ERROR: Failed to update user\n";
    }
}

echo "\n=== Summary ===\n";
echo "Total fake users: " . count($fakeUsers) . "\n";
echo "Already configured: $alreadyConfigured\n";
echo "Newly updated: $updated\n";
echo "Failed: " . (count($fakeUsers) - $alreadyConfigured - $updated) . "\n\n";

// Step 4: Verify all fake users have data
echo "Step 4: Final Verification...\n";
$unconfigured = $db->fetchScalar("SELECT COUNT(*) FROM users 
                                  WHERE access=3 
                                  AND (npc_personality IS NULL OR npc_difficulty IS NULL)");

if ($unconfigured > 0) {
    echo "  ⚠️  WARNING: $unconfigured fake user(s) still not configured!\n";
} else {
    echo "  ✅ All fake users are properly configured!\n";
}

echo "\n";

// Step 5: Display sample configurations
echo "Step 5: Sample NPC Configurations\n";
$samples = $db->query("SELECT id, name, npc_personality, npc_difficulty,
                              JSON_EXTRACT(npc_info, '$.raids_sent') as raids_sent,
                              last_npc_action
                       FROM users 
                       WHERE access=3 
                       LIMIT 5")->fetch_all(MYSQLI_ASSOC);

foreach ($samples as $sample) {
    echo "\n  {$sample['name']} (ID: {$sample['id']})\n";
    echo "    Personality: " . ($sample['npc_personality'] ?? 'NULL') . "\n";
    echo "    Difficulty: " . ($sample['npc_difficulty'] ?? 'NULL') . "\n";
    echo "    Raids Sent: " . ($sample['raids_sent'] ?? 0) . "\n";
    echo "    Last Action: " . ($sample['last_npc_action'] ? date('Y-m-d H:i:s', $sample['last_npc_action']) : 'Never') . "\n";
}

echo "\n✅ Update complete! Your fake users are ready to use the NPC system.\n\n";

echo "Next steps:\n";
echo "1. Run: php test_npc_config.php (test NPC configuration)\n";
echo "2. Run: php test_personality_ai.php (test personality AI)\n";
echo "3. Run: php test_raid_ai.php (test raid AI)\n";
echo "4. Run: php debug_npc_system.php (comprehensive debugging)\n\n";
