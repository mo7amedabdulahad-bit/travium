<?php

namespace Core\AI;

use Core\NpcConfig;
use Core\Database\DB;

/**
 * Alliance AI for NPCs
 * 
 * Enables NPCs to join existing alliances or create new ones
 * based on personality and strategic considerations.
 * 
 * @package Core\AI
 * @version 1.0
 * @date 2025-12-28
 */
class AllianceAI
{
    /**
     * Process alliance decisions for an NPC
     * 
     * Checks if NPC should join/create alliance based on:
     * - Current alliance status
     * - Personality tendency
     * - Cooldown timer (24-48 hours)
     * 
     * @param int $uid User ID
     * @return bool True if action was taken
     */
    public static function processAlliance($uid)
    {
        $db = DB::getInstance();
        
        // Check if already in alliance
        $currentAid = $db->fetchScalar("SELECT aid FROM users WHERE id=$uid");
        if ($currentAid > 0) {
            return false; // Already in alliance
        }
        
        // Get NPC config
        $config = NpcConfig::getNpcConfig($uid);
        if (!$config) {
            return false;
        }
        
        // Check cooldown (24-48 hours between alliance checks)
        $lastCheck = $config['npc_info']['last_alliance_check'] ?? 0;
        $cooldown = mt_rand(86400, 172800); // 24-48 hours
        
        if (time() - $lastCheck < $cooldown) {
            return false;
        }
        
        // Update check time
        NpcConfig::updateNpcInfo($uid, 'last_alliance_check', time());
        
        // Should join alliance?
        if (!self::shouldJoinAlliance($uid, $config)) {
            NpcLogger::log($uid, 'ALLIANCE', 'Decided not to join any alliance', ['tendency' => $config['personality_stats']['alliance_tendency']]);
            return false;
        }
        
        // Find suitable alliance
        $alliance = self::findSuitableAlliance($uid, $config);
        
        if ($alliance) {
            return self::joinAlliance($uid, $alliance);
        }
        
        // No suitable alliance - should create?
        if (self::shouldCreateAlliance($uid, $config)) {
            return self::createAlliance($uid, $config);
        }
        
        NpcLogger::log($uid, 'ALLIANCE', 'No suitable alliance found, not creating', []);
        return false;
    }
    
    /**
     * Check if NPC should join alliance based on personality
     * 
     * @param int $uid User ID
     * @param array $config NPC configuration
     * @return bool True if should join
     */
    private static function shouldJoinAlliance($uid, $config)
    {
        $tendency = $config['personality_stats']['alliance_tendency'] ?? 50;
        $roll = mt_rand(1, 100);
        
        return $roll <= $tendency;
    }
    
    /**
     * Find best alliance match for NPC
     * 
     * Considers:
     * - Alliance size (personality preference)
     * - Total strength (population)
     * - Activity level
     * 
     * @param int $uid User ID
     * @param array $config NPC configuration
     * @return array|null Alliance data or null
     */
    private static function findSuitableAlliance($uid, $config)
    {
        $db = DB::getInstance();
        $personality = $config['npc_personality'];
        
        // Size preferences based on personality
        $sizePrefs = [
            'diplomat' => ['min' => 5, 'max' => 999],      // Large alliances
            'balanced' => ['min' => 3, 'max' => 999],      // Any size
            'economic' => ['min' => 4, 'max' => 999],      // Medium-large
            'assassin' => ['min' => 2, 'max' => 8],        // Small alliances
            'aggressive' => ['min' => 2, 'max' => 10],     // Small-medium
        ];
        
        $prefs = $sizePrefs[$personality] ?? ['min' => 3, 'max' => 999];
        
        // Find alliances (exclude NPC-only alliances for variety)
        $query = "SELECT a.id, a.name, a.tag, COUNT(u.id) as member_count,
                         SUM(u.pop) as total_pop
                  FROM alidata a
                  JOIN users u ON u.aid = a.id
                  WHERE a.id > 0
                  GROUP BY a.id
                  HAVING member_count >= {$prefs['min']} 
                     AND member_count <= {$prefs['max']}
                  ORDER BY total_pop DESC
                  LIMIT 10";
        
        $result = $db->query($query);
        
        if (!$result || $result->num_rows == 0) {
            return null;
        }
        
        $alliances = [];
        while ($row = $result->fetch_assoc()) {
            $alliances[] = $row;
        }
        
        // Return top alliance (highest population)
        return !empty($alliances) ? $alliances[0] : null;
    }
    
    /**
     * Join an alliance
     * 
     * @param int $uid User ID
     * @param array $alliance Alliance data
     * @return bool Success
     */
    private static function joinAlliance($uid, $alliance)
    {
        $db = DB::getInstance();
        $aid = $alliance['id'];
        
        // Simply join (no invitation needed for NPCs)
        $success = $db->query("UPDATE users SET aid=$aid WHERE id=$uid");
        
        if ($success) {
            // Log
            NpcLogger::log($uid, 'ALLIANCE', "Joined alliance: {$alliance['name']} [{$alliance['tag']}]", [
                'aid' => $aid,
                'name' => $alliance['name'],
                'tag' => $alliance['tag'],
                'members' => $alliance['member_count']
            ]);
        }
        
        return $success;
    }
    
    /**
     * Check if should create alliance
     * 
     * Only Diplomat and Balanced personalities create alliances
     * Requires 3+ villages
     * 
     * @param int $uid User ID
     * @param array $config NPC configuration
     * @return bool True if should create
     */
    private static function shouldCreateAlliance($uid, $config)
    {
        // Only Diplomat and Balanced personalities create
        $creators = ['diplomat', 'balanced'];
        if (!in_array($config['npc_personality'], $creators)) {
            return false;
        }
        
        // Check if has 3+ villages (embassy requirement)
        $db = DB::getInstance();
        $villageCount = $db->fetchScalar("SELECT COUNT(kid) FROM vdata WHERE owner=$uid");
        
        return $villageCount >= 3;
    }
    
    /**
     * Create new alliance
     * 
     * Generates alliance name and tag, creates alliance entry,
     * and joins as leader.
     * 
     * @param int $uid User ID
     * @param array $config NPC configuration
     * @return bool Success
     */
    private static function createAlliance($uid, $config)
    {
        $db = DB::getInstance();
        
        // Generate alliance name based on personality
        $nameTemplates = [
            'diplomat' => ['The Peacekeepers', 'Unity Alliance', 'Diplomatic Corps', 'Neutral Pact'],
            'balanced' => ['The Equilibrium', 'Balanced Forces', 'Middle Kingdom', 'The Moderates'],
            'economic' => ['Trade Federation', 'Merchant Guild', 'Economic League', 'Prosperity Pact'],
            'assassin' => ['Shadow Blades', 'Silent Order', 'The Unseen', 'Night Watchers'],
            'aggressive' => ['War Machine', 'Iron Fist', 'The Dominion', 'Blood Legion'],
        ];
        
        $personality = $config['npc_personality'];
        $templates = $nameTemplates[$personality] ?? ['The Alliance', 'United Forces'];
        
        $name = $templates[array_rand($templates)];
        $tag = strtoupper(substr(str_replace(' ', '', $name), 0, 3));
        
        // Ensure unique tag
        $existingTag = $db->fetchScalar("SELECT COUNT(id) FROM alidata WHERE tag='$tag'");
        if ($existingTag > 0) {
            $tag = $tag . mt_rand(1, 99);
        }
        
        // Create alliance
        $name = $db->real_escape_string($name);
        $tag = $db->real_escape_string($tag);
        
        $success = $db->query("INSERT INTO alidata (name, tag, leader, created) 
                               VALUES ('$name', '$tag', $uid, " . time() . ")");
        
        if (!$success) {
            return false;
        }
        
        $aid = $db->insertId();
        
        // Join it as leader
        $db->query("UPDATE users SET aid=$aid WHERE id=$uid");
        
        // Log
        NpcLogger::log($uid, 'ALLIANCE', "Created alliance: $name [$tag]", [
            'aid' => $aid,
            'name' => $name,
            'tag' => $tag,
            'personality' => $personality
        ]);
        
        return true;
    }
    
    /**
     * Get alliance statistics for debugging
     * 
     * @param int $uid User ID
     * @return array Alliance stats
     */
    public static function getAllianceStats($uid)
    {
        $db = DB::getInstance();
        $config = NpcConfig::getNpcConfig($uid);
        
        if (!$config) {
            return ['error' => 'Not an NPC'];
        }
        
        $currentAid = $db->fetchScalar("SELECT aid FROM users WHERE id=$uid");
        $allianceName = 'None';
        
        if ($currentAid > 0) {
            $alliance = $db->query("SELECT name, tag FROM alidata WHERE id=$currentAid")->fetch_assoc();
            $allianceName = "{$alliance['name']} [{$alliance['tag']}]";
        }
        
        $lastCheck = $config['npc_info']['last_alliance_check'] ?? 0;
        $timeSince = $lastCheck > 0 ? time() - $lastCheck : 'Never';
        
        return [
            'personality' => $config['npc_personality'],
            'alliance_tendency' => $config['personality_stats']['alliance_tendency'] . '%',
            'current_alliance' => $allianceName,
            'last_check' => $lastCheck > 0 ? date('Y-m-d H:i:s', $lastCheck) : 'Never',
            'time_since_check' => is_numeric($timeSince) ? round($timeSince / 3600, 1) . ' hours' : $timeSince,
            'will_join' => $currentAid == 0 ? self::shouldJoinAlliance($uid, $config) : 'Already in alliance',
        ];
    }
}
