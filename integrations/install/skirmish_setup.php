<?php
use Core\Config;
use Core\Database\DB;
use Model\RegisterModel;
use Model\AllianceModel;
use Model\AutomationModel;
use Core\NpcConfig;

// Skirmish Setup CLI Script
// Usage: php skirmish_setup.php '<json_input>'

function debugLog($msg) {
    file_put_contents('/tmp/skirmish_debug.log', date('[H:i:s] ') . $msg . "\n", FILE_APPEND);
}
debugLog("Process started. ARGC: $argc");

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

if ($argc < 2) {
    debugLog("Missing input JSON");
    die("Missing input JSON");
}

debugLog("Parsing input...");
$input = json_decode($argv[1], true);
if (!$input) {
    die("Invalid JSON input");
}

// 1. Define Constants for Bootstrap
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
}
if (!defined('GLOBAL_CONFIG_FILE')) {
    define('GLOBAL_CONFIG_FILE', ROOT_PATH . 'config.php');
}
if (!defined('CONNECTION_FILE')) {
    define('CONNECTION_FILE', ROOT_PATH . 'servers/' . $input['worldId'] . '/include/connection.php');
}

// 2. Load Bootstrap
$bootstrapPath = ROOT_PATH . 'src/bootstrap.php';
if (!file_exists($bootstrapPath)) {
    die("Bootstrap not found: $bootstrapPath");
}
require_once $bootstrapPath;
debugLog("Bootstrap loaded.");

try {
    $db = DB::getInstance();
    debugLog("DB instance retrieved.");
    $registerModel = new RegisterModel();
    $allianceModel = new AllianceModel();

    // 3. Define Quadrants and Alliances
    $alliances = [
        'NE' => ['tag' => 'NE', 'name' => 'North East Empire', 'angle' => [0, 90]],
        'SE' => ['tag' => 'SE', 'name' => 'South East Empire', 'angle' => [270, 360]],
        'SW' => ['tag' => 'SW', 'name' => 'South West Empire', 'angle' => [180, 270]],
        'NW' => ['tag' => 'NW', 'name' => 'North West Empire', 'angle' => [90, 180]],
    ];
    $allianceIds = [];

    // 4. Create Player Account
    echo "[Skirmish] Creating Player...\n";
    debugLog("Creating player...");
    $playerQuadrant = $input['player_quadrant'];
    $playerKid = $registerModel->generateBase(strtolower($playerQuadrant));
    
    if (!$playerKid) {
        throw new RuntimeException("Could not generate starting position for player in $playerQuadrant");
    }

    // Pass startGold from input
    $startGold = isset($input['startGold']) ? (int)$input['startGold'] : 0;

    $playerId = $registerModel->addUser(
        $input['player_username'],
        sha1($input['player_password']), 
        $input['player_email'],
        $input['player_tribe'],
        $playerKid,
        1,          // access
        $startGold  // giftGold (Corrected)
    );

    if (!$playerId) {
        throw new RuntimeException("Failed to register player.");
    }
    
    $registerModel->createBaseVillage($playerId, $input['player_username'], $input['player_tribe'], $playerKid);
    
    $playerAliData = $alliances[$playerQuadrant];
    $playerAliId = $allianceModel->createAlliance($playerId, $playerAliData['name'], $playerAliData['tag']);
    $allianceIds[$playerQuadrant] = $playerAliId;
    
    echo "[Skirmish] Player '{$input['player_username']}' created in $playerQuadrant (ID: $playerId, Ali: $playerAliId)\n";
    debugLog("Player created. Fetching NPC names...");

    // Prepare NPC Names for EVERYONE (Leaders + Mass)
    $totalNpcs = (int)$input['npc_count'];
    $totalNpcsToName = max($totalNpcs, 3); // Ensure at least enough for 3 leaders
    $npcNames = [];
    $sqlitePath = ROOT_PATH . "src/schema/users.sqlite";
    
    if (file_exists($sqlitePath) && class_exists('SQLite3')) {
        try {
            $sqlite = new SQLite3($sqlitePath);
            // Fetch names equal to total needed
            $res = $sqlite->query("SELECT username FROM users ORDER BY RANDOM() LIMIT $totalNpcsToName");
            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $npcNames[] = $row['username'];
            }
            $sqlite->close();
        } catch (Exception $e) {
            echo "[Skirmish Warning] SQLite name fetch failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "[Skirmish Warning] SQLite users DB not found or SQLite3 missing. Using generic names. (Path: $sqlitePath)\n";
    }

    // 5. Create Leader NPCs
    echo "[Skirmish] Creating Alliance Leaders...\n";
    debugLog("Creating leaders...");
    foreach ($alliances as $quad => $aliData) {
        if ($quad === $playerQuadrant) continue;

        // Use real name if available, else generic
        $leaderName = !empty($npcNames) ? array_shift($npcNames) : ($aliData['tag'] . "_Leader");
        
        $leaderKid = $registerModel->generateBase(strtolower($quad));
        $tribe = mt_rand(1, 3);
        
        $leaderId = $registerModel->addUser(
            $leaderName,
            sha1(microtime()),
            '',
            $tribe,
            $leaderKid,
            3,          // access
            $startGold  // giftGold (Corrected)
        );
        
        $registerModel->createBaseVillage($leaderId, $leaderName, $tribe, $leaderKid);
        if (class_exists('Core\NpcConfig')) {
            \Core\NpcConfig::assignRandom($leaderId);
        }
        
        $aliId = $allianceModel->createAlliance($leaderId, $aliData['name'], $aliData['tag']);
        $allianceIds[$quad] = $aliId;
        echo "[Skirmish] Leader '$leaderName' created (Ali: $aliId)\n";
    }

    // 6. Create Mass NPCs
    $npcsToCreate = max(0, $totalNpcs - 3);
    echo "[Skirmish] Creating $npcsToCreate additional NPCs...\n";
    debugLog("Creating mass NPCs ($npcsToCreate)...");

    $quadKeys = array_keys($alliances);
    $quadIndex = 0;
    
    for ($i = 0; $i < $npcsToCreate; $i++) {
        $quad = $quadKeys[$quadIndex];
        $quadIndex = ($quadIndex + 1) % 4;
        
        // Use real name if available, else generic
        $npcName = !empty($npcNames) ? array_shift($npcNames) : ("NPC_" . $quad . "_" . ($i + 1));
        
        // Inline Create Function Logic
        $kid = $registerModel->generateBase(strtolower($quad));
        if ($kid) {
            $tribe = mt_rand(1, 3);
            $uid = $registerModel->addUser(
                $npcName,
                sha1(microtime() . $npcName),
                '',
                $tribe,
                $kid,
                3,          // access
                $startGold  // giftGold (Corrected)
            );
            if ($uid) {
                $registerModel->createBaseVillage($uid, $npcName, $tribe, $kid);
                $db->query("UPDATE users SET aid={$allianceIds[$quad]}, alliance_join_time=".time()." WHERE id=$uid");
                $allianceModel->recalculateMaxUsers($allianceIds[$quad]);
                 if (class_exists('Core\NpcConfig')) {
                    \Core\NpcConfig::assignRandom($uid);
                }
            }
        }
    }
    
    echo "[Skirmish] Setup Complete.\n";
    debugLog("Setup Complete.");
    exit(0);

} catch (Exception $e) {
    echo "[Skirmish Error] " . $e->getMessage() . "\n";
    debugLog("Error: " . $e->getMessage());
    exit(1);
}

