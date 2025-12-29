<?php
/**
 * Check why farmlist returns 0 raids
 */

// Database connection
$mysqli = new mysqli('localhost', 'maindb', 'E+BtbIW6rlBhFqzRI4L6NAAE', 'maindb');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== FARMLIST DIAGNOSIS ===\n\n";

// Check NPC farmlist
$result = $mysqli->query("SELECT id, name, kid, owner FROM farmlist WHERE owner IN (SELECT id FROM users WHERE access=3) LIMIT 5");

if ($result && $result->num_rows > 0) {
    echo "NPC Farmlists:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  List ID: {$row['id']}, Name: {$row['name']}, Village: {$row['kid']}, Owner: {$row['owner']}\n";
        
        // Check how many slots are in this farmlist
        $slots = $mysqli->query("SELECT COUNT(*) as count FROM raidlist WHERE lid={$row['id']}")->fetch_assoc();
        echo "    Slots: {$slots['count']}\n";
        
        // Check if slots have targets
        if ($slots['count'] > 0) {
            $targets = $mysqli->query("SELECT towkid FROM raidlist WHERE lid={$row['id']} LIMIT 3");
            echo "    Sample targets: ";
            $kids = [];
            while ($t = $targets->fetch_assoc()) {
                $kids[] = $t['towkid'];
            }
            echo implode(', ', $kids) . "\n";
        }
    }
} else {
    echo "NO NPC farmlists found!\n";
}

echo "\n";

// Check NPC troops
echo "NPC Troop Status:\n";
$troopResult = $mysqli->query("
    SELECT u.id, u.name, un.kid, un.u1, un.u2, un.u3, un.u4, un.u5, un.u6, un.u7, un.u8, un.u9, un.u10
    FROM users u
    JOIN units un ON u.id = (SELECT owner FROM vdata WHERE kid = un.kid)
    WHERE u.access=3
    LIMIT 5
");

if ($troopResult && $troopResult->num_rows > 0) {
    while ($row = $troopResult->fetch_assoc()) {
        $totalTroops = $row['u1'] + $row['u2'] + $row['u3'] + $row['u4'] + $row['u5'] + 
                      $row['u6'] + $row['u7'] + $row['u8'] + $row['u9'] + $row['u10'];
        echo "  NPC: {$row['name']} (ID:{$row['id']}, Village:{$row['kid']})\n";
        echo "    Total troops: $totalTroops\n";
        echo "    Breakdown: u1={$row['u1']}, u2={$row['u2']}, u3={$row['u3']}, u4={$row['u4']}, u5={$row['u5']}\n";
    }
} else {
    echo "  No troop data found!\n";
}

$mysqli->close();
