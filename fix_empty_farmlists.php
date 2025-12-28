#!/usr/bin/env php
<?php
/**
 * Fix Empty Farm-Lists: Add targets to NPC farm-lists
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Database\DB;

echo "===== Populating NPC Farm-Lists =====\n\n";

$db = DB::getInstance();

// Get all NPC farm-lists
$farmLists = $db->query("SELECT f.id, f.owner, f.kid 
                         FROM farmlist f
                         JOIN users u ON f.owner = u.id
                         WHERE u.access = 3");

if (!$farmLists || $farmLists->num_rows == 0) {
    echo "No NPC farm-lists found.\n";
    exit(0);
}

$processed = 0;

while ($list = $farmLists->fetch_assoc()) {
    $listId = $list['id'];
    $uid = $list['owner'];
    $kid = $list['kid'];
    
    // Check current target count
    $currentCount = $db->fetchScalar("SELECT COUNT(*) FROM raidlist WHERE lid=$listId");
    
    echo "Farm-list ID $listId (Owner: $uid, Village: $kid)\n";
    echo "  Current targets: $currentCount\n";
    
    if ($currentCount >= 10) {
        echo "  ✓ Already has enough targets\n\n";
        continue;
    }
    
    // Get village coordinates
    $coords = $db->query("SELECT x, y FROM wdata WHERE id=$kid")->fetch_assoc();
    if (!$coords) {
        echo "  ✗ Village not found\n\n";
        continue;
    }
    
    $x = $coords['x'];
    $y = $coords['y'];
    $needed = 10 - $currentCount;
    $maxDistance = 20;
    
    // Find oasis targets
    $oasisQuery = "SELECT w.id as kid
                   FROM wdata w
                   WHERE w.oasistype > 0
                     AND w.occupied = 0
                     AND ABS(w.x - $x) <= $maxDistance
                     AND ABS(w.y - $y) <= $maxDistance
                     AND NOT EXISTS (SELECT 1 FROM raidlist WHERE kid = w.id AND lid = $listId)
                   ORDER BY (ABS(w.x - $x) + ABS(w.y - $y))
                   LIMIT $needed";
    
    $targets = $db->query($oasisQuery);
    $added = 0;
    
    while ($target = $targets->fetch_assoc()) {
        $targetKid = $target['kid'];
        $db->query("INSERT INTO raidlist (kid, lid, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10, t11) 
                    VALUES ($targetKid, $listId, 5, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0)");
        $added++;
    }
    
    // If not enough oasis, add weak players
    if ($added < $needed) {
        $remaining = $needed - $added;
        $villageQuery = "SELECT v.kid
                        FROM vdata v
                        JOIN wdata w ON v.kid = w.id
                        WHERE v.owner > 3
                          AND v.owner != $uid
                          AND v.pop < 200
                          AND ABS(w.x - $x) <= $maxDistance
                          AND ABS(w.y - $y) <= $maxDistance
                          AND NOT EXISTS (SELECT 1 FROM raidlist WHERE kid = v.kid AND lid = $listId)
                        ORDER BY v.pop ASC
                        LIMIT $remaining";
        
        $targets = $db->query($villageQuery);
        while ($target = $targets->fetch_assoc()) {
            $targetKid = $target['kid'];
            $db->query("INSERT INTO raidlist (kid, lid, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10, t11) 
                        VALUES ($targetKid, $listId, 3, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0)");
            $added++;
        }
    }
    
    echo "  ✓ Added $added targets\n\n";
    $processed++;
}

echo "===== Complete =====\n";
echo "Processed $processed farm-lists\n";
