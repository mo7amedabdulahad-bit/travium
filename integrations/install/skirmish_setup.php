<?php
use Core\Config;
use Core\Database\DB;
use Model\RegisterModel;
use Model\AllianceModel;
use Model\AutomationModel;
use Core\NpcConfig; // Make sure this class exists or is autoloaded

/**
 * Skirmish Mode Setup Script
 * 
 * This script is included by integrations/install/index.php AFTER a successful 
 * standard installation if 'skirmish' mode is selected.
 * 
 * Pre-requisites:
 * - src/bootstrap.php must be loadable (install/index.php environment needs to allow this)
 * - Config and DB connections must be active
 * - $input array from install/index.php must contain:
 *      player_username, player_password, player_email, player_tribe, player_quadrant, npc_count
 */

function skirmishSetup(array $input) {
    global $config;

    // 1. Bootstrap the Game Engine
    // We need to switch context from "installer" to "game"
    // Assuming we are in integrations/install/
    $bootstrapPath = dirname(__DIR__, 2) . '/src/bootstrap.php';
    if (!file_exists($bootstrapPath)) {
        throw new RuntimeException("Skirmish Setup: Cannot find bootstrap.php at $bootstrapPath");
    }
    
    // Config::getInstance() might already be loaded by install/index.php but we need full bootstrap
    // We wrap this in a closure or check to avoid redeclaration issues if index.php did partial load
    if (!defined('ROOT_PATH')) {
        if (!defined('GLOBAL_CONFIG_FILE')) {
            define('GLOBAL_CONFIG_FILE', dirname(__DIR__, 2) . '/config.php');
        }
        if (!defined('CONNECTION_FILE')) {
            define('CONNECTION_FILE', dirname(__DIR__, 2) . '/servers/' . $input['worldId'] . '/include/connection.php');
        }
        require_once $bootstrapPath;
    }

    $db = DB::getInstance();
    $registerModel = new RegisterModel();
    $allianceModel = new AllianceModel();

    // 2. Define Quadrants and Alliances
    $alliances = [
        'NE' => ['tag' => 'NE', 'name' => 'North East Empire', 'angle' => [0, 90]],
        'SE' => ['tag' => 'SE', 'name' => 'South East Empire', 'angle' => [270, 360]],
        'SW' => ['tag' => 'SW', 'name' => 'South West Empire', 'angle' => [180, 270]],
        'NW' => ['tag' => 'NW', 'name' => 'North West Empire', 'angle' => [90, 180]],
    ];
    
    // Map storing 'Quadrant' => 'AllianceID'
    $allianceIds = [];

    // 3. Create Player Account
    $playerQuadrant = $input['player_quadrant']; // NE, SE, SW, NW
    
    // Generate valid coordinate in selected quadrant
    $playerKid = $registerModel->generateBase(strtolower($playerQuadrant));
    if (!$playerKid) {
        throw new RuntimeException("Could not generate starting position for player in $playerQuadrant");
    }

    $playerId = $registerModel->addUser(
        $input['player_username'],
        $input['player_password'], // Pass raw, addUser handles hashing? 
        // WAIT: RegisterModel::addUser expects already hashed password? 
        // Let's check ActivateCtrl logic: $_SESSION[.. pw] stores it, then RegisterModel->addUser uses it.
        // Usually Travium stores plaintext or md5? 
        // Looking at RegisterModel::addUser($name, $password...):
        // $db->query("INSERT INTO users ... '$password' ...")
        // It inserts exactly what is passed.
        // Installer input is plaintext. We should hash it if the game expects hashed.
        // Code check: LoginCtrl uses `md5($password)`.
        // So we must pass `md5($input['player_password'])`.
        md5($input['player_password']),
        $input['player_email'],
        $input['player_tribe'],
        $playerKid
    );

    if (!$playerId) {
        throw new RuntimeException("Failed to notify RegisterModel->addUser for player.");
    }
    
    // Create Village
    $registerModel->createBaseVillage($playerId, $input['player_username'], $input['player_tribe'], $playerKid);
    
    // Create Player's Alliance
    $playerAliData = $alliances[$playerQuadrant];
    $playerAliId = $allianceModel->createAlliance($playerId, $playerAliData['name'], $playerAliData['tag']);
    $allianceIds[$playerQuadrant] = $playerAliId;
    
    // Log
    logSuccess("Created Player '{$input['player_username']}' (ID: $playerId) in $playerQuadrant at KID $playerKid. Alliance: {$playerAliData['name']} (ID: $playerAliId)");


    // 4. Create Leader NPCs for other quadrants
    // We need a leader to create the alliance.
    foreach ($alliances as $quad => $aliData) {
        if ($quad === $playerQuadrant) continue; // Already created

        $leaderName = $aliData['tag'] . "_Leader";
        $leaderKid = $registerModel->generateBase(strtolower($quad));
        
        // Random tribe for NPC leader
        $tribe = mt_rand(1, 3); 
        
        $leaderId = $registerModel->addUser(
            $leaderName,
            sha1(microtime()), // Random password
            '',
            $tribe,
            $leaderKid,
            3 // Access level 3 (Multihunter/Support/NPC?) or 1? Let's use 1 (Regular) or check FakeUserModel usage. 
              // RegisterModel::addFakeUser uses 3.
        );
        
        $registerModel->createBaseVillage($leaderId, $leaderName, $tribe, $leaderKid);
        
        // Assign Personality
        // \Core\NpcConfig::assignRandom($leaderId); // If available
        
        // Create Alliance
        $aliId = $allianceModel->createAlliance($leaderId, $aliData['name'], $aliData['tag']);
        $allianceIds[$quad] = $aliId;
        
        logSuccess("Created NPC Leader '$leaderName' in $quad. Alliance: {$aliData['name']} (ID: $aliId)");
    }

    // 5. Create Mass NPCs
    // Total NPCs requested minus the 3 leaders we just created
    $totalNpcs = (int)$input['npc_count'];
    $npcsToCreate = max(0, $totalNpcs - 3);
    
    $createdCount = 0;
    
    // We will cycle through quadrants to distribute evenly
    $quadKeys = array_keys($alliances);
    $quadIndex = 0;
    
    // Get NPC names
    // We can assume we have a list or generate names.
    // For now, let's generate generic names "NPC_NE_1", etc. or use a name generator if available.
    // RegisterModel::addFakeUser takes a comma list. But we want manual control.
    
    // We will use a simple name generation for robustness
    for ($i = 0; $i < $npcsToCreate; $i++) {
        $quad = $quadKeys[$quadIndex];
        $quadIndex = ($quadIndex + 1) % 4; // Rotate
        
        $npcName = "NPC_" . $quad . "_" . ($i + 1);
        
        try {
            createNpcInQuadrant($registerModel, $allianceModel, $npcName, $quad, $allianceIds[$quad]);
            $createdCount++;
        } catch (Exception $e) {
            // Log error but continue
            // echo "Failed to create NPC $npcName: " . $e->getMessage();
        }
    }
    
    logSuccess("Created $createdCount additional NPCs distributed across quadrants.");
    
    return true;
}

/**
 * Helper to create a single NPC in a specific quadrant and assign to alliance
 */
function createNpcInQuadrant(RegisterModel $registerModel, AllianceModel $allianceModel, string $name, string $quadrant, int $allianceId) {
    
    // 1. Generate Location
    $kid = $registerModel->generateBase(strtolower($quadrant));
    if (!$kid) return false;
    
    // 2. Create User
    $tribe = mt_rand(1, 3);
    $uid = $registerModel->addUser(
        $name,
        sha1(microtime() . $name),
        '',
        $tribe,
        $kid,
        3 // Fake User Access
    );
    
    if (!$uid) return false;
    
    // 3. Create Village
    $registerModel->createBaseVillage($uid, $name, $tribe, $kid);
    
    // 4. Assign to Alliance
    // We can use AllianceModel::acceptInvite style logic or direct DB update since we are admin/setup
    // Direct DB update is safer/faster for setup script.
    $db = DB::getInstance();
    $now = time();
    $db->query("UPDATE users SET aid=$allianceId, alliance_join_time=$now WHERE id=$uid");
    
    // Update Alliance counts? Recalculate?
    // AllianceModel::recalculateMaxUsers($aid)
    $allianceModel->recalculateMaxUsers($allianceId);
    
    // 5. Assign Config/Personality
    if (class_exists('Core\NpcConfig')) {
        \Core\NpcConfig::assignRandom($uid);
    }
    
    return $uid;
}

function logSuccess($msg) {
    // Basic logging, maybe to a file or echoed if the installer captures output
    file_put_contents(dirname(__DIR__, 2) . '/skirmish_setup.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}
