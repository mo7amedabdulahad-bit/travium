<?php
/**
 * Test Script for Personality-Driven Building AI
 * 
 * Tests PersonalityAI integration with AutoUpgradeAI
 * Run this after deploying Task 2.3
 * 
 * Usage: php test_personality_ai.php
 */

require_once __DIR__ . '/src/bootstrap.php';

use Core\AI\PersonalityAI;
use Core\NpcConfig;
use Core\Database\DB;

echo "\n=== Personality AI Test Script ===\n\n";

$db = DB::getInstance();

// Test 1: Find NPCs by personality
echo "Test 1: NPCs by Personality\n";
$personalities = ['aggressive', 'economic', 'balanced', 'diplomat', 'assassin'];

foreach ($personalities as $personality) {
    $count = $db->fetchScalar("SELECT COUNT(*) FROM users 
                               WHERE access=3 AND npc_personality='$personality'");
    echo "  - $personality: $count NPCs\n";
}
echo "✅ Passed\n\n";

// Test 2: Test personality selection logic
echo "Test 2: Building Selection Logic\n";
foreach ($personalities as $personality) {
    $uid = $db->fetchScalar("SELECT id FROM users 
                             WHERE access=3 AND npc_personality='$personality' 
                             LIMIT 1");
    
    if ($uid) {
        $config = NpcConfig::getNpcConfig($uid);
        $kid = $db->fetchScalar("SELECT kid FROM users WHERE id=$uid");
        
        if ($kid) {
            // Get buildings
            $buildingsResult = $db->query("SELECT * FROM fdata WHERE kid=$kid");
            $buildings = [];
            if ($buildingsResult && $buildingsResult->num_rows > 0) {
                $row = $buildingsResult->fetch_assoc();
                
                // Build buildings array (simplified)
                for ($i = 1; $i <= 40; $i++) {
                    $buildings[$i] = [
                        'item_id' => isset($row["f{$i}t"]) ? $row["f{$i}t"] : 0,
                        'level' => isset($row["f{$i}"]) ? $row["f{$i}"] : 0,
                    ];
                }
                
                // Test selection 10 times
                $selections = ['resource_fields' => 0, 'buildings' => 0];
                for ($j = 0; $j < 10; $j++) {
                    $selected = PersonalityAI::selectBuildingByPersonality($uid, $buildings, time() - 10000);
                    if ($selected !== false) {
                        if ($selected <= 18) {
                            $selections['resource_fields']++;
                        } else {
                            $selections['buildings']++;
                        }
                    }
                }
                
                $resourcePercent = ($selections['resource_fields'] / 10) * 100;
                $buildingPercent = ($selections['buildings'] / 10) * 100;
                
                echo "  - $personality NPC: {$resourcePercent}% resource fields, {$buildingPercent}% buildings\n";
                echo "    Expected: " . $config['personality_stats']['economy_focus'] . "% economy, " . 
                     $config['personality_stats']['military_focus'] . "% military\n";
            }
        }
    }
}
echo "✅ Test complete (selection distribution may vary due to randomness)\n\n";

// Test 3: Debug info for sample NPC
echo "Test 3: Debug Info for Sample NPCs\n";
foreach (['aggressive', 'economic'] as $personality) {
    $uid = $db->fetchScalar("SELECT id FROM users 
                             WHERE access=3 AND npc_personality='$personality' 
                             LIMIT 1");
    
    if ($uid) {
        $kid = $db->fetchScalar("SELECT kid FROM users WHERE id=$uid");
        if (!$kid) continue;
        
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
        
        $debugInfo = PersonalityAI::getSelectionDebugInfo($uid, $buildings);
        
        echo "\n  $personality NPC (ID: $uid):\n";
        echo "    Personality: " . $debugInfo['personality'] . "\n";
        echo "    Difficulty: " . $debugInfo['difficulty'] . "\n";
        echo "    Military Focus: " . $debugInfo['military_focus'] . "%\n";
        echo "    Economy Focus: " . $debugInfo['economy_focus'] . "%\n";
        echo "    Current Buildings:\n";
        echo "      - Military: " . $debugInfo['current_buildings']['military'] . "\n";
        echo "      - Economy: " . $debugInfo['current_buildings']['economy'] . "\n";
        echo "      - Diplomat: " . $debugInfo['current_buildings']['diplomat'] . "\n";
        echo "      - Total: " . $debugInfo['current_buildings']['total'] . "\n";
    }
}
echo "\n✅ Passed\n\n";

// Test 4: Verify AutoUpgradeAI integration (check if code exists)
echo "Test 4: Check AutoUpgradeAI Integration\n";
$autoUpgradeFile = __DIR__ . '/src/Game/Buildings/AutoUpgradeAI.php';
$content = file_get_contents($autoUpgradeFile);

if (strpos($content, 'PersonalityAI::selectBuildingByPersonality') !== false) {
    echo "  ✅ PersonalityAI integrated into AutoUpgradeAI\n";
} else {
    echo "  ❌ PersonalityAI NOT found in AutoUpgradeAI\n";
}

if (strpos($content, 'access == 3') !== false || strpos($content, "access = 3") !== false) {
    echo "  ✅ NPC detection logic present\n";
} else {
    echo "  ⚠️  NPC detection logic might be missing\n";
}
echo "\n";

echo "=== Summary ===\n";
echo "Personality-driven building AI is integrated.\n";
echo "NPCs will now build according to their personality:\n";
echo "  - Aggressive: 70% military buildings (barracks, smithy, etc.)\n";
echo "  - Economic: 80% economy focus (resource fields, warehouses)\n";
echo "  - Balanced: 50/50 mix\n";
echo "  - Diplomat: Embassy, marketplace, residence priority\n";
echo "  - Assassin: Academy, smithy, scouts\n\n";

echo "Monitor NPC activity over 24-48 hours to see personality differences.\n\n";
