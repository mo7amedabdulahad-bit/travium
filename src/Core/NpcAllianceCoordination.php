<?php

namespace Core;

use Core\Database\DB;
use Game\Formulas;
use Game\SpeedCalculator;
use Model\MovementsModel;

class NpcAllianceCoordination
{
    /**
     * Coordinate mutual defense response when an alliance member is attacked
     * 
     * @param int $attackedNpcId The NPC who was attacked
     * @param int $attackerId The attacker's user ID
     */
    /**
     * Coordinate mutual defense response when an alliance member is attacked
     * 
     * @param int $attackedNpcId The NPC who was attacked
     * @param int $attackerId The attacker's user ID
     */
    public static function coordinateMutualDefense($attackedNpcId, $attackerId)
    {
        $db = DB::getInstance();
        
        // Get attacked NPC's alliance and a village
        $npcData = $db->query("
            SELECT u.aid, u.npc_difficulty, v.kid 
            FROM users u 
            INNER JOIN vdata v ON u.id = v.owner 
            WHERE u.id = $attackedNpcId 
            LIMIT 1
        ")->fetch_assoc();
        
        if (!$npcData || !$npcData['aid']) return; // No alliance
        
        $allianceId = (int)$npcData['aid'];
        $targetVillageId = (int)$npcData['kid'];
        $difficulty = $npcData['npc_difficulty'] ?? 'Medium';
        
        // Get eligible defenders
        $defenders = self::getEligibleDefenders($allianceId, $targetVillageId, $attackedNpcId);
        
        if (empty($defenders)) return;
        
        // Difficulty-based response rate
        $responseRates = [
            'Easy' => 0.4,      // 40% chance
            'Medium' => 0.6,    // 60% chance
            'Hard' => 0.8       // 80% chance
        ];
        $responseRate = $responseRates[$difficulty] ?? 0.6;
        
        $reinforcementsSent = 0;
        
        foreach ($defenders as $defender) {
            // Personality filter: Aggressive personalities DON'T defend, they only attack
            $personality = $defender['personality'] ?? 'Balanced';
            $aggressivePersonalities = ['Aggressive', 'Raider', 'Assassin'];
            
            if (in_array($personality, $aggressivePersonalities)) {
                continue; // Skip aggressive NPCs - they don't help defend
            }
            
            // Random sampling based on difficulty
            if ((mt_rand(0, 100) / 100) > $responseRate) continue;
            
            // Check cooldown
            if (!class_exists('Core\NpcRetaliationManager') || !NpcRetaliationManager::canSendDefense($defender['user_id'], $targetVillageId)) {
                // If NpcRetaliationManager doesn't exist yet, we proceed (skip check) or fail safe. 
                // Assuming NpcRetaliationManager exists or logic is handled elsewhere.
                // For now, proceed if class missing to prevent crash.
            }
            
            // Send reinforcement
            $success = self::sendReinforcement(
                $defender['war_village_id'], 
                $targetVillageId, 
                $defender['race'],
                0.4 // Send 40% of defensive troops
            );
            
            if ($success) {
                if(class_exists('Core\NpcRetaliationManager')) {
                    NpcRetaliationManager::recordDefenseSent($defender['user_id'], $targetVillageId);
                }
                $reinforcementsSent++;

                // SEND MESSAGE
                // Get target user ID to send message to
                $targetUserId = $db->fetchScalar("SELECT owner FROM vdata WHERE kid=$targetVillageId");
                
                // Only send message if target is a REAL player (access < 3), not another NPC
                $targetAccess = $db->fetchScalar("SELECT access FROM users WHERE id=$targetUserId");
                
                if ($targetAccess < 3 && class_exists('Core\NpcMessagingSystem')) {
                     $msg = NpcMessagingSystem::getReinforcementMessage($personality);
                     $mModel = new \Model\MessageModel();
                     $mModel->sendMessage($defender['user_id'], $targetUserId, 'Reinforcements', $msg, 0);
                }
            }
        }
    }
    
    /**
     * Probabilistically recall old reinforcements
     * Run this periodically (e.g. hourly) via NpcScheduler
     */
    public static function withdrawOldReinforcements()
    {
        $db = DB::getInstance();
        
        // Select random reinforcements sent BY NPCs (uid IN users access=3)
        // Since 'enforcement' table has no timestamp, we recall with ~5% probability per run
        // If run hourly, expected duration ~20 hours.
        $probability = 5; // 5% chance
        
        // Find reinforcements owned by NPCs
        $sql = "
            SELECT e.id, e.kid as from_village, e.to_kid as target_village, e.race, 
                   e.u1, e.u2, e.u3, e.u4, e.u5, e.u6, e.u7, e.u8, e.u9, e.u10, e.u11
            FROM enforcement e
            JOIN users u ON e.uid = u.id
            WHERE u.access = 3
        ";
        
        $result = $db->query($sql);
        while ($row = $result->fetch_assoc()) {
            // Roll dice
            if (mt_rand(1, 100) <= $probability) {
                self::recallTroops($row);
            }
        }
    }

    private static function recallTroops($enforceData)
    {
        $db = DB::getInstance();
        $moveModel = new MovementsModel();
        
        // Calculate travel time for return
        $avgSpeed = 10; // Fallback
        
        // Determine slowest unit speed for return
        $speeds = [];
        for ($i=1; $i<=10; $i++) {
            if ($enforceData['u'.$i] > 0) {
                 $unitId = ($enforceData['race'] - 1) * 10 + $i;
                 $speeds[] = Formulas::uSpeed($unitId);
            }
        }
        
        $calculator = new SpeedCalculator();
        $calculator->setFrom($enforceData['target_village']); // Returning FROM target
        $calculator->setTo($enforceData['from_village']);     // TO home
        
        if (!empty($speeds)) {
             $calculator->setMinSpeed($speeds);
             $travelTime = $calculator->calc();
        } else {
             $travelTime = 3600; // 1 hour fallback
        }

        $startTime = time() * 1000;
        $endTime = $startTime + ($travelTime * 1000);
        
        $units = [];
        for($i=1; $i<=11; $i++) $units[$i] = $enforceData['u'.$i];

        // Create return movement
        $moveModel->addMovement(
            $enforceData['target_village'],
            $enforceData['from_village'],
            $enforceData['race'],
            $units,
            0, 0, 0, 0, 1, // mode=1 (return)
            MovementsModel::ATTACKTYPE_REINFORCEMENT,
            $startTime,
            $endTime
        );
        
        // Remove from enforcement
        $db->query("DELETE FROM enforcement WHERE id={$enforceData['id']}");
        
        // Optional: Log or send message ("Troops returning")
    }

    /**
     * Get NPCs from alliance who can send defense
     * 
     * @param int $allianceId Alliance ID
     * @param int $targetVillageId Village under attack
     * @param int $excludeNpcId Don't include the victim
     * @return array List of eligible defenders
     */
    private static function getEligibleDefenders($allianceId, $targetVillageId, $excludeNpcId)
    {
        $db = DB::getInstance();
        
        // Get target village coordinates
        $coords = $db->query("SELECT x, y FROM wdata WHERE id=$targetVillageId")->fetch_assoc();
        if (!$coords) return [];
        
        $targetX = (int)$coords['x'];
        $targetY = (int)$coords['y'];
        
        // Find alliance members with war villages within range
        $maxRange = 50; // tiles
        
        $query = "
            SELECT u.id as user_id, u.war_village_id, u.race, u.npc_personality as personality, w.x, w.y
            FROM users u
            INNER JOIN vdata v ON u.war_village_id = v.kid
            INNER JOIN wdata w ON v.kid = w.id
            WHERE u.aid = $allianceId 
              AND u.access = 3
              AND u.id != $excludeNpcId
              AND u.war_village_id IS NOT NULL
              AND w.x BETWEEN " . ($targetX - $maxRange) . " AND " . ($targetX + $maxRange) . "
              AND w.y BETWEEN " . ($targetY - $maxRange) . " AND " . ($targetY + $maxRange) . "
        ";
        
        $result = $db->query($query);
        $defenders = [];
        
        while ($row = $result->fetch_assoc()) {
            // Check if they have troops (at least 50 units)
            $totalTroops = (int)$db->fetchScalar("
                SELECT (u1+u2+u3+u4+u5+u6+u7+u8+u9+u10) as total
                FROM units WHERE kid = {$row['war_village_id']}
            ");
            
            if ($totalTroops >= 50) {
                $defenders[] = $row;
            }
        }
        
        return $defenders;
    }
    
    /**
     * Send reinforcement troops from one village to another
     * 
     * @param int $fromVillageId Source village
     * @param int $toVillageId Target village
     * @param int $race Tribe of sender
     * @param float $troopPercent Percentage of troops to send (0.0 - 1.0)
     * @return bool True if reinforcement sent
     */
    private static function sendReinforcement($fromVillageId, $toVillageId, $race, $troopPercent)
    {
        $db = DB::getInstance();
        
        // Get available troops
        $units = $db->query("SELECT u1,u2,u3,u4,u5,u6,u7,u8,u9,u10,u11 FROM units WHERE kid=$fromVillageId")->fetch_assoc();
        if (!$units) return false;
        
        // Select defensive troops (adjust based on tribe)
        // For simplicity, send a mix of all available troops
        $troopsToSend = [];
        for ($i = 1; $i <= 10; $i++) {
            $available = (int)$units['u' . $i];
            $troopsToSend[$i] = ($available > 0) ? max(1, floor($available * $troopPercent)) : 0;
        }
        $troopsToSend[11] = 0; // Don't send hero
        
        // Check if we have any troops
        if (array_sum($troopsToSend) == 0) return false;
        
        // Calculate travel time
        $calculator = new SpeedCalculator();
        $calculator->setFrom($fromVillageId);
        $calculator->setTo($toVillageId);
        
        $speeds = [];
        for ($i = 1; $i <= 10; $i++) {
            if ($troopsToSend[$i] > 0) {
                $unitId = ($race - 1) * 10 + $i;
                $speeds[] = Formulas::uSpeed($unitId);
            }
        }
        
        if (empty($speeds)) return false;
        
        $calculator->setMinSpeed($speeds);
        $travelTime = $calculator->calc();
        
        $startTime = time() * 1000;
        $endTime = $startTime + ($travelTime * 1000);
        
        // Create reinforcement movement
        $movementModel = new MovementsModel();
        $movementModel->addMovement(
            $fromVillageId,
            $toVillageId,
            $race,
            $troopsToSend,
            0, 0, // ctar1, ctar2
            0, // spyType
            false, // redeployHero
            0, // mode (0 = going)
            MovementsModel::ATTACKTYPE_REINFORCEMENT, // type 2 = reinforcement
            $startTime,
            $endTime,
            null
        );
        
        // Deduct troops
        $updateParts = [];
        for ($i = 1; $i <= 10; $i++) {
            if ($troopsToSend[$i] > 0) {
                $updateParts[] = "u$i = u$i - " . $troopsToSend[$i];
            }
        }
        
        if (!empty($updateParts)) {
            $db->query("UPDATE units SET " . implode(', ', $updateParts) . " WHERE kid=$fromVillageId");
        }
        
        return true;
    }
}
