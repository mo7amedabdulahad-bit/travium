<?php

namespace Core;

use Core\Database\DB;

class NpcRetaliationManager
{
    /**
     * Add an attacker to NPC's retaliation list
     * 
     * @param int $npcId The NPC user ID
     * @param int $attackerId The attacker's user ID
     * @param float $priority Priority multiplier (1.0 = normal, 2.0 = high priority)
     */
    public static function addRetaliationTarget($npcId, $attackerId, $priority = 1.0)
    {
        $db = DB::getInstance();
        
        // Get current memory
        $memory = self::getMemory($npcId);
        
        // Initialize retaliation_targets if not exists
        if (!isset($memory['retaliation_targets'])) {
            $memory['retaliation_targets'] = [];
        }
        
        // Check if target already exists
        $existingIndex = null;
        foreach ($memory['retaliation_targets'] as $index => $target) {
            if ($target['user_id'] == $attackerId) {
                $existingIndex = $index;
                break;
            }
        }
        
        $currentTime = time();
        $expiresAt = $currentTime + (72 * 3600); // 72 hours
        
        if ($existingIndex !== null) {
            // Update existing target (increase priority, reset timer)
            $memory['retaliation_targets'][$existingIndex]['priority'] += 0.5; // Escalate
            $memory['retaliation_targets'][$existingIndex]['priority'] = min($memory['retaliation_targets'][$existingIndex]['priority'], 3.0); // Cap at 3.0
            $memory['retaliation_targets'][$existingIndex]['incidents']++;
            $memory['retaliation_targets'][$existingIndex]['expires_at'] = $expiresAt;
        } else {
            // Add new target
            $memory['retaliation_targets'][] = [
                'user_id' => $attackerId,
                'priority' => $priority,
                'added_at' => $currentTime,
                'expires_at' => $expiresAt,
                'incidents' => 1
            ];
        }
        
        // Clean expired targets
        $memory['retaliation_targets'] = array_filter($memory['retaliation_targets'], function($target) use ($currentTime) {
            return $target['expires_at'] > $currentTime;
        });
        
        // Re-index array
        $memory['retaliation_targets'] = array_values($memory['retaliation_targets']);
        
        // Save memory
        self::saveMemory($npcId, $memory);
    }
    
    /**
     * Get prioritized list of retaliation targets for an NPC
     * 
     * @param int $npcId The NPC user ID
     * @return array Sorted list of targets (highest priority first)
     */
    public static function getRetaliationTargets($npcId)
    {
        $memory = self::getMemory($npcId);
        
        if (!isset($memory['retaliation_targets']) || empty($memory['retaliation_targets'])) {
            return [];
        }
        
        // Sort by priority descending
        $targets = $memory['retaliation_targets'];
        usort($targets, function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
        
        return $targets;
    }
    
    /**
     * Record that defense was sent to a target (for cooldown tracking)
     * 
     * @param int $npcId The NPC user ID
     * @param int $targetVillageId The village that was defended
     */
    public static function recordDefenseSent($npcId, $targetVillageId)
    {
        $memory = self::getMemory($npcId);
        
        if (!isset($memory['last_defense_sent'])) {
            $memory['last_defense_sent'] = [];
        }
        
        $memory['last_defense_sent']['target_' . $targetVillageId] = time();
        
        self::saveMemory($npcId, $memory);
    }
    
    /**
     * Check if NPC can send defense to a target (cooldown check)
     * 
     * @param int $npcId The NPC user ID
     * @param int $targetVillageId The village to defend
     * @return bool True if can send, false if on cooldown
     */
    public static function canSendDefense($npcId, $targetVillageId)
    {
        $memory = self::getMemory($npcId);
        
        if (!isset($memory['last_defense_sent'])) {
            return true;
        }
        
        $key = 'target_' . $targetVillageId;
        if (!isset($memory['last_defense_sent'][$key])) {
            return true;
        }
        
        $lastSent = $memory['last_defense_sent'][$key];
        $cooldown = 24 * 3600; // 24 hours
        
        return (time() - $lastSent) > $cooldown;
    }
    
    /**
     * Get NPC memory from database
     * 
     * @param int $npcId The NPC user ID
     * @return array Memory structure
     */
    private static function getMemory($npcId)
    {
        $db = DB::getInstance();
        $json = $db->fetchScalar("SELECT npc_memory_json FROM users WHERE id=$npcId");
        
        if (!$json) return [];
        
        $memory = json_decode($json, true);
        return is_array($memory) ? $memory : [];
    }
    
    /**
     * Save NPC memory to database
     * 
     * @param int $npcId The NPC user ID
     * @param array $memory Memory structure
     */
    private static function saveMemory($npcId, $memory)
    {
        $db = DB::getInstance();
        $json = json_encode($memory);
        $json = $db->real_escape_string($json);
        
        $db->query("UPDATE users SET npc_memory_json='$json' WHERE id=$npcId");
    }
}
