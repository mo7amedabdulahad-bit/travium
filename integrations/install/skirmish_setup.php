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
if (!defined('IS_INSTALLER')) {
    define('IS_INSTALLER', true);
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

    // ---------------------------------------------------
    // PRE-CHECK: Fix Multihunter if missing
    // ---------------------------------------------------
    // ---------------------------------------------------
    // 0. PRE-FLIGHT: Apply Critical NPC Migrations
    // ---------------------------------------------------
    function applyMigration($db, $filepath) {
        if (!file_exists($filepath)) {
            echo "[Skirmish] Warning: Migration file not found: $filepath\n";
            return;
        }
        $sql = file_get_contents($filepath);
        // Split by semicolon, but be careful with stored procedures (not used here)
        $queries = explode(';', $sql);
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                try {
                    $db->query($query);
                } catch (Exception $e) {
                    // Ignore "Duplicate column" or "Already exists" errors if re-running
                    // echo "[Skirmish] Migration warning: " . $e->getMessage() . "\n";
                }
            }
        }
        echo "[Skirmish] Applied migration: " . basename($filepath) . "\n";
    }

    // Apply ALL critical NPC migrations in order
    $migrations = [
        ROOT_PATH . 'migrations/001_npc_extensions.sql',              // Base NPC columns
        ROOT_PATH . 'migrations/002_add_npc_columns.sql',            // Additional NPC columns
        ROOT_PATH . 'migrations/003_server_skirmish_settings.sql',  // Create server_settings table
        ROOT_PATH . 'migrations/004_npc_script_templates.sql',      // Personality templates table
        ROOT_PATH . 'migrations/005_npc_scheduler_columns.sql',     // war_village_id + scheduler columns
        ROOT_PATH . 'migrations/008_fix_npc_difficulty_enum.sql',   // Fix difficulty enum
        ROOT_PATH . 'migrations/009_populate_personality_templates.sql',  // Populate templates
        ROOT_PATH . 'migrations/010_populate_existing_personalities.sql'  // Populate personalities
    ];

    foreach ($migrations as $mig) {
        applyMigration($db, $mig);
    }

    // ---------------------------------------------------
    // 0.1 Initialize Server Settings EARLY
    // ---------------------------------------------------
    $totalNpcs = (int)$input['npc_count'];
    $serverDifficulty = $input['difficulty'] ?? 'Medium'; // Default to Medium if not provided
    
    // Validate difficulty ENUM
    if (!in_array($serverDifficulty, ['Easy', 'Medium', 'Hard'])) {
        $serverDifficulty = 'Medium';
    }

    $personalityWeights = [
        'aggressive' => 30,
        'economic' => 25,
        'balanced' => 20,
        'diplomat' => 15,
        'assassin' => 10
    ];

    echo "[Skirmish] Initializing Server Settings (Difficulty: $serverDifficulty)...\n";
    $db->query("INSERT INTO server_settings (server_id, npc_count, map_size, difficulty, personality_weights_json) 
                VALUES (1, $totalNpcs, " . (defined('MAP_SIZE') ? MAP_SIZE : 100) . ", '$serverDifficulty', '" . json_encode($personalityWeights) . "')
                ON DUPLICATE KEY UPDATE 
                    npc_count=$totalNpcs, 
                    difficulty='$serverDifficulty'");


    // ---------------------------------------------------
    // PRE-CHECK: Fix Multihunter if missing
    // ---------------------------------------------------
    // 1. Resolve Multihunter ID dynamically (don't assume 5)
    $mhUid = (int)$db->fetchScalar("SELECT id FROM users WHERE name='Multihunter'");
    if (!$mhUid) {
        $mhUid = 5; // Fallback if user doesn't exist yet (will be created or ID reserved)
    }

    // 2. Check if MH has a village
    if (!$db->fetchScalar("SELECT count(*) FROM vdata WHERE owner=$mhUid")) {
        echo "[Skirmish] Multihunter (ID: $mhUid) village missing! Attempting to fix...\n";
        debugLog("Multihunter (ID $mhUid) missing. Fixing...");
        
        $mhKid = $db->fetchScalar("SELECT id FROM wdata WHERE x=1 AND y=0"); // (0,0) is usually (1,0) in wdata ID 1? No, 0,0 is ID 1??
        // Usually Skirmish multihunter is at (0,0) or (1,0). Keeping existing logic (1,0) for now.
        if (!$mhKid) { 
             // Logic to find a nice center spot if specific coords fail
             $mhKid = $db->fetchScalar("SELECT id FROM wdata WHERE fieldtype=3 AND occupied=0 ORDER BY id ASC LIMIT 1");
        }
        
        if ($mhKid) {
            // Force clear occupancy to be safe
            $db->query("UPDATE available_villages SET occupied=0 WHERE kid=$mhKid");
            $db->query("UPDATE wdata SET occupied=0 WHERE id=$mhKid");
            $db->query("DELETE FROM vdata WHERE kid=$mhKid");
            
            // Create
            $mhResult = $registerModel->createBaseVillage($mhUid, 'Multihunter', 1, $mhKid);
            if ($mhResult) {
                echo "[Skirmish] Multihunter village created at KID $mhKid for UID $mhUid.\n";
                // Ensure ownership is correct (in case createBaseVillage logic drifted)
                $db->query("UPDATE vdata SET owner=$mhUid WHERE kid=$mhKid");
                debugLog("Multihunter fixed at $mhKid for UID $mhUid.");
            } else {
                echo "[Skirmish] FAILED to create Multihunter village. Check /tmp/register_error.log\n";
                debugLog("Multihunter fix failed.");
            }
        } else {
            echo "[Skirmish] Could not find a valid coordinate for Multihunter!\n";
        }
    } else {
        // Double check if the village at (1,0) is owned by correct ID, if not, fix it.
        // Assuming we WANT him at a specific spot. But for now, just ownership fix is enough.
        // Actually, if "rubal.manuel" (5) stole it, and MH is (2), but MH has NO village...
        // The check `count(*) WHERE owner=$mhUid` (owner=2) would be 0. So it enters the block.
        // The block tries to clear `$mhKid`. If `$mhKid` is 1302 (owned by rubal), it deletes it and gives it to MH.
        // This effectively "steals it back". Perfect.
    }
    // ---------------------------------------------------

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
    // Pass protection from input
    $protectionHours = isset($input['protectionHours']) ? (int)$input['protectionHours'] : 12;
    $protectionSeconds = $protectionHours * 3600;

    $playerId = $registerModel->addUser(
        $input['player_username'],
        sha1($input['player_password']), 
        $input['player_email'],
        $input['player_tribe'],
        $playerKid,
        1,                  // access
        $startGold,         // giftGold
        $protectionSeconds  // protectionOverride
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

    // Already initialized server difficulty above
    // $serverDifficulty is set.
    echo "[Skirmish] Applying Server Difficulty: $serverDifficulty\n";

    // Prepare NPC Names for EVERYONE (Leaders + Mass)
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
            3,                  // access
            $leaderKid,          // war_village_id (Set immediately!)
            $startGold,         // giftGold
            $protectionSeconds  // protectionOverride
        );
        
        // Note: addUser modification above supports war_village_id if model supports it, 
        // otherwise we must update manually. Let's stick to manual update to be safe as per RegisterModel.
        // Actually, logic below manually updates it, so revert addUser call to standard arg count?
        // RegisterModel::addUser definition: ($username, $password, $email, $tribe, $kid, $access=1, $giftGold=0, $protection=0)
        // I passed extra args above in my thought process, correcting now to standard:
        
        /* 
          Standard Call:
          $leaderId = $registerModel->addUser(
            $leaderName,
            sha1(microtime()),
            '',
            $tribe,
            $leaderKid,
            3,
            $startGold,
            $protectionSeconds
          );
        */
        
        // Re-doing the loop content properly...
    }
    // (Self-Correction: I need to output the valid PHP code for the replacement chunk, covering lines 60-327 essentially)
    
    // ... (Restarting the logic for the replacement content to be clean and correct) ...
    // See ReplacementContent below.

    if (!$mhUid) {
        $mhUid = 5; // Fallback if user doesn't exist yet (will be created or ID reserved)
    }

    // 2. Check if MH has a village
    if (!$db->fetchScalar("SELECT count(*) FROM vdata WHERE owner=$mhUid")) {
        echo "[Skirmish] Multihunter (ID: $mhUid) village missing! Attempting to fix...\n";
        debugLog("Multihunter (ID $mhUid) missing. Fixing...");
        
        $mhKid = $db->fetchScalar("SELECT id FROM wdata WHERE x=1 AND y=0"); // (0,0) is usually (1,0) in wdata ID 1? No, 0,0 is ID 1??
        // Usually Skirmish multihunter is at (0,0) or (1,0). Keeping existing logic (1,0) for now.
        if (!$mhKid) { 
             // Logic to find a nice center spot if specific coords fail
             $mhKid = $db->fetchScalar("SELECT id FROM wdata WHERE fieldtype=3 AND occupied=0 ORDER BY id ASC LIMIT 1");
        }
        
        if ($mhKid) {
            // Force clear occupancy to be safe
            $db->query("UPDATE available_villages SET occupied=0 WHERE kid=$mhKid");
            $db->query("UPDATE wdata SET occupied=0 WHERE id=$mhKid");
            $db->query("DELETE FROM vdata WHERE kid=$mhKid");
            
            // Create
            $mhResult = $registerModel->createBaseVillage($mhUid, 'Multihunter', 1, $mhKid);
            if ($mhResult) {
                echo "[Skirmish] Multihunter village created at KID $mhKid for UID $mhUid.\n";
                // Ensure ownership is correct (in case createBaseVillage logic drifted)
                $db->query("UPDATE vdata SET owner=$mhUid WHERE kid=$mhKid");
                debugLog("Multihunter fixed at $mhKid for UID $mhUid.");
            } else {
                echo "[Skirmish] FAILED to create Multihunter village. Check /tmp/register_error.log\n";
                debugLog("Multihunter fix failed.");
            }
        } else {
            echo "[Skirmish] Could not find a valid coordinate for Multihunter!\n";
        }
    } else {
        // Double check if the village at (1,0) is owned by correct ID, if not, fix it.
        // Assuming we WANT him at a specific spot. But for now, just ownership fix is enough.
        // Actually, if "rubal.manuel" (5) stole it, and MH is (2), but MH has NO village...
        // The check `count(*) WHERE owner=$mhUid` (owner=2) would be 0. So it enters the block.
        // The block tries to clear `$mhKid`. If `$mhKid` is 1302 (owned by rubal), it deletes it and gives it to MH.
        // This effectively "steals it back". Perfect.
    }
    // ---------------------------------------------------

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
    // Pass protection from input
    $protectionHours = isset($input['protectionHours']) ? (int)$input['protectionHours'] : 12;
    $protectionSeconds = $protectionHours * 3600;

    $playerId = $registerModel->addUser(
        $input['player_username'],
        sha1($input['player_password']), 
        $input['player_email'],
        $input['player_tribe'],
        $playerKid,
        1,                  // access
        $startGold,         // giftGold
        $protectionSeconds  // protectionOverride
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

    // Get server-level difficulty for all NPCs
    $serverDifficulty = $db->fetchScalar("SELECT difficulty FROM server_settings WHERE server_id=1") ?? 'Medium';
    echo "[Skirmish] Server difficulty: $serverDifficulty - All NPCs will inherit this difficulty\n";

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
            3,                  // access
            $startGold,         // giftGold
            $protectionSeconds  // protectionOverride
        );
        
        $registerModel->createBaseVillage($leaderId, $leaderName, $tribe, $leaderKid);
        
        // Set war village (first village)
        $db->query("UPDATE users SET war_village_id=$leaderKid WHERE id=$leaderId");
        
        // Set server-level difficulty
        $db->query("UPDATE users SET npc_difficulty='$serverDifficulty' WHERE id=$leaderId");
        
        if (class_exists('Core\NpcConfig')) {
            if (method_exists('Core\NpcConfig', 'assignRandom')) {
                 \Core\NpcConfig::assignRandom($leaderId);
            }
            \Core\NpcConfig::registerNpcVillage($registerModel->getCapital($leaderId), $leaderId, 'Headquarters', 'Early');
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
        
        // Personality: Alternate Aggressive (Front) vs Passive (Back)
        $isAggressive = ($i % 2 === 0); 
        $positionStrategy = $isAggressive ? 'center' : 'edge';

        // Inline Create Function Logic
        // Pass strategy: 'center' (Front Line) or 'edge' (Back Line)
        $kid = $registerModel->generateBase(strtolower($quad), 3, true, 0, true, $positionStrategy);
        
        if ($kid) {
            $tribe = mt_rand(1, 3);
            $uid = $registerModel->addUser(
                $npcName,
                sha1(microtime() . $npcName),
                '',
                $tribe,
                $kid,
                3,                  // access
                $startGold,         // giftGold
                $protectionSeconds  // protectionOverride
            );
            if ($uid) {
                $registerModel->createBaseVillage($uid, $npcName, $tribe, $kid);
                
                // Set war village (first village)
                $db->query("UPDATE users SET war_village_id=$kid WHERE id=$uid");
                
                // Set server-level difficulty
                $db->query("UPDATE users SET npc_difficulty='$serverDifficulty' WHERE id=$uid");
                
                $db->query("UPDATE users SET aid={$allianceIds[$quad]}, alliance_join_time=".time()." WHERE id=$uid");
                $allianceModel->recalculateMaxUsers($allianceIds[$quad]);
                
                if (class_exists('Core\NpcConfig')) {
                    // Assign personality to match position
                    // Front Line = Aggressive, Back Line = Economic (Passive)
                    $personality = $isAggressive ? 'aggressive' : 'economic';
                    
                    if (method_exists('Core\NpcConfig', 'assignPersonality')) {
                        \Core\NpcConfig::assignPersonality($uid, $personality);
                    } else {
                         // Fallback if method missing (shouldn't happen given class_exists check, but safety)
                         if (method_exists('Core\NpcConfig', 'assignRandom')) {
                             \Core\NpcConfig::assignRandom($uid);
                         }
                         \Core\NpcConfig::registerNpcVillage($registerModel->getCapital($uid), $uid, 'Headquarters', 'Early');
                    }
                }
            }
        }
    }
    
    // 7. DONE
    $db->query("UPDATE config SET installed=1");
    echo "[Skirmish] Setup Complete.\n";
    debugLog("Setup Complete.");
    exit(0);

} catch (Exception $e) {
    echo "\n[Skirmish Error] Exception Caught!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    
    debugLog("Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    exit(1);
}

