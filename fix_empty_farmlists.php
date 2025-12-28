#!/usr/bin/env php
<?php
/**
 * Fix Empty Farm-Lists: Add targets to NPC farm-lists (v2 - with debug)
 */

require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

use Core\Database\DB;

echo "===== Populating NPC Farm-Lists (Debug Mode) =====\n\n";

$db = DB::getInstance();

// Get all NPC farm-lists
$farmLists = $db->query("SELECT f.id, f.owner, f.kid, u.name
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
    $name = $list['name'];
    
    echo "Processing: $name (UID: $uid, List ID: $listId, Village: $kid)\n";
    
    // Check current target count
    $currentCount = $db->fetchScalar("SELECT COUNT(*) FROM raidlist WHERE lid=$listId");
    echo "  Current targets: $currentCount\n";
    
    if ($currentCount >= 10) {
        echo "  ✓ Already has enough targets\n\n";
        continue;
    }
    
    // Get village coordinates
    $result = $db->query("SELECT x, y FROM wdata WHERE id=$kid");
    if (!$result || $result->num_rows == 0) {
        echo "  ✗ Village not found in wdata\n\n";
        continue;
    }
    
    $coords = $result->fetch_assoc();
    $x = $coords['x'];
    $y = $coords['y'];
    echo "  Village location: ($x, $y)\n";
    
    $needed = 10 - $currentCount;
    $maxDistance = 25;
    $added = 0;
    
    // Try to find ANY nearby targets (oasis or villages)
    echo "  Searching for targets within $maxDistance tiles...\n";
    
    // First try oasis
    $oasisQuery = "SELECT w.id as kid, w.x, w.y, w.oasistype
                   FROM wdata w
                   WHERE w.oasistype > 0
                     AND w.occupied = 0
                     AND ABS(w.x - ($x)) <= $maxDistance
                     AND ABS(w.y - ($y)) <= $maxDistance
                     AND w.id != $kid
                   ORDER BY (ABS(w.x - ($x)) + ABS(w.y - ($y)))
                   LIMIT $needed";
    
    $targets = $db->query($oasisQuery);
    echo "    Oasis found: " . ($targets ? $targets->num_rows : 0) . "\n";
    
    try {
        if ($targets) {
            while ($target = $targets->fetch_assoc()) {
                $targetKid = $target['kid'];
                echo "      Attempting to add oasis kid=$targetKid...\n";
                
                $result = $db->query("INSERT INTO raidlist (kid, lid, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10, t11) 
                            VALUES ($targetKid, $listId, 5, 5, 0, 0, 0, 0, 0, 0, 0, 0, 0)");
                
                if ($result) {
                    $added++;
                    echo "      ✓ Added oasis at ({$target['x']},{$target['y']}) - kid: $targetKid\n";
                } else {
                    echo "      ✗ FAILED to add oasis - query returned false\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "    ✗ ERROR in loop: " . $e->getMessage() . "\n";
        echo "    Stack: " . $e->getTraceAsString() . "\n";
    }
    
    // If still need more, try player villages
    if ($added < $needed) {
        $remaining = $needed - $added;
        echo "    Looking for $remaining player villages...\n";
        
        $villageQuery = "SELECT v.kid, w.x, w.y
                        FROM vdata v
                        JOIN wdata w ON v.kid = w.id
                        WHERE v.owner > 1
                          AND v.owner != $uid
                          AND v.owner != 1
                          AND ABS(w.x - ($x)) <= $maxDistance
                          AND ABS(w.y - ($y)) <= $maxDistance
                        ORDER BY v.pop ASC
                        LIMIT $remaining";
        
        $targets = $db->query($villageQuery);
        echo "    Player villages found: " . $targets->num_rows . "\n";
        
        while ($target = $targets->fetch_assoc()) {
            $targetKid = $target['kid'];
            $db->query("INSERT INTO raidlist (kid, lid, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10, t11) 
                        VALUES ($targetKid, $listId, 3, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0)");
            $added++;
            echo "    + Added village at ({$target['x']},{$target['y']})\n";
        }
    }
    
    echo "  ✓ Total added: $added targets\n\n";
    $processed++;
}

echo "===== Complete =====\n";
echo "Processed $processed farm-lists\n";
