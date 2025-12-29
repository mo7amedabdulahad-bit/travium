<?php
/**
 * Simple NPC Processing Test (Direct SQL + Logging)
 * Tests if AI.php exists and can be called
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$mysqli = new mysqli('localhost', 'maindb', 'E+BtbIW6rlBhFqzRI4L6NAAE', 'maindb');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== SIMPLE NPC TEST ===\n\n";

// Get one NPC village
$result = $mysqli->query("
    SELECT v.kid, v.owner, u.name, u.npc_difficulty 
    FROM vdata v 
    JOIN users u ON v.owner = u.id 
    WHERE u.access=3 
    LIMIT 1
");

if (!$result || $result->num_rows == 0) {
    die("X No NPC villages found\n");
}

$village = $result->fetch_assoc();
echo "Found NPC: ID={$village['owner']}, Name={$village['name']}, Village={$village['kid']}\n";
echo "Difficulty: {$village['npc_difficulty']}\n\n";

// Check if AI.php exists
$aiPath = '/home/travium/htdocs/src/Core/AI.php';
if (!file_exists($aiPath)) {
    die("X AI.php not found at: $aiPath\n");
}
echo "OK AI.php exists\n";

// Check if AI class can be loaded
require_once '/home/travium/htdocs/src/bootstrap.php';

echo "OK Bootstrap loaded\n";

try {
    echo "\nCalling AI::doSomethingRandom() for village {$village['kid']}...\n";
    
    $iterations = 5; // Small test
    \Core\AI::doSomethingRandom($village['kid'], $iterations);
    
    echo "OK Method executed without errors\n";
    
    // Check if village lastVillageCheck was updated
    $result = $mysqli->query("SELECT lastVillageCheck FROM vdata WHERE kid={$village['kid']}");
    $row = $result->fetch_assoc();
    echo "Village lastVillageCheck: " . date('Y-m-d H:i:s', $row['lastVillageCheck']) . "\n";
    
} catch (Exception $e) {
    echo "X ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

$mysqli->close();
