<?php
/**
 * Test NPC Processing Manually
 * This bypasses the automation system and calls handleFakeUsers() directly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Change to server directory
chdir('/home/travium/htdocs/servers/s1');

require_once __DIR__ . '/servers/s1/include/bootstrap.php';

use Model\FakeUserModel;
use Core\Database\DB;

echo "=== MANUAL NPC PROCESSING TEST ===\n\n";

try {
    echo "1. Creating FakeUserModel instance...\n";
    $fakeModel = new FakeUserModel();
    
    echo "2. Checking canRun() conditions...\n";
    $elapsed = getGameElapsedSeconds();
    echo "   - Game elapsed: $elapsed seconds\n";
    
    $db = DB::getInstance();
    $fakeProcess = $db->fetchScalar("SELECT fakeAccountProcess FROM config");
    echo "   - fakeAccountProcess: " . var_export($fakeProcess, true) . "\n";
    
    if ($elapsed <= 0) {
        echo "   X canRun() will FAIL: Game not started\n";
        exit(1);
    }
    
    if (!$fakeProcess) {
        echo "   X canRun() will FAIL: fakeAccountProcess disabled\n";
        exit(1);
    }
    
    echo "   OK canRun() should PASS\n\n";
    
    echo "3. Calling handleFakeUsers()...\n";
    $fakeModel->handleFakeUsers();
    
    echo "\n4. Checking if NPCs were updated...\n";
    $result = $db->query("SELECT id, name, last_npc_action FROM users WHERE access=3 LIMIT 3");
    while ($row = $result->fetch_assoc()) {
        $lastAction = $row['last_npc_action'] ? date('Y-m-d H:i:s', $row['last_npc_action']) : 'NEVER';
        echo "   NPC #{$row['id']} ({$row['name']}): Last action = $lastAction\n";
    }
    
    echo "\nOK Test complete!\n";
    
} catch (Exception $e) {
    echo "\nX ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
