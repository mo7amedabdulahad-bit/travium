\u003c?php
// Quick diagnostic: Check NPC config status
require_once __DIR__ . '/src/init.php';

use Core\\Config;
use Core\\Database\\DB;

echo "=== NPC SYSTEM DIAGNOSTIC ===\n\n";

// Check 1: Game started?
$elapsed = getGameElapsedSeconds();
echo "1. Game elapsed time: " . number_format($elapsed) . " seconds\n";
echo "   Status: " . ($elapsed \u003e 0 ? "✅ RUNNING" : "❌ NOT STARTED") . "\n\n";

// Check 2: fakeAccountProcess enabled?
$db = DB::getInstance();
$fakeProcess = $db-\u003efetchScalar("SELECT fakeAccountProcess FROM config");
echo "2. fakeAccountProcess flag (database): " . var_export($fakeProcess, true) . "\n";
echo "   Status: " . ($fakeProcess ? "✅ ENABLED" : "❌ DISABLED") . "\n\n";

// Check 3: Count NPCs
$npcCount = $db-\u003efetchScalar("SELECT COUNT(*) FROM users WHERE access=3");
echo "3. Total NPCs (access=3): $npcCount\n";
echo "   Status: " . ($npcCount \u003e 0 ? "✅ NPCs EXIST" : "❌ NO NPCs") . "\n\n";

// Check 4: NPC with personality/difficulty
$configuredNpcs = $db-\u003efetchScalar("SELECT COUNT(*) FROM users WHERE access=3 AND npc_personality IS NOT NULL");
echo "4. Configured NPCs (with personality): $configuredNpcs\n";
echo "   Status: " . ($configuredNpcs \u003e 0 ? "✅ HAS CONFIG" : "❌ NOT CONFIGURED") . "\n\n";

// Check 5: Sample NPC data
$sampleNpc = $db-\u003equery("SELECT id, name, npc_personality, npc_difficulty, last_npc_action FROM users WHERE access=3 LIMIT 1")-\u003efetch_assoc();
if ($sampleNpc) {
    echo "5. Sample NPC:\n";
    echo "   ID: {$sampleNpc['id']}\n";
    echo "   Name: {$sampleNpc['name']}\n";
    echo "   Personality: " . ($sampleNpc['npc_personality'] ?? 'NULL') . "\n";
    echo "   Difficulty: " . ($sampleNpc['npc_difficulty'] ?? 'NULL') . "\n";
    echo "   Last action: " . ($sampleNpc['last_npc_action'] ? date('Y-m-d H:i:s', $sampleNpc['last_npc_action']) : 'NEVER') . "\n\n";
}

// Check 6: canRun() test
echo "6. FakeUserModel::canRun() conditions:\n";
echo "   getGameElapsedSeconds() \u003e 0: " . ($elapsed \u003e 0 ? "✅ YES" : "❌ NO ($elapsed)") . "\n";
echo "   fakeAccountProcess enabled: " . ($fakeProcess ? "✅ YES" : "❌ NO") . "\n\n";

// DIAGNOSIS
echo "=== DIAGNOSIS ===\n";
if (!$fakeProcess) {
    echo "❌ **PROBLEM FOUND**: fakeAccountProcess is DISABLED!\n";
    echo "   NPCs cannot run because the config flag is OFF.\n\n";
    echo "   🔧 FIX: Enable it from admin panel:\n";
    echo "      https://s1.travium.local/admin.php?p=config\n";
    echo "      OR run SQL:\n";
    echo "      UPDATE config SET fakeAccountProcess=1;\n\n";
} elseif ($elapsed \u003c= 0) {
    echo "❌ **PROBLEM FOUND**: Game has not started yet!\n";
    echo "   NPCs cannot run before game start time.\n\n";
} elseif ($npcCount == 0) {
    echo "❌ **PROBLEM FOUND**: No NPCs exist!\n";
    echo "   Create NPCs from admin panel first.\n\n";
} elseif ($configuredNpcs == 0) {
    echo "⚠️  **POTENTIAL ISSUE**: NPCs exist but have no personality/difficulty.\n";
    echo "   They may have been created before NPC columns were added.\n";
    echo "   Run migration: migrations/002_add_npc_columns_safe.sql\n\n";
} else {
    echo "✅ All checks passed! NPCs should be working.\n";
    echo "   If they still aren't active, check logs for errors.\n\n";
}
