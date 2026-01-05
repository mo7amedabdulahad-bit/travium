<?php

namespace Core;

use Core\Database\DB;
use function logError;

/**
 * World Wonder Operations State Machine
 * Manages NPC progression through WW endgame phases
 */
class NpcWWOperations
{
    /**
     * Handle the WWPlanReleased event - all eligible NPCs enter plan hunting mode
     * 
     * @param int $serverId Server/world ID
     */
    public static function onPlansReleased($serverId)
    {
        $db = DB::getInstance();
        
        // Get all NPCs in alliances (not solo players)
        $result = $db->query("
            SELECT u.id, u.aid, a.tag as alliance_name
            FROM users u
            LEFT JOIN alidata a ON u.aid = a.id
            WHERE u.access = 3 
              AND u.aid > 0
              AND u.ww_operation_state = 'Idle'
        ");
        
        if (!$result || $result->num_rows === 0) return;
        
        // Designate alliance roles based on server settings or random distribution
        // For simplicity: Top alliance by population = Contender, others = Spoiler
        $alliances = [];
        while ($row = $result->fetch_assoc()) {
            $allianceId = (int)$row['aid'];
            if (!isset($alliances[$allianceId])) {
                $alliances[$allianceId] = [
                    'id' => $allianceId,
                    'name' => $row['alliance_name'],
                    'members' => []
                ];
            }
            $alliances[$allianceId]['members'][] = $row['id'];
        }
        
        // Get alliance populations to determine contender
        foreach ($alliances as $aid => &$data) {
            $pop = (int)$db->fetchScalar("
                SELECT SUM(pop) FROM vdata WHERE owner IN (
                    SELECT id FROM users WHERE aid = $aid
                )
            ");
            $data['population'] = $pop;
        }
        
        // Sort by population descending
        usort($alliances, function($a, $b) {
            return $b['population'] - $a['population'];
        });
        
        // Top alliance becomes contender, rest are spoilers
        foreach ($alliances as $index => $alliance) {
            $role = ($index === 0) ? 'Contender' : 'Spoiler';
            $memberIds = implode(',', $alliance['members']);
            
            $db->query("
                UPDATE users 
                SET ww_alliance_role = '$role',
                    ww_operation_state = 'PlanHunting'
                WHERE id IN ($memberIds)
            ");
            
            logError("WW Plans Released: Alliance {$alliance['name']} ({$alliance['id']}) assigned role: $role");
        }
    }
    
    /**
     * Progress WW operation for a single NPC
     * 
     * @param array $npcRow NPC user row
     */
    public static function progressWWOperation($npcRow)
    {
        $state = $npcRow['ww_operation_state'] ?? 'Idle';
        $role = $npcRow['ww_alliance_role'] ?? 'Neutral';
        
        // Neutral NPCs don't participate in WW race
        if ($role === 'Neutral' || $state === 'Idle') {
            return;
        }
        
        switch ($state) {
            case 'PlanHunting':
                self::handlePlanHunting($npcRow);
                break;
                
            case 'PlanSecured':
                self::handlePlanSecured($npcRow);
                break;
                
            case 'WWBuilding':
                self::handleWWBuilding($npcRow);
                break;
                
            case 'WWDefending':
                self::handleWWDefending($npcRow);
                break;
                
            case 'OperationFailed':
                // NPC has given up or been defeated
                break;
        }
    }
    
    /**
     * Handle plan hunting phase
     */
    private static function handlePlanHunting($npcRow)
    {
        if ($npcRow['ww_alliance_role'] === 'Contender') {
            // Contenders actively hunt plans
            NpcWWContender::executePlanCapture($npcRow);
        } else {
            // Spoilers try to steal/deny plans
            NpcWWSpoiler::executeSpoilerActions($npcRow);
        }
    }
    
    /**
     * Handle plan secured phase - start WW construction
     */
    private static function handlePlanSecured($npcRow)
    {
        if ($npcRow['ww_alliance_role'] === 'Contender') {
            NpcWWContender::startWWConstruction($npcRow);
        }
    }
    
    /**
     * Handle WW building phase - defend and build
     */
    private static function handleWWBuilding($npcRow)
    {
        $db = DB::getInstance();
        
        // Check if WW reached level 50 (transition to defending)
        $wwVillageId = self::getWWVillageId($npcRow['id']);
        if ($wwVillageId) {
            $wwLevel = self::getWWLevel($wwVillageId);
            if ($wwLevel >= 50) {
                self::transitionState($npcRow['id'], 'WWBuilding', 'WWDefending');
            }
        }
    }
    
    /**
     * Handle WW defending phase - full defensive mode
     */
    private static function handleWWDefending($npcRow)
    {
        // Defense is handled by event system (WWUnderAttack events)
        // Check for victory condition
        $db = DB::getInstance();
        $wwVillageId = self::getWWVillageId($npcRow['id']);
        
        if ($wwVillageId) {
            $wwLevel = self::getWWLevel($wwVillageId);
            if ($wwLevel >= 100) {
                logError("NPC {$npcRow['id']} has won the game with WW level 100!");
                // Victory state - could trigger server end
            }
        }
    }
    
    /**
     * Handle WW defeat - fallback logic
     * 
     * @param int $npcId NPC user ID
     */
    public static function handleWWDefeat($npcId)
    {
        $db = DB::getInstance();
        $npc = $db->query("SELECT id, npc_difficulty FROM users WHERE id = $npcId")->fetch_assoc();
        
        if (!$npc) return;
        
        $difficulty = $npc['npc_difficulty'] ?? 'Medium';
        
        switch ($difficulty) {
            case 'Easy':
                // Give up, switch to spoiler mode
                $db->query("
                    UPDATE users 
                    SET ww_operation_state = 'OperationFailed',
                        ww_alliance_role = 'Spoiler'
                    WHERE id = $npcId
                ");
                logError("NPC $npcId (Easy) gave up after WW defeat, switching to spoiler mode");
                break;
                
            case 'Medium':
                // 12-hour rebuild timer, try again once
                $memory = json_decode($npc['npc_memory_json'] ?? '{}', true);
                $attempts = $memory['ww_defeat_count'] ?? 0;
                
                if ($attempts < 1) {
                    $memory['ww_defeat_count'] = $attempts + 1;
                    $memory['ww_rebuild_after'] = time() + (12 * 3600);
                    
                    $db->query("
                        UPDATE users 
                        SET ww_operation_state = 'Idle',
                            npc_memory_json = '" . $db->real_escape_string(json_encode($memory)) . "'
                        WHERE id = $npcId
                    ");
                    logError("NPC $npcId (Medium) will attempt WW rebuild in 12 hours");
                } else {
                    $db->query("UPDATE users SET ww_operation_state = 'OperationFailed' WHERE id = $npcId");
                    logError("NPC $npcId (Medium) exhausted WW attempts, giving up");
                }
                break;
                
            case 'Hard':
                // Immediate aggressive rebuild
                $db->query("
                    UPDATE users 
                    SET ww_operation_state = 'PlanHunting'
                    WHERE id = $npcId
                ");
                logError("NPC $npcId (Hard) immediately regrouping for WW rebuild");
                break;
        }
    }
    
    /**
     * Transition NPC between WW operation states
     */
    public static function transitionState($npcId, $fromState, $toState)
    {
        $db = DB::getInstance();
        $db->query("
            UPDATE users 
            SET ww_operation_state = '$toState'
            WHERE id = $npcId AND ww_operation_state = '$fromState'
        ");
        
        logError("NPC $npcId transitioned from $fromState to $toState");
    }
    
    /**
     * Get WW village ID for an NPC
     */
    private static function getWWVillageId($npcId)
    {
        $db = DB::getInstance();
        // Use existing isWW flag for instant lookup
        return $db->fetchScalar("
            SELECT kid
            FROM vdata
            WHERE owner = $npcId AND isWW = 1
            LIMIT 1
        ");
    }
    
    /**
     * Get current WW level
     */
    private static function getWWLevel($villageId)
    {
        $db = DB::getInstance();
        // Use CASE to extract WW level from whichever slot has type 40
        return (int)$db->fetchScalar("
            SELECT CASE
                WHEN f1t=40 THEN f1 WHEN f2t=40 THEN f2 WHEN f3t=40 THEN f3 WHEN f4t=40 THEN f4 WHEN f5t=40 THEN f5
                WHEN f6t=40 THEN f6 WHEN f7t=40 THEN f7 WHEN f8t=40 THEN f8 WHEN f9t=40 THEN f9 WHEN f10t=40 THEN f10
                WHEN f11t=40 THEN f11 WHEN f12t=40 THEN f12 WHEN f13t=40 THEN f13 WHEN f14t=40 THEN f14 WHEN f15t=40 THEN f15
                WHEN f16t=40 THEN f16 WHEN f17t=40 THEN f17 WHEN f18t=40 THEN f18 WHEN f19t=40 THEN f19 WHEN f20t=40 THEN f20
                WHEN f21t=40 THEN f21 WHEN f22t=40 THEN f22 WHEN f23t=40 THEN f23 WHEN f24t=40 THEN f24 WHEN f25t=40 THEN f25
                WHEN f26t=40 THEN f26 WHEN f27t=40 THEN f27 WHEN f28t=40 THEN f28 WHEN f29t=40 THEN f29 WHEN f30t=40 THEN f30
                WHEN f31t=40 THEN f31 WHEN f32t=40 THEN f32 WHEN f33t=40 THEN f33 WHEN f34t=40 THEN f34 WHEN f35t=40 THEN f35
                WHEN f36t=40 THEN f36 WHEN f37t=40 THEN f37 WHEN f38t=40 THEN f38 WHEN f39t=40 THEN f39 WHEN f40t=40 THEN f40
                ELSE 0
            END AS ww_level
            FROM fdata
            WHERE kid = $villageId
        ");
    }
}
