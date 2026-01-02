<?php
define("COL_TAG", "");
define('GLOBAL_CONFIG_FILE', __DIR__ . '/config.php');
require_once(__DIR__ . '/src/config.php');
require_once(__DIR__ . '/src/Core/Database/DB.php');

$db = \Core\Database\DB::getInstance();

echo "=== DIAGNOSTIC REPORT ===\n";

// 1. Check Multihunter
echo "\n[Multihunter Status]\n";
$mh = $db->query("SELECT id, username, access, location FROM users WHERE id=5")->fetch_assoc();
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

// 2. Check (1,0)
echo "\n[Coordinate (1,0) Check]\n";
// We need to calculate KID or find by x,y if wdata has x,y
// Usually wdata has x,y
$k10 = $db->query("SELECT id, x, y, occupied, fieldtype FROM wdata WHERE x=1 AND y=0")->fetch_assoc();
if ($k10) {
    echo "(1,0) Found: ID={$k10['id']} Occupied={$k10['occupied']} FieldType={$k10['fieldtype']}\n";
    // Check available_villages for this KID
    $av = $db->query("SELECT * FROM available_villages WHERE kid={$k10['id']}")->fetch_assoc();
    echo "available_villages entry: " . ($av ? print_r($av, true) : "NONE") . "\n";
} else {
    echo "(1,0) NOT FOUND in wdata!\n";
}

// 3. Check Map Stats
echo "\n[Map Stats]\n";
$bounds = $db->query("SELECT MIN(x) as min_x, MAX(x) as max_x, MIN(y) as min_y, MAX(y) as max_y FROM wdata")->fetch_assoc();
echo "Bounds: " . print_r($bounds, true) . "\n";

$rStats = $db->query("SELECT MIN(r) as min_r, MAX(r) as max_r, COUNT(*) as cnt FROM available_villages")->fetch_assoc();
echo "available_villages R stats: " . print_r($rStats, true) . "\n";

// 4. Check Fieldtype 3 at edges (R > 10)
$edgeCount = $db->fetchScalar("SELECT COUNT(*) FROM available_villages WHERE r > 10 AND fieldtype=3");
echo "FieldType 3 count at R > 10: $edgeCount\n";

$edgeUnoccupied = $db->fetchScalar("SELECT COUNT(*) FROM available_villages WHERE r > 10 AND fieldtype=3 AND occupied=0");
echo "Unoccupied FieldType 3 at R > 10: $edgeUnoccupied\n";

// 5. Check what IS at the edge
if ($edgeCount == 0) {
    echo "Check what types exist at edge:\n";
    $types = $db->query("SELECT fieldtype, COUNT(*) as c FROM available_villages WHERE r > 10 GROUP BY fieldtype");
    while($row = $types->fetch_assoc()) {
        echo "Type {$row['fieldtype']}: {$row['c']}\n";
    }
}

// 6. Check existing NPC distribution (Center vs Edge)
echo "\n[NPC Distribution]\n";
$users = $db->query("SELECT id, username, aid FROM users WHERE access=3");
$npcCount = 0;
while($u = $users->fetch_assoc()) {
    $npcCount++;
    $v = $db->query("SELECT w.r, w.x, w.y FROM vdata v JOIN wdata w ON v.kid=w.id WHERE v.owner={$u['id']}")->fetch_assoc();
    if ($v) {
        // echo "NPC {$u['username']} (Alliance {$u['aid']}): r={$v['r']} ({$v['x']},{$v['y']})\n";
    }
}
echo "Total NPCs checked: $npcCount\n";

echo "\n=== END REPORT ===\n";
