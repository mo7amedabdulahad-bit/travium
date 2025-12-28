#!/usr/bin/php
<?php
/**
 * Test Difficulty-Based Iteration System
 * 
 * Verifies that NPCs get different action counts based on difficulty
 */

require __DIR__ . "/servers/s1/include/env.php";
require SRC_PATH_PROD . "/bootstrap.php";

echo "=== DIFFICULTY-BASED ITERATION TEST ===\n\n";

$db = Core\Database\DB::getInstance();

// Get all NPCs
$npcs = $db->query("SELECT id, name, npc_personality, npc_difficulty FROM users WHERE access=3");

echo "Testing iteration counts for each difficulty level:\n";
echo str_repeat("=", 70) . "\n\n";

$difficultyTests = [];

while ($npc = $npcs->fetch_assoc()) {
    $difficulty = $npc['npc_difficulty'] ?? 'beginner';
    
    echo "NPC: {$npc['name']}\n";
    echo "  Difficulty: " . strtoupper($difficulty) . "\n";
    echo "  Personality: {$npc['npc_personality']}\n";
    
    // Get base iteration count
    $base = \Core\NpcConfig::getIterationCount($difficulty);
    echo "  Base iterations: $base\n";
    
    // Test randomized iterations (10 samples)
    $samples = [];
    for ($i = 0; $i < 10; $i++) {
        $samples[] = \Core\NpcConfig::getRandomizedIterations($difficulty);
    }
    
    $min = min($samples);
    $max = max($samples);
    $avg = round(array_sum($samples) / count($samples), 1);
    
    echo "  Randomized range: $min - $max (avg: $avg)\n";
    echo "  Sample iterations: " . implode(", ", array_slice($samples, 0, 5)) . "...\n";
    echo "\n";
    
    // Store for comparison
    if (!isset($difficultyTests[$difficulty])) {
        $difficultyTests[$difficulty] = [
            'base' => $base,
            'samples' => [],
            'npcs' => []
        ];
    }
    $difficultyTests[$difficulty]['samples'] = array_merge($difficultyTests[$difficulty]['samples'], $samples);
    $difficultyTests[$difficulty]['npcs'][] = $npc['name'];
}

echo str_repeat("=", 70) . "\n";
echo "DIFFICULTY COMPARISON\n";
echo str_repeat("=", 70) . "\n\n";

foreach ($difficultyTests as $difficulty => $data) {
    $avg = round(array_sum($data['samples']) / count($data['samples']), 1);
    $min = min($data['samples']);
    $max = max($data['samples']);
    
    echo strtoupper($difficulty) . ":\n";
    echo "  Base: {$data['base']} iterations\n";
    echo "  Range: $min - $max\n";
    echo "  Average: $avg\n";
    echo "  NPCs: " . implode(", ", $data['npcs']) . "\n";
    echo "\n";
}

echo str_repeat("=", 70) . "\n";
echo "EXPECTED ACTIVITY MULTIPLIER\n";
echo str_repeat("=", 70) . "\n\n";

$beginnerBase = \Core\NpcConfig::getIterationCount('beginner');
echo "Compared to Beginner ($beginnerBase actions):\n";

foreach(['intermediate', 'advanced', 'expert'] as $diff) {
    $base = \Core\NpcConfig::getIterationCount($diff);
    $multiplier = round($base / $beginnerBase, 2);
    echo "  " . ucfirst($diff) . ": {$multiplier}x more active\n";
}

echo "\n✅ Expert NPCs are " . round(\Core\NpcConfig::getIterationCount('expert') / $beginnerBase, 1) . "x more active than Beginners!\n";

echo "\n=== TEST COMPLETE ===\n";
