<?php
/**
 * Give NPCs Initial Troops
 * Run: php give_npcs_troops.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqli = new mysqli('localhost', 'maindb', 'E+BtbIW6rlBhFqzRI4L6NAAE', 'maindb');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== GIVING NPCs INITIAL TROOPS ===\n\n";

// Get all NPCs with their villages
$result = $mysqli->query("
    SELECT u.id, u.name, u.race, v.kid 
    FROM users u 
    JOIN vdata v ON v.owner = u.id 
    WHERE u.access=3
");

if (!$result || $result->num_rows == 0) {
    die("No NPCs found!\n");
}

$updated = 0;

while ($row = $result->fetch_assoc()) {
    $uid = $row['id'];
    $name = $row['name'];
    $race = $row['race'];
    $kid = $row['kid'];
    
    // Define initial troops based on race (fast raiding units)
    $troops = [
        1 => [2 => 20, 5 => 15, 6 => 10], // Romans: Equites Imperatoris, Eq. Caesaris, Eq. Legati
        2 => [3 => 25, 5 => 15, 6 => 10], // Teutons: Axemen, TK, Paladins
        3 => [2 => 20, 4 => 15, 6 => 10], // Gauls: Swordsmen, TT, Haeduans
        6 => [2 => 20, 5 => 15, 6 => 10], // Egyptians
        7 => [1 => 20, 2 => 15, 5 => 15], // Huns: Mercenaries, Bowmen, Steppe Riders
    ];
    
    $raceTroops = $troops[$race] ?? $troops[2]; // Default to Teutons if unknown race
    
    // Build UPDATE query
    $updates = [];
    foreach ($raceTroops as $unitNr => $count) {
        $updates[] = "u$unitNr = $count";
    }
    
    $query = "UPDATE units SET " . implode(", ", $updates) . " WHERE kid=$kid";
    
    if ($mysqli->query($query)) {
        echo "✓ Gave troops to NPC '$name' (ID:$uid, Race:$race, Village:$kid)\n";
        echo "  Units: " . json_encode($raceTroops) . "\n";
        $updated++;
    } else {
        echo "✗ Failed to give troops to NPC '$name': " . $mysqli->error . "\n";
    }
}

echo "\n=== COMPLETE ===\n";
echo "Updated $updated NPC villages with initial troops\n";
echo "\nNPCs should now be able to raid!\n";
echo "Check logs: sudo journalctl -u travium@s1 --since '1 minute ago' | grep NPC_DEBUG\n";

$mysqli->close();
