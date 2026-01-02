<?php
define("COL_TAG", "");
define('GLOBAL_CONFIG_FILE', __DIR__ . '/config.php');

// Auto-detect CONNECTION_FILE
$serversDir = __DIR__ . '/servers';
$connectionFile = null;
if (is_dir($serversDir)) {
    $scanned = scandir($serversDir);
    foreach ($scanned as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        $candidate = $serversDir . '/' . $dir . '/include/connection.php';
        if (file_exists($candidate)) {
            $connectionFile = $candidate;
            echo "Found Connection File: $connectionFile\n";
            break;
        }
    }
}
// Fallback if no servers found (e.g. fresh install that failed)
if (!$connectionFile) {
    echo "ERROR: No connection.php found in servers/ directory!\n";
    echo "This means installation failed before server directory creation.\n";
    die("Cannot proceed without DB connection.\n");
}

if (!defined('IS_DEV')) define('IS_DEV', true);
if (!defined('ROOT_PATH')) define('ROOT_PATH', __DIR__ . '/');
if (!defined('EP_HOST')) define('EP_HOST', 'localhost');
if (!defined('EP_PORT')) define('EP_PORT', 3306);
define('CONNECTION_FILE', $connectionFile);
if (!defined('INCLUDE_PATH')) define('INCLUDE_PATH', __DIR__ . '/src/');
require_once(__DIR__ . '/src/config.php');
require_once(__DIR__ . '/src/Core/Config.php');
require_once(__DIR__ . '/src/Core/Database/DB.php');

$db = \Core\Database\DB::getInstance();

echo "\n=== DIAGNOSTIC REPORT ===\n";

// 1. Check Multihunter
echo "\n[Multihunter Status]\n";
$mh = $db->query("SELECT id, name, access, location FROM users WHERE id=5")->fetch_assoc();
if ($mh) {
    echo "User 5 found: " . print_r($mh, true) . "\n";
    $vils = $db->query("SELECT * FROM vdata WHERE owner=5");
    echo "Villages count: " . $vils->num_rows . "\n";
    while($row = $vils->fetch_assoc()) {
        echo " - KID: {$row['kid']}, Name: {$row['name']}, Pop: {$row['pop']}\n";
    }
} else {
    echo "User 5 NOT FOUND!\n";
}

// 2. Map Stats
echo "\n[Map Stats]\n";
$rStats = $db->query("SELECT MIN(r) as min_r, MAX(r) as max_r, COUNT(*) as cnt FROM available_villages")->fetch_assoc();
echo "available_villages R stats: " . print_r($rStats, true) . "\n";

// 3. Edge Availability (FieldType 3)
echo "\n[Edge Stats (R > 5)]\n"; // Reduced from 10 to 5 for small map sensitivity
$edgeCount = $db->fetchScalar("SELECT COUNT(*) FROM available_villages WHERE r > 5 AND fieldtype=3");
echo "FieldType 3 count at R > 5: $edgeCount\n";
$edgeUnoccupied = $db->fetchScalar("SELECT COUNT(*) FROM available_villages WHERE r > 5 AND fieldtype=3 AND occupied=0");
echo "Unoccupied FieldType 3 at R > 5: $edgeUnoccupied\n";

if ($edgeCount == 0) {
    echo "Check what types exist at edge (R > 5):\n";
    $types = $db->query("SELECT fieldtype, COUNT(*) as c FROM available_villages WHERE r > 5 GROUP BY fieldtype");
    while($row = $types->fetch_assoc()) {
        echo "Type {$row['fieldtype']}: {$row['c']}\n";
    }
}

// 4. Logs
echo "\n=== LOGS (Last 20 lines) ===\n";
$logs = ['/tmp/register_error.log', '/tmp/skirmish_debug.log', '/tmp/register_debug.log'];
foreach ($logs as $log) {
    echo "\n--- $log ---\n";
    if (file_exists($log)) {
        $lines = file($log);
        $last = array_slice($lines, -20);
        foreach ($last as $line) echo $line;
    } else {
        echo "File not found.\n";
    }
}

// 5. Schema Audit & Test Insert
echo "\n=== USERS TABLE ===\n";
$uRes = $db->query("SELECT id, name, access FROM users ORDER BY id ASC LIMIT 20");
while($u = $uRes->fetch_assoc()) {
    echo "[{$u['id']}] {$u['name']} (Access: {$u['access']})\n";
}

echo "\n=== VDATA OWNERSHIP ===\n";
// Check who owns the village at (1,0) - assuming KID 1302 or similar
$vRes = $db->query("SELECT kid, owner, name, pop FROM vdata WHERE owner < 10");
while($v = $vRes->fetch_assoc()) {
    echo "KID {$v['kid']}: {$v['name']} (Owner: {$v['owner']}, Pop: {$v['pop']})\n";
}

echo "\n=== END REPORT ===\n";
