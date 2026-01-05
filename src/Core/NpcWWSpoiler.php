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
        
        // Priority 1: WW villages - Use CASE to extract level efficiently
        $wwVillages = $db->query("
            SELECT v.kid, v.owner, u.ww_operation_state,
                   CASE
                       WHEN f.f1t=40 THEN f.f1 WHEN f.f2t=40 THEN f.f2 WHEN f.f3t=40 THEN f.f3 WHEN f.f4t=40 THEN f.f4 WHEN f.f5t=40 THEN f.f5
                       WHEN f.f6t=40 THEN f.f6 WHEN f.f7t=40 THEN f.f7 WHEN f.f8t=40 THEN f.f8 WHEN f.f9t=40 THEN f.f9 WHEN f.f10t=40 THEN f.f10
                       WHEN f.f11t=40 THEN f.f11 WHEN f.f12t=40 THEN f.f12 WHEN f.f13t=40 THEN f.f13 WHEN f.f14t=40 THEN f.f14 WHEN f.f15t=40 THEN f.f15
                       WHEN f.f16t=40 THEN f.f16 WHEN f.f17t=40 THEN f.f17 WHEN f.f18t=40 THEN f.f18 WHEN f.f19t=40 THEN f.f19 WHEN f.f20t=40 THEN f.f20
                       WHEN f.f21t=40 THEN f.f21 WHEN f.f22t=40 THEN f.f22 WHEN f.f23t=40 THEN f.f23 WHEN f.f24t=40 THEN f.f24 WHEN f.f25t=40 THEN f.f25
                       WHEN f.f26t=40 THEN f.f26 WHEN f.f27t=40 THEN f.f27 WHEN f.f28t=40 THEN f.f28 WHEN f.f29t=40 THEN f.f29 WHEN f.f30t=40 THEN f.f30
                       WHEN f.f31t=40 THEN f.f31 WHEN f.f32t=40 THEN f.f32 WHEN f.f33t=40 THEN f.f33 WHEN f.f34t=40 THEN f.f34 WHEN f.f35t=40 THEN f.f35
                       WHEN f.f36t=40 THEN f.f36 WHEN f.f37t=40 THEN f.f37 WHEN f.f38t=40 THEN f.f38 WHEN f.f39t=40 THEN f.f39 WHEN f.f40t=40 THEN f.f40
                       ELSE 0
                   END AS ww_level
            FROM vdata v
            JOIN fdata f ON v.kid = f.kid
            JOIN users u ON v.owner = u.id
            WHERE (f.f1t=40 OR f.f2t=40 OR f.f3t=40 OR f.f4t=40 OR f.f5t=40 OR f.f6t=40 OR f.f7t=40 OR f.f8t=40 OR f.f9t=40 OR f.f10t=40
                OR f.f11t=40 OR f.f12t=40 OR f.f13t=40 OR f.f14t=40 OR f.f15t=40 OR f.f16t=40 OR f.f17t=40 OR f.f18t=40 OR f.f19t=40 OR f.f20t=40
                OR f.f21t=40 OR f.f22t=40 OR f.f23t=40 OR f.f24t=40 OR f.f25t=40 OR f.f26t=40 OR f.f27t=40 OR f.f28t=40 OR f.f29t=40 OR f.f30t=40
                OR f.f31t=40 OR f.f32t=40 OR f.f33t=40 OR f.f34t=40 OR f.f35t=40 OR f.f36t=40 OR f.f37t=40 OR f.f38t=40 OR f.f39t=40 OR f.f40t=40)
              AND u.aid != $allianceId
              AND u.ww_alliance_role = 'Contender'
            ORDER BY ww_level DESC
            LIMIT 5
        ");
        
        while ($row = $wwVillages->fetch_assoc()) {
            $targets[] = [
                'type' => 'ww_village',
                'village_id' => (int)$row['kid'],
                'owner_id' => (int)$row['owner'],
                'priority' => 100 + (int)$row['ww_level'],
                'ww_level' => (int)$row['ww_level']
            ];
        }
        
        // Priority 2: Plan holders with Treasury level 10+
        $planHolders = $db->query("
            SELECT DISTINCT v.kid, v.owner
            FROM vdata v
            JOIN users u ON v.owner = u.id
            JOIN fdata f ON v.kid = f.kid
            WHERE u.aid != $allianceId
              AND u.ww_alliance_role = 'Contender'
              AND (   (f.f1t=27 AND f.f1>=10) OR (f.f2t=27 AND f.f2>=10) OR (f.f3t=27 AND f.f3>=10) OR (f.f4t=27 AND f.f4>=10) OR (f.f5t=27 AND f.f5>=10)
                   OR (f.f6t=27 AND f.f6>=10) OR (f.f7t=27 AND f.f7>=10) OR (f.f8t=27 AND f.f8>=10) OR (f.f9t=27 AND f.f9>=10) OR (f.f10t=27 AND f.f10>=10)
                   OR (f.f11t=27 AND f.f11>=10) OR (f.f12t=27 AND f.f12>=10) OR (f.f13t=27 AND f.f13>=10) OR (f.f14t=27 AND f.f14>=10) OR (f.f15t=27 AND f.f15>=10)
                   OR (f.f16t=27 AND f.f16>=10) OR (f.f17t=27 AND f.f17>=10) OR (f.f18t=27 AND f.f18>=10) OR (f.f19t=27 AND f.f19>=10) OR (f.f20t=27 AND f.f20>=10)
                   OR (f.f21t=27 AND f.f21>=10) OR (f.f22t=27 AND f.f22>=10) OR (f.f23t=27 AND f.f23>=10) OR (f.f24t=27 AND f.f24>=10) OR (f.f25t=27 AND f.f25>=10)
                   OR (f.f26t=27 AND f.f26>=10) OR (f.f27t=27 AND f.f27>=10) OR (f.f28t=27 AND f.f28>=10) OR (f.f29t=27 AND f.f29>=10) OR (f.f30t=27 AND f.f30>=10)
                   OR (f.f31t=27 AND f.f31>=10) OR (f.f32t=27 AND f.f32>=10) OR (f.f33t=27 AND f.f33>=10) OR (f.f34t=27 AND f.f34>=10) OR (f.f35t=27 AND f.f35>=10)
                   OR (f.f36t=27 AND f.f36>=10) OR (f.f37t=27 AND f.f37>=10) OR (f.f38t=27 AND f.f38>=10) OR (f.f39t=27 AND f.f39>=10) OR (f.f40t=27 AND f.f40>=10))
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
