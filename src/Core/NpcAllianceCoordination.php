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
            'Easy' => 0.4,
            'Medium' => 0.6,
            'Hard' => 0.8
        ];
        $responseRate = $responseRates[$difficulty] ?? 0.6;
        
        $reinforcementsSent = 0;
        
        foreach ($defenders as $defender) {
            // Random sampling based on difficulty
            if ((mt_rand(0, 100) / 100) > $responseRate) continue;
            
            // Check cooldown
            if (!NpcRetaliationManager::canSendDefense($defender['user_id'], $targetVillageId)) {
                continue;
            }
            
            // Send reinforcement
            $success = self::sendReinforcement(
                $defender['war_village_id'], 
                $targetVillageId, 
                $defender['race'],
                0.4 // Send 40% of defensive troops
            );
            
            if ($success) {
                NpcRetaliationManager::recordDefenseSent($defender['user_id'], $targetVillageId);
                $reinforcementsSent++;
            }
        }
        
        // Log for debugging (optional)
        // logError("Alliance Defense: $reinforcementsSent NPCs sent reinforcements to village $targetVillageId");
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
            SELECT u.id as user_id, u.war_village_id, u.race, w.x, w.y
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
        
        $startTime = time();
        $endTime = $startTime + $travelTime;
        
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
