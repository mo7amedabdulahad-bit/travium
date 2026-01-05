<?php

namespace Core;

use Core\Database\DB;
use function logError;

/**
 * World Wonder Spoiler Operations
 * Handles disruption tactics for spoiler alliances
 * Goal: Prevent contenders from winning, not necessarily win themselves
 */
class NpcWWSpoiler
{
    /**
     * Execute spoiler actions
     * Prioritize disruption over building own WW
     * 
     * @param array $npcRow NPC user row
     */
    public static function executeSpoilerActions($npcRow)
    {
        $db = DB::getInstance();
        
        // Get difficulty-based success rate
        $difficulty = $npcRow['npc_difficulty'] ?? 'Medium';
        $successRates = [
            'Easy' => 30,
            'Medium' => 50,
            'Hard' => 70
        ];
        $successRate = $successRates[$difficulty] ?? 50;
        
        // Randomly decide if spoiling attempt succeeds based on difficulty
        if (mt_rand(1, 100) > $successRate) {
            logError("NPC {$npcRow['id']}: Spoiling attempt skipped (difficulty check)");
            return;
        }
        
        // Prioritize targets
        $targets = self::prioritizeTargets($npcRow);
        
        if (empty($targets)) {
            logError("NPC {$npcRow['id']}: No spoiler targets available");
            return;
        }
        
        $target = $targets[0]; // Highest priority target
        
        // Execute appropriate disruption tactic
        self::executeDisruption($npcRow, $target);
    }
    
    /**
     * Prioritize disruption targets
     * 
     * @param array $npcRow NPC user row
     * @return array Sorted list of targets [{type, village_id, priority}]
     */
    private static function prioritizeTargets($npcRow)
    {
        $db = DB::getInstance();
        $allianceId = $npcRow['aid'];
        
        $targets = [];
        
        // Priority 1: WW villages under construction (highest priority)
        $wwVillages = $db->query("
            SELECT v.kid, v.owner, f.level, u.ww_operation_state
            FROM vdata v
            JOIN fdata f ON v.kid = f.kid
            JOIN users u ON v.owner = u.id
            WHERE f.type = 40
              AND u.aid != $allianceId
              AND u.ww_alliance_role = 'Contender'
            ORDER BY f.level DESC
            LIMIT 5
        ");
        
        while ($row = $wwVillages->fetch_assoc()) {
            $targets[] = [
                'type' => 'ww_village',
                'village_id' => (int)$row['kid'],
                'owner_id' => (int)$row['owner'],
                'priority' => 100 + (int)$row['level'], // Higher level = higher priority
                'ww_level' => (int)$row['level']
            ];
        }
        
        // Priority 2: Plan holders from contender alliances
        $planHolders = $db->query("
            SELECT v.kid, v.owner
            FROM vdata v
            JOIN users u ON v.owner = u.id
            WHERE u.aid != $allianceId
              AND u.ww_alliance_role = 'Contender'
              AND EXISTS (
                  SELECT 1 FROM fdata f2
                  WHERE f2.kid = v.kid 
                    AND f2.type = 27
                    AND f2.level >= 10
              )
            LIMIT 10
        ");
        
        while ($row = $planHolders->fetch_assoc()) {
            $targets[] = [
                'type' => 'plan_holder',
                'village_id' => (int)$row['kid'],
                'owner_id' => (int)$row['owner'],
                'priority' => 80
            ];
        }
        
        // Priority 3: Resource support villages near WW
        // Villages that are likely sending resources to WW
        foreach ($targets as $wwTarget) {
            if ($wwTarget['type'] === 'ww_village') {
                $supporters = self::findSupportVillages($wwTarget['owner_id'], $wwTarget['village_id']);
                foreach ($supporters as $supporter) {
                    $targets[] = [
                        'type' => 'resource_supporter',
                        'village_id' => $supporter,
                        'owner_id' => $wwTarget['owner_id'],
                        'priority' => 60
                    ];
                }
            }
        }
        
        // Sort by priority descending
        usort($targets, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        return $targets;
    }
    
    /**
     * Find villages likely supporting WW with resources
     */
    private static function findSupportVillages($ownerId, $wwVillageId)
    {
        $db = DB::getInstance();
        
        // Find other villages of same owner close to WW
        $result = $db->query("
            SELECT v.kid
            FROM vdata v
            WHERE v.owner = $ownerId
              AND v.kid != $wwVillageId
            LIMIT 5
        ");
        
        $supporters = [];
        while ($row = $result->fetch_assoc()) {
            $supporters[] = (int)$row['kid'];
        }
        
        return $supporters;
    }
    
    /**
     * Execute disruption tactic against target
     * 
     * @param array $npcRow NPC user row
     * @param array $target Target info array
     */
    private static function executeDisruption($npcRow, $target)
    {
        $db = DB::getInstance();
        
        // Get war village
        $warVillageId = $npcRow['war_village_id'];
        if (!$warVillageId) {
            $warVillageId = NpcWarVillageManager::updateWarVillage($npcRow['id']);
        }
        
        if (!$warVillageId) {
            logError("NPC {$npcRow['id']}: No war village for spoiling");
            return;
        }
        
        // Check distance
        $distance = self::calculateDistance($warVillageId, $target['village_id']);
        if ($distance > 50) {
            logError("NPC {$npcRow['id']}: Spoiler target {$target['village_id']} out of range");
            return;
        }
        
        // Load template and policy
        $template = NpcConfig::getPersonalityTemplate($npcRow['npc_personality'] ?? 'Balanced', 'Late');
        $policy = NpcConfig::getDifficultyPolicy($npcRow['npc_difficulty'] ?? 'Medium');
        
        if (!$template || !$policy) return;
        
        // Execute tactic based on target type
        switch ($target['type']) {
            case 'ww_village':
                self::harassWW($warVillageId, $target, $template, $policy, $npcRow);
                break;
                
            case 'plan_holder':
                self::stealPlans($warVillageId, $target, $template, $policy, $npcRow);
                break;
                
            case 'resource_supporter':
                self::denyResources($warVillageId, $target, $template, $policy, $npcRow);
                break;
        }
    }
    
    /**
     * Harass WW village with waves
     */
    private static function harassWW($fromVillageId, $target, $template, $policy, $npcRow)
    {
        // Check cooldown (don't spam)
        $memory = json_decode($npcRow['npc_memory_json'] ?? '{}', true);
        $lastHarassment = $memory['last_ww_harassment'] ?? 0;
        
        if ((time() - $lastHarassment) < (2 * 3600)) {
            // Cooldown: 2 hours minimum between waves
            return;
        }
        
        // Send attack wave
        NpcAttackManager::executeAttack($fromVillageId, $target['village_id'], $template, $policy);
        
        // Update memory
        $memory['last_ww_harassment'] = time();
        $db = DB::getInstance();
        $db->query("
            UPDATE users 
            SET npc_memory_json = '" . $db->real_escape_string(json_encode($memory)) . "'
            WHERE id = {$npcRow['id']}
        ");
        
        logError("NPC war village $fromVillageId: Harassment wave sent to WW {$target['village_id']} (Level {$target['ww_level']})");
    }
    
    /**
     * Attempt to steal plans from treasury
     */
    private static function stealPlans($fromVillageId, $target, $template, $policy, $npcRow)
    {
        // Treasury raid
        NpcRaidManager::executeRaid($fromVillageId, $target['village_id'], $template, $policy);
        logError("NPC war village $fromVillageId: Plan theft attempt on {$target['village_id']}");
    }
    
    /**
     * Deny resources to WW by raiding supporters
     */
    private static function denyResources($fromVillageId, $target, $template, $policy, $npcRow)
    {
        // Regular farm raid
        NpcRaidManager::executeRaid($fromVillageId, $target['village_id'], $template, $policy);
        logError("NPC war village $fromVillageId: Resource denial raid on supporter {$target['village_id']}");
    }
    
    /**
     * Calculate distance between two villages
     */
    private static function calculateDistance($villageId1, $villageId2)
    {
        $db = DB::getInstance();
        
        $coords1 = $db->query("SELECT x, y FROM wdata WHERE id = $villageId1")->fetch_assoc();
        $coords2 = $db->query("SELECT x, y FROM wdata WHERE id = $villageId2")->fetch_assoc();
        
        if (!$coords1 || !$coords2) return PHP_INT_MAX;
        
        return max(
            abs($coords1['x'] - $coords2['x']),
            abs($coords1['y'] - $coords2['y'])
        );
    }
}
