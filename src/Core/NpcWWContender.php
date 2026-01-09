<?php

namespace Core;

use Core\Database\DB;
use function logError;

/**
 * World Wonder Contender Operations
 * Handles plan capture and WW construction for contender alliances
 */
class NpcWWContender
{
    /**
     * Execute plan capture operation
     * Multi-stage coordinated attack to secure WW plans
     * 
     * @param array $npcRow NPC user row
     */
    public static function executePlanCapture($npcRow)
    {
        $db = DB::getInstance();
        
        // Check if NPC already has plans
        if (self::hasWWPlans($npcRow['id'])) {
            // Transition to PlanSecured state
            NpcWWOperations::transitionState($npcRow['id'], 'PlanHunting', 'PlanSecured');
            return;
        }
        
        // Identify plan holders (enemy treasury villages with plans)
        $planHolders = self::identifyPlanHolders($npcRow['aid']);
        
        if (empty($planHolders)) {
            logError("NPC {$npcRow['id']}: No plan holders found");
            return;
        }
        
        // Select closest plan holder
        $warVillageId = $npcRow['war_village_id'];
        if (!$warVillageId) {
            $warVillageId = NpcWarVillageManager::updateWarVillage($npcRow['id']);
        }
        
        $target = self::selectClosestTarget($warVillageId, $planHolders);
        
        if (!$target) {
            logError("NPC {$npcRow['id']}: No reachable plan holders");
            return;
        }
        
        // Execute plan capture raid
        self::conductPlanRaid($warVillageId, $target, $npcRow);
    }
    
    /**
     * Identify villages holding WW plans
     * 
     * @param int $allianceId NPC's alliance ID (to exclude own alliance)
     * @return array List of village IDs with plans
     */
    private static function identifyPlanHolders($allianceId)
    {
        $db = DB::getInstance();
        
        // Simplified: High-level embassy indicates plan storage capability
        // Much faster than checking all 40 building slots for Treasury
        $result = $db->query("
            SELECT DISTINCT v.kid
            FROM vdata v
            JOIN users u ON v.owner = u.id
            JOIN fdata f ON v.kid = f.kid
            WHERE u.aid != $allianceId
              AND u.aid > 0
              AND f.embassy >= 10
            ORDER BY v.pop DESC
            LIMIT 20
        ");
        
        $holders = [];
        while ($row = $result->fetch_assoc()) {
            $holders[] = (int)$row['kid'];
        }
        
        return $holders;
    }
    
    /**
     * Select closest target from list
     */
    private static function selectClosestTarget($fromVillageId, $targets)
    {
        $db = DB::getInstance();
        
        $fromCoords = $db->query("SELECT x, y FROM wdata WHERE id = $fromVillageId")->fetch_assoc();
        if (!$fromCoords) return null;
        
        $closest = null;
        $minDistance = PHP_INT_MAX;
        
        foreach ($targets as $targetId) {
            $targetCoords = $db->query("SELECT x, y FROM wdata WHERE id = $targetId")->fetch_assoc();
            if (!$targetCoords) continue;
            
            $distance = max(
                abs($targetCoords['x'] - $fromCoords['x']),
                abs($targetCoords['y'] - $fromCoords['y'])
            );
            
            if ($distance < $minDistance && $distance <= 50) {
                $minDistance = $distance;
                $closest = $targetId;
            }
        }
        
        return $closest;
    }
    
    /**
     * Conduct plan capture raid
     * Sends coordinated waves to capture plans from treasury
     */
    private static function conductPlanRaid($fromVillageId, $targetId, $npcRow)
    {
        $db = DB::getInstance();
        
        // Load template and policy for troop calculations
        $template = NpcConfig::getPersonalityTemplate($npcRow['npc_personality'] ?? 'Balanced', 'Late');
        $policy = NpcConfig::getDifficultyPolicy($npcRow['npc_difficulty'] ?? 'Medium');
        
        if (!$template || !$policy) {
            logError("NPC {$npcRow['id']}: Cannot load template/policy for plan raid");
            return;
        }
        
        // Step 1: Scout if not recently scouted
        $lastScoutTime = self::getLastScoutTime($targetId);
        if (!$lastScoutTime || (time() - $lastScoutTime) > 3600) {
            NpcScoutingManager::executeScouts($fromVillageId, $targetId, $template, $policy);
            logError("NPC war village $fromVillageId: Scouting plan holder $targetId");
        }
        
        // Step 2: Full attack wave to clear defense
        NpcAttackManager::executeAttack($fromVillageId, $targetId, $template, $policy);
        logError("NPC war village $fromVillageId: Attacking plan holder $targetId");
        
        // Step 3: Treasury raid (farm raid for simplicity)
        // In a full implementation, this would be a special raid type
        NpcRaidManager::executeRaid($fromVillageId, $targetId, $template, $policy);
        logError("NPC war village $fromVillageId: Treasury raid on $targetId");
    }
    
    /**
     * Check if NPC has WW plans
     */
    private static function hasWWPlans($npcId)
    {
        $db = DB::getInstance();
        
        // Simplified: Check if NPC has high-level embassy (plan storage capability)
        $count = (int)$db->fetchScalar("
            SELECT COUNT(*)
            FROM vdata v
            JOIN fdata f ON v.kid = f.kid
            WHERE v.owner = $npcId
              AND f.embassy >= 10
        ");
        
        return $count > 0;
    }
    
    /**
     * Get last scout time for target village
     */
    private static function getLastScoutTime($targetId)
    {
        $db = DB::getInstance();
        
        // Check movement table for recent scouts
        $result = $db->query("
            SELECT MAX(end_time) as last_scout
            FROM movement
            WHERE to_kid = $targetId
              AND attack_type = 1
              AND end_time < " . (time() * 1000)
        );
        
        $row = $result->fetch_assoc();
        return $row['last_scout'] ? strtotime($row['last_scout']) : null;
    }
    
    /**
     * Start WW construction after securing plans
     * 
     * @param array $npcRow NPC user row
     */
    public static function startWWConstruction($npcRow)
    {
        $db = DB::getInstance();
        
        // Find or designate WW village
        $wwVillageId = self::getOrCreateWWVillage($npcRow['id']);
        
        if (!$wwVillageId) {
            logError("NPC {$npcRow['id']}: Cannot find/create WW village");
            return;
        }
        
        // Check if WW already exists
        $hasWW = $db->fetchScalar("
            SELECT COUNT(*) FROM fdata 
            WHERE kid = $wwVillageId AND type = 40
        ");
        
        if ($hasWW > 0) {
            // WW already building, transition to WWBuilding state
            NpcWWOperations::transitionState($npcRow['id'], 'PlanSecured', 'WWBuilding');
            logError("NPC {$npcRow['id']}: WW construction in progress");
            return;
        }
        
        // Queue WW construction
        // Note: This would integrate with your building queue system
        // WW building is now handled by AI.php integration in NpcScriptEngine
        
        NpcWWOperations::transitionState($npcRow['id'], 'PlanSecured', 'WWBuilding');
        logError("NPC {$npcRow['id']}: Started WW construction at village $wwVillageId");
    }
    
    /**
     * Get or create designated WW village
     * Typically the capital or a strategically placed village
     */
    private static function getOrCreateWWVillage($npcId)
    {
        $db = DB::getInstance();
        
        // Check for existing WW
        $existing = $db->fetchScalar("
            SELECT v.kid FROM vdata v
            WHERE v.owner = $npcId AND v.isWW = 1
            LIMIT 1
        ");
        
        if ($existing) return $existing;
        
        // Use capital as WW village
        $capital = $db->fetchScalar("
            SELECT kid FROM vdata 
            WHERE owner = $npcId AND capital = 1
            LIMIT 1
        ");
        
        return $capital;
    }
}
