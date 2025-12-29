<?php

namespace Core\AI;

use Core\NpcConfig;
use Core\Database\DB;
use Game\Formulas;
use Model\MovementsModel;
use Game\SpeedCalculator;
use Game\ResourcesHelper;
use function nrToUnitId;

/**
 * Raid AI for NPCs
 * 
 * Enables NPCs to automatically send raids to other villages
 * based on personality, target selection algorithms, and raid frequency.
 * 
 * THIS IS THE MOST CRITICAL FEATURE FOR ENGAGING NPC BEHAVIOR!
 * 
 * @package Core\AI
 * @version 1.0
 * @date 2025-12-28
 */
class RaidAI
{
    /**
     * Target selection: Find suitable villages to raid
     * 
     * @param int $kid Attacker village ID
     * @param int $maxDistance Maximum distance in tiles (default 20)
     * @return array|false Array of target info or false if no targets
     */
    public static function findTargets($kid, $maxDistance = 20)
    {
        $db = DB::getInstance();
        
        // Get attacker info
        $attacker = $db->query("SELECT owner FROM vdata WHERE kid=$kid")->fetch_assoc();
        if (!$attacker) {
            return false;
        }
        
        $attackerUid = $attacker['owner'];
        
        // Get attacker alliance ID (to avoid attacking allies)
        $attackerAid = $db->fetchScalar("SELECT aid FROM users WHERE id=$attackerUid");
        
        // Get attacker coordinates
        $attackerXY = Formulas::kid2xy($kid);
        
        // Find nearby villages AND oasis
        // Villages from vdata, oasis from wdata (they don't have vdata entries!)
        // Includes: Players, Other NPCs (access=3), Oasis (oasistype > 0, occupied = 0)
        // Excludes: Natars (owner=1) - too powerful
        
        // Query 1: Regular villages
        $query = "SELECT v.kid, v.owner, v.name, v.wood, v.clay, v.iron, v.crop, 
                         v.maxstore, v.maxcrop, v.pop, v.created,
                         w.x, w.y, 0 as oasistype
                  FROM vdata v
                  JOIN wdata w ON v.kid = w.id
                  WHERE v.owner != $attackerUid
                    AND v.owner !=1
                    AND v.owner > 0
                    AND w.occupied = 1
                    AND ABS(w.x - {$attackerXY['x']}) <= $maxDistance
                    AND ABS(w.y - {$attackerXY['y']}) <= $maxDistance
                  LIMIT 50";
        
        $result = $db->query($query);
        
        $targets = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Check if target has beginner protection (skip protected players only)
                if ($row['owner'] > 1) { // Only check real players, not Natars or oasis
                    $protection = $db->fetchScalar("SELECT protection FROM users WHERE id={$row['owner']}");
                    if ($protection && $protection >= time()) {
                        continue; // Skip protected players
                    }
                }
                
                // Check if target is in same alliance (skip allies, but allow NAP attacks)
                $targetAid = 0;
                if ($row['owner'] > 1) {
                    $targetAid = $db->fetchScalar("SELECT aid FROM users WHERE id={$row['owner']}");
                }
                
                if ($targetAid && $attackerAid && $targetAid == $attackerAid) {
                    continue; // Don't attack same alliance members
                }
                
                // Calculate distance
                $distance = sqrt(
                    pow($attackerXY['x'] - $row['x'], 2) + 
                    pow($attackerXY['y'] - $row['y'], 2)
                );
                
                // Estimate loot (resources available)
                $estimatedLoot = ($row['wood'] + $row['clay'] + $row['iron'] + $row['crop']) * 0.5; // 50% average loot
                
                // Calculate attractiveness score
                // Formula: (Loot * 10) - (Distance * 5) - (Age penalty for new players)
                $ageDays = ($row['created'] > 0) ? (time() - $row['created']) / 86400 : 0;
                $agePenalty = ($row['owner'] > 1 && $ageDays > 0 && $ageDays < 7) ? (7 - $ageDays) * 10 : 0; // Newer players less attractive
                
                $score = ($estimatedLoot * 10) - ($distance * 5) - $agePenalty;
                
                $targets[] = [
                    'kid' => $row['kid'],
                    'owner' => $row['owner'],
                    'name' => $row['name'],
                    'x' => $row['x'],
                    'y' => $row['y'],
                    'distance' => round($distance, 2),
                    'estimated_loot' => round($estimatedLoot),
                    'pop' => $row['pop'],
                    'score' => round($score),
                    'age_days' => round($ageDays, 1),
                    'type' => 'village',
                ];
            }
        }
        
        // Query 2: Oasis (separate query since they don't have vdata entries)
        $oasisQuery = "SELECT w.id as kid, 0 as owner, CONCAT('Oasis ', w.x, '|', w.y) as name,
                              0 as wood, 0 as clay, 0 as iron, 0 as crop, 0 as maxstore, 0 as maxcrop,
                              0 as pop, 0 as created, w.x, w.y, w.oasistype
                       FROM wdata w
                       WHERE w.oasistype > 0
                         AND w.occupied = 0
                         AND w.id != {$kid}
                         AND ABS(w.x - {$attackerXY['x']}) <= $maxDistance
                         AND ABS(w.y - {$attackerXY['y']}) <= $maxDistance
                       LIMIT 50";
        
        $oasisResult = $db->query($oasisQuery);
        
        if ($oasisResult && $oasisResult->num_rows > 0) {
            while ($row = $oasisResult->fetch_assoc()) {
                // Calculate distance
                $distance = sqrt(
                    pow($attackerXY['x'] - $row['x'], 2) + 
                    pow($attackerXY['y'] - $row['y'], 2)
                );
                
                // Oasis have fixed loot potential based on type
                $estimatedLoot = 500 + ($row['oasistype'] * 50);
                
                // Oasis are always attractive (no age penalty)
                $score = ($estimatedLoot * 10) - ($distance * 5);
                
                $targets[] = [
                    'kid' => $row['kid'],
                    'owner' => 0,
                    'name' => $row['name'],
                    'x' => $row['x'],
                    'y' => $row['y'],
                    'distance' => round($distance, 2),
                    'estimated_loot' => round($estimatedLoot),
                    'pop' => 0,
                    'score' => round($score),
                    'age_days' => 0,
                    'type' => 'oasis',
                ];
            }
        }
        
        if (empty($targets)) {
            return false;
        }
        
        // Sort by score (highest first)
        usort($targets, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return $targets;
    }

    /**
     * Check if NPC should raid based on personality and last raid time
     * 
     * @param int $uid User ID
     * @return bool True if should raid, false otherwise
     */
    public static function shouldRaid($uid)
    {
        $config = NpcConfig::getNpcConfig($uid);
        
        if (!$config) {
            return false;
        }
        
        // Get last raid time from npc_info
        $lastRaid = $config['npc_info']['last_raid_time'] ?? 0;
        
        // Get raid frequency for this personality
        $freq = NpcConfig::getRaidFrequency($config['npc_personality']);
        
        // Random cooldown between min and max
        $cooldown = mt_rand($freq['min'], $freq['max']);
        
        // Check if cooldown has passed
        return (time() - $lastRaid) >= $cooldown;
    }

    /**
     * Select troops to send for raid
     * 
     * @param int $kid Village ID
     * @param int $race Player race
     * @param string $personality NPC personality
     * @return array|false Array of units or false
     */
    public static function selectRaidTroops($kid, $race, $personality)
    {
        $db = DB::getInstance();
        
        // Get available units
        $units = $db->query("SELECT * FROM units WHERE kid=$kid")->fetch_assoc();
        
        if (!$units) {
            return false;
        }
        
        $selectedUnits = array_fill(1, 11, 0);
        
        // Select fast units for raiding based on race
        switch ($race) {
            case 1: // Romans
                $raiders = [2, 5, 6]; // Equites Imperatoris, Equites Caesaris, Equites Legati
                break;
            case 2: // Teutons
                $raiders = [3, 5, 6]; // Axeman, TK, Paladin
                break;
            case 3: // Gauls
                $raiders = [2, 4, 6]; // Swordsman, TT, Haeduan
                break;
            case 6: // Egyptians
                $raiders = [2, 5, 6]; // Anhur Guard, Resheph Chariot, etc
                break;
            case 7: // Huns
                $raiders = [1, 2, 5]; // Mercenary, Bowman, Steppe Rider
                break;
            default:
                $raiders = [1, 2, 3];
                break;
        }
        
        // Personality-based troop selection
        $aggressiveness = NpcConfig::getPersonalityStats($personality)['military_focus'] ?? 50;
        $sendPercent = ($aggressiveness / 100) * 0.7; // Send 30-70% of available troops
        
        $totalSent = 0;
        foreach ($raiders as $unitNr) {
            $available = $units['u' . $unitNr] ?? 0;
            
           if ($available > 0) {
                $toSend = max(1, ceil($available * $sendPercent));
                $selectedUnits[$unitNr] = min($toSend, $available);
                $totalSent += $selectedUnits[$unitNr];
            }
        }
        
        // If no troops selected, return false
        if ($totalSent == 0) {
            return false;
        }
        
        return $selectedUnits;
    }

    /**
     * Send a raid to a target village
     * 
     * @param int $fromKid Attacker village ID
     * @param int $toKid Target village ID
     * @param int $uid Attacker user ID
     * @param int $race Attacker race
     * @param string $personality Attacker personality
     * @return bool Success
     */
    public static function sendRaid($fromKid, $toKid, $uid, $race, $personality)
    {
        $db = DB::getInstance();
        
        // **Farm-list ONLY for NPCs (no fallback)**
        $farmListId = $db->fetchScalar("SELECT id FROM farmlist WHERE kid=$fromKid AND owner=$uid LIMIT 1");
        
        if (!$farmListId) {
            // **NEW: Auto-create farm-list for this village**
            NpcLogger::log($uid, 'AUTO_CREATE_FARMLIST', "No farm-list found - auto-creating", [
                'village' => $fromKid
            ]);
            
            try {
                NpcConfig::createNpcFarmList($uid, $fromKid);
                
                // Fetch the newly created farm-list ID
                $farmListId = $db->fetchScalar("SELECT id FROM farmlist WHERE kid=$fromKid AND owner=$uid LIMIT 1");
                
                if ($farmListId) {
                    NpcLogger::log($uid, 'FARMLIST_CREATED', "Farm-list auto-created successfully", [
                        'list_id' => $farmListId,
                        'village' => $fromKid
                    ]);
                } else {
                    NpcLogger::log($uid, 'FARMLIST_FAILED', "Failed to create farm-list", [
                        'village' => $fromKid
                    ]);
                    return false;
                }
            } catch (\Exception $e) {
                NpcLogger::log($uid, 'FARMLIST_ERROR', "Exception creating farm-list: " . $e->getMessage(), [
                    'village' => $fromKid
                ]);
                return false;
            }
        }
        
        // Use the game's built-in farm-list batch-send method
        NpcLogger::log($uid, 'FARMLIST_RAID', "Sending farm-list raids", [
            'list_id' => $farmListId,
            'village' => $fromKid
        ]);
        
        $farmListModel = new \Model\FarmListModel();
        $sent = $farmListModel->autoRaidFarmList($farmListId, $uid, $fromKid);
        
        if ($sent > 0) {
            NpcLogger::log($uid, 'FARMLIST_SENT', "Farm-list raids sent", [
                'list_id' => $farmListId,
                'sent' => $sent
            ]);
            return true;
        } else {
            NpcLogger::log($uid, 'FARMLIST_NO_TROOPS', "No raids sent (need troops)", [
                'list_id' => $farmListId
            ]);
            return false;
        }
    }
    
    /**
     * OLD: Send single raid - REMOVED (farm-lists only now)
     */
    private static function sendSingleRaid($fromKid, $toKid, $uid, $race, $personality)
    {
        $db = DB::getInstance();
        
        // Get target info for logging (works for both villages and oasis)
        $targetInfo = $db->query("SELECT v.name, w.x, w.y 
                                  FROM vdata v 
                                  JOIN wdata w ON v.kid = w.id 
                                  WHERE v.kid=$toKid")->fetch_assoc();
        
        // If not found in vdata (e.g., oasis), check wdata directly
        if (!$targetInfo) {
            $targetInfo = $db->query("SELECT CONCAT('Oasis ', w.x, '|', w.y) as name, w.x, w.y
                                      FROM wdata w
                                      WHERE w.id=$toKid")->fetch_assoc();
        }
        
        // Fallback if still not found
        if (!$targetInfo) {
            NpcLogger::logFailure($uid, 'RAID', 'Target village/oasis not found', ['toKid' => $toKid]);
            return false;
        }
        
        // Calculate distance for logging
        $fromXY = \Game\Formulas::kid2xy($fromKid);
        $toXY = ['x' => $targetInfo['x'], 'y' => $targetInfo['y']];
        $distance = round(sqrt(pow($fromXY['x'] - $toXY['x'], 2) + pow($fromXY['y'] - $toXY['y'], 2)), 2);
        
        // Select troops
        $units = self::selectRaidTroops($fromKid, $race, $personality);
        
        if (!$units) {
            NpcLogger::logFailure($uid, 'RAID', 'No troops available for raiding');
            return false;
        }
        
        // Calculate travel time
        $calculator = new SpeedCalculator();
        $calculator->setFrom($fromKid);
        $calculator->setTo($toKid);
        
        $speeds = [];
        for ($i = 1; $i <= 10; $i++) {
            if ($units[$i] > 0) {
                $speeds[] = Formulas::uSpeed(nrToUnitId($i, $race));
            }
        }
        
        if (empty($speeds)) {
            return false;
        }
        
        $calculator->setMinSpeed(min($speeds));
        $calculator->setArtefactEffect(1); // No artifact effect for NPCs
        $travelTime = $calculator->calc();
        
        // Modify units in database
        $db = DB::getInstance();
        $modifyQuery = "UPDATE units SET ";
        $modifyParts = [];
        
        for ($i = 1; $i <= 10; $i++) {
            if ($units[$i] > 0) {
                $modifyParts[] = "u$i = u$i - {$units[$i]}";
            }
        }
        
        if (empty($modifyParts)) {
            return false;
        }
        
        $modifyQuery .= implode(", ", $modifyParts);
        $modifyQuery .= " WHERE kid=$fromKid";
        
        if (!$db->query($modifyQuery)) {
            return false;
        }
        
        // Add movement
        $move = new MovementsModel();
        $attack_type = 4; // Raid
        $start_time = miliseconds(true);
        $end_time = $start_time + (1000 * $travelTime);
        
        $result = $move->addMovement(
            $fromKid,
            $toKid,
            $race,
            $units,
            99, // ctar1 (random target)
            0,  // ctar2 (no second target)
            0,  // spy type
            0,  // redeploy hero
            0,  // mode
            $attack_type,
            $start_time,
            $end_time
        );
        
        if ($result) {
            // Update NPC info
            NpcConfig::incrementCounter($uid, 'raids_sent');
            NpcConfig::updateNpcInfo($uid, 'last_raid_time', time());
            
            // Log successful raid
            NpcLogger::logRaid($uid, $fromKid, $toKid, $targetInfo['name'], $distance, $units);
            
            return true;
        }
        
        return false;
    }

    /**
     * Main raid decision method for NPC
     * 
     * @param int $uid User ID
     * @param int $kid Village ID
     * @return bool True if raid was sent, false otherwise
     */
    public static function processRaid($uid, $kid)
    {
        error_log("[NPC_DEBUG] processRaid() called for uid=$uid, kid=$kid");
        
        // Check if should raid
        if (!self::shouldRaid($uid)) {
            error_log("[NPC_DEBUG] shouldRaid() returned FALSE - skipping raid");
            NpcLogger::log($uid, 'RAID_COOLDOWN', 'Raid on cooldown - waiting for interval', []);
            return false;
        }
        
        error_log("[NPC_DEBUG] shouldRaid() passed - proceeding with raid");
        
        // Get NPC config
        $config = NpcConfig::getNpcConfig($uid);
        
        if (!$config) {
            error_log("[NPC_DEBUG] getNpcConfig() returned NULL!");
            NpcLogger::logFailure($uid, 'RAID', 'No NPC config found');
            return false;
        }
        
        $db = DB::getInstance();
        $race = $db->fetchScalar("SELECT race FROM users WHERE id=$uid");
        
        error_log("[NPC_DEBUG] Finding targets for kid=$kid");
        
        // Find targets
        $targets = self::findTargets($kid, 20);
        
        if (!$targets || empty($targets)) {
            error_log("[NPC_DEBUG] No targets found!");
            NpcLogger::logFailure($uid, 'RAID', 'No valid targets found');
            return false;
        }
        
        error_log("[NPC_DEBUG] Found " . count($targets) . " targets");
        
        // Log target selection
        $target = $targets[0];
        NpcLogger::logTargetSelection($uid, count($targets), $target);
        
        error_log("[NPC_DEBUG] Calling sendRaid() to target kid={$target['kid']}");
        
        // Send raid
        $success = self::sendRaid($kid, $target['kid'], $uid, $race, $config['npc_personality']);
        
        if (!$success) {
            error_log("[NPC_DEBUG] sendRaid() returned FALSE!");
            NpcLogger::logFailure($uid, 'RAID', 'Failed to send raid (no troops or error)');
        } else {
            error_log("[NPC_DEBUG] sendRaid() SUCCESS!");
        }
        
        return $success;
    }

    /**
     * Get raid statistics for debugging
     * 
     * @param int $uid User ID
     * @return array Raid statistics
     */
    public static function getRaidStats($uid)
    {
        $config = NpcConfig::getNpcConfig($uid);
        
        if (!$config) {
            return ['error' => 'Not an NPC'];
        }
        
        $db = DB::getInstance();
        $kid = $db->fetchScalar("SELECT kid FROM users WHERE id=$uid");
        
        // Count ongoing raids
        $ongoingRaids = $db->fetchScalar("SELECT COUNT(id) FROM movement 
                                          WHERE kid=$kid AND attack_type=4");
        
        // Get last raid time
        $lastRaidTime = $config['npc_info']['last_raid_time'] ?? 0;
        $timeSinceLastRaid = $lastRaidTime > 0 ? time() - $lastRaidTime : 'Never';
        
        // Get raid frequency
        $freq = NpcConfig::getRaidFrequency($config['npc_personality']);
        $minHours = round($freq['min'] / 3600, 1);
        $maxHours = round($freq['max'] / 3600, 1);
        
        return [
            'personality' => $config['npc_personality'],
            'raids_sent' => $config['npc_info']['raids_sent'] ?? 0,
            'last_raid_time' => $lastRaidTime > 0 ? date('Y-m-d H:i:s', $lastRaidTime) : 'Never',
            'time_since_last_raid' => is_numeric($timeSinceLastRaid) ? round($timeSinceLastRaid / 3600, 1) . ' hours' : $timeSinceLastRaid,
            'raid_frequency' => "$minHours-$maxHours hours",
            'ongoing_raids' => $ongoingRaids,
            'should_raid_now' => self::shouldRaid($uid) ? 'YES' : 'NO',
        ];
    }
}
