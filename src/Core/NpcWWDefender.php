<?php

namespace Core;

use Core\Database\DB;
use function logError;

/**
 * World Wonder Defender Operations
 * Handles WW defense coordination and counter-attacks
 */
class NpcWWDefender
{
    /**
     * Defend World Wonder when under attack
     * Coordinates all alliance members to send reinforcements
     * 
     * @param int $npcId NPC ID whose WW is under attack
     * @param array $event Event data from npc_world_events
     */
    public static function defendWorldWonder($npcId, $event)
    {
        $db = DB::getInstance();
        
        // Get NPC and alliance info
        $npc = $db->query("SELECT id, aid, ww_operation_state FROM users WHERE id = $npcId")->fetch_assoc();
        
        if (!$npc || !$npc['aid']) {
            logError("NPC $npcId: Cannot defend WW (no alliance)");
            return;
        }
        
        $allianceId = $npc['aid'];
        $attackedVillageId = $event['target_village_id'] ?? $event['ww_village_id'] ?? null;
        
        if (!$attackedVillageId) {
            logError("NPC $npcId: WW defense event missing village ID");
            return;
        }
        
        // Coordinate alliance-wide defense
        self::coordinateDefense($attackedVillageId, $allianceId);
        
        // Setup resource pipeline
        self::setupResourcePipeline($attackedVillageId, $allianceId);
        
        // Add attacker to retaliation list
        if (!empty($event['attacker_id'])) {
            NpcRetaliationManager::addRetaliationTarget($npcId, $event['attacker_id'], 2.0);
            logError("NPC $npcId: Added WW attacker {$event['attacker_id']} to retaliation list");
        }
    }
    
    /**
     * Coordinate defense - all alliance members send reinforcements
     * 
     * @param int $attackedVillageId Village under attack
     * @param int $allianceId Alliance ID
     */
    private static function coordinateDefense($attackedVillageId, $allianceId)
    {
        $db = DB::getInstance();
        
        // Get all NPCs in alliance
        $npcs = $db->query("
            SELECT id, npc_difficulty 
            FROM users 
            WHERE aid = $allianceId AND access = 3
        ");
        
        if (!$npcs || $npcs->num_rows === 0) return;
        
        logError("Coordinating WW defense for village $attackedVillageId (Alliance $allianceId)");
        
        while ($npc = $npcs->fetch_assoc()) {
            $npcId = $npc['id'];
            $difficulty = $npc['npc_difficulty'] ?? 'Medium';
            
            // Difficulty-based response rate
            $responseRates = [
                'Easy' => 40,    // 40% will send reinforcements
                'Medium' => 70,  // 70% will send reinforcements
                'Hard' => 100    // 100% always respond
            ];
            $responseChance = $responseRates[$difficulty] ?? 70;
            
            if (mt_rand(1, 100) > $responseChance) {
                continue; // This NPC doesn't respond
            }
            
            // Send reinforcements from all villages
            self::sendReinforcements($npcId, $attackedVillageId);
        }
    }
    
    /**
     * Send reinforcements from NPC to WW village
     * 
     * @param int $npcId NPC user ID
     * @param int $targetVillageId WW village to reinforce
     */
    private static function sendReinforcements($npcId, $targetVillageId)
    {
        $db = DB::getInstance();
        
        // Get all NPC villages
        $villages = $db->query("SELECT kid FROM vdata WHERE owner = $npcId");
        
        while ($village = $villages->fetch_assoc()) {
            $villageId = $village['kid'];
            
            // Get available defensive troops
            $troops = self::getDefensiveTroops($villageId);
            
            if (empty($troops)) continue;
            
            // Send reinforcement (attack_type = 2 in movement table)
            self::queueReinforcement($villageId, $targetVillageId, $troops);
        }
        
        logError("NPC $npcId: Sent reinforcements to WW village $targetVillageId");
    }
    
    /**
     * Get defensive troops available in village
     */
    private static function getDefensiveTroops($villageId)
    {
        $db = DB::getInstance();
        
        // Get unit counts from unitsdata table
        // This is simplified - adapt to your troop storage system
        $result = $db->query("SELECT * FROM units WHERE kid = $villageId LIMIT 1");
        
        if (!$result || $result->num_rows === 0) return [];
        
        $units = $result->fetch_assoc();
        $troops = [];
        
        // Extract defensive units (units 2, 3, 6 are typically defensive)
        // This varies by tribe - Gauls, Romans, Teutons
        for ($i = 1; $i <= 10; $i++) {
            $unitField = "u{$i}";
            if (isset($units[$unitField]) && $units[$unitField] > 0) {
                $troops[$i] = (int)$units[$unitField];
            }
        }
        
        return $troops;
    }
    
    /**
     * Queue reinforcement movement
     */
    private static function queueReinforcement($fromVillageId, $toVillageId, $troops)
    {
        $db = DB::getInstance();
        
        // Calculate travel time
        $distance = self::calculateDistance($fromVillageId, $toVillageId);
        $speed = 5; // Base speed, adjust based on slowest unit
        $travelTime = ceil($distance / $speed) * 3600;
        
        $startTime = time();
        $endTime = $startTime + $travelTime;
        
        // Build troop string (t1, t2, ..., t10)
        $troopString = implode(',', array_map(function($count) {
            return $count ?? 0;
        }, $troops));
        
        // Insert movement (attack_type = 2 for reinforcement)
        $db->query("
            INSERT INTO movement (
                `from`, `to`, attack_type, troops, starttime, endtime
            ) VALUES (
                $fromVillageId, $toVillageId, 2, '$troopString', $startTime, $endTime
            )
        ");
    }
    
    /**
     * Setup resource pipeline - all support villages send resources to WW
     * 
     * @param int $wwVillageId WW village ID
     * @param int $allianceId Alliance ID
     */
    private static function setupResourcePipeline($wwVillageId, $allianceId)
    {
        $db = DB::getInstance();
        
        // Get all alliance NPCs
        $npcs = $db->query("SELECT id FROM users WHERE aid = $allianceId AND access = 3");
        
        while ($npc = $npcs->fetch_assoc()) {
            $npcId = $npc['id'];
            
            // Get support villages (not the WW village itself)
            $villages = $db->query("
                SELECT kid FROM vdata 
                WHERE owner = $npcId AND kid != $wwVillageId
            ");
            
            while ($village = $villages->fetch_assoc()) {
                self::sendResources($village['kid'], $wwVillageId);
            }
        }
        
        logError("Resource pipeline setup for WW village $wwVillageId (Alliance $allianceId)");
    }
    
    /**
     * Send resources from support village to WW
     */
    private static function sendResources($fromVillageId, $toVillageId)
    {
        $db = DB::getInstance();
        
        // Get available resources
        $village = $db->query("
            SELECT wood, clay, iron, crop 
            FROM vdata 
            WHERE kid = $fromVillageId
        ")->fetch_assoc();
        
        if (!$village) return;
        
        // Send 50% of each resource
        $resources = [
            'wood' => floor($village['wood'] * 0.5),
            'clay' => floor($village['clay'] * 0.5),
            'iron' => floor($village['iron'] * 0.5),
            'crop' => floor($village['crop'] * 0.5)
        ];
        
        // Only send if meaningful amount
        if ($resources['wood'] < 100) return;
        
        // Queue marketplace trade (simplified - adapt to your trade system)
        // This would normally check marketplace level and merchants available
        logError("Sending resources from village $fromVillageId to WW $toVillageId");
    }
    
    /**
     * Attack enemy WW on level up (if enabled in server settings)
     * 
     * @param int $enemyWWVillageId Enemy WW village ID
     */
    public static function attackWWOnLevelUp($enemyWWVillageId)
    {
        $db = DB::getInstance();
        
        // Check if attackWWOnLevelUp is enabled
        $setting = $db->fetchScalar("
            SELECT value FROM server_settings 
            WHERE setting_key = 'attackWWOnLevelUp'
        ");
        
        if ($setting !== '1' && $setting !== 'true') {
            return; // Feature disabled
        }
        
        // Get enemy WW owner
        $ownerId = (int)$db->fetchScalar("SELECT owner FROM vdata WHERE kid = $enemyWWVillageId");
        
        if (!$ownerId) return;
        
        // Get enemy alliance
        $enemyAllianceId = (int)$db->fetchScalar("SELECT aid FROM users WHERE id = $ownerId");
        
        // Get all rival alliance NPCs (not in same alliance)
        $rivals = $db->query("
            SELECT id, war_village_id 
            FROM users 
            WHERE access = 3 
              AND aid > 0 
              AND aid != $enemyAllianceId
              AND ww_alliance_role IN ('Contender', 'Spoiler')
        ");
        
        while ($rival = $rivals->fetch_assoc()) {
            $warVillageId = $rival['war_village_id'];
            if (!$warVillageId) continue;
            
            // Check distance
            $distance = self::calculateDistance($warVillageId, $enemyWWVillageId);
            if ($distance > 50) continue;
            
            // Load template and policy
            $template = NpcConfig::getPersonalityTemplate('Aggressive', 'Late');
            $policy = NpcConfig::getDifficultyPolicy('Hard');
            
            if ($template && $policy) {
                // Send attack wave
                NpcAttackManager::executeAttack($warVillageId, $enemyWWVillageId, $template, $policy);
                logError("NPC {$rival['id']}: Auto-attack on WW level-up (village $enemyWWVillageId)");
            }
        }
    }
    
    /**
     * Calculate distance between villages
     */
    private static function calculateDistance($villageId1, $villageId2)
    {
        $db = DB::getInstance();
        
        $coords1 = $db->query("SELECT x, y FROM wdata WHERE id = $villageId1")->fetch_assoc();
        $coords2 = $db->query("SELECT x, y FROM wdata WHERE id = $villageId2")->fetch_assoc();
        
        if (!$coords1 || $coords2) return PHP_INT_MAX;
        
        return max(
            abs($coords1['x'] - $coords2['x']),
            abs($coords1['y'] - $coords2['y'])
        );
    }
}
