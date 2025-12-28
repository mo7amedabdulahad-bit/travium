<?php

namespace Core;

use Core\Database\DB;

/**
 * NPC Configuration Manager
 * 
 * Manages NPC personalities, difficulty levels, and configuration data
 * for the intelligent AI NPC system.
 * 
 * @package Core
 * @version 1.0
 * @date 2025-12-28
 */
class NpcConfig
{
    /**
     * Available NPC personalities with behavior characteristics
     */
    const PERSONALITIES = [
        'aggressive' => [
            'military_focus' => 70,      // 70% military, 30% economy
            'economy_focus' => 30,
            'raid_frequency' => 'high',   // Raids every 1-3 hours
            'preferred_buildings' => [19, 20, 21, 13],  // Barracks, Smithy, Academy, Stable
            'preferred_units' => 'offensive',
            'alliance_tendency' => 20,    // 20% chance to join alliance
        ],
        'economic' => [
            'military_focus' => 20,
            'economy_focus' => 80,        // 80% economy, 20% military
            'raid_frequency' => 'low',    // Raids every 12-24 hours
            'preferred_buildings' => [1, 2, 3, 4, 10, 11],  // Resource fields, Warehouse, Granary
            'preferred_units' => 'defensive',
            'alliance_tendency' => 40,
        ],
        'balanced' => [
            'military_focus' => 50,
            'economy_focus' => 50,        // 50/50 mix
            'raid_frequency' => 'medium', // Raids every 4-8 hours
            'preferred_buildings' => 'balanced',
            'preferred_units' => 'mixed',
            'alliance_tendency' => 50,
        ],
        'diplomat' => [
            'military_focus' => 30,
            'economy_focus' => 70,
            'raid_frequency' => 'very_low', // Raids every 24-48 hours
            'preferred_buildings' => [17, 28, 18],  // Marketplace, Embassy, Residence
            'preferred_units' => 'defensive',
            'alliance_tendency' => 90,    // 90% likely to join alliance
        ],
        'assassin' => [
            'military_focus' => 60,
            'economy_focus' => 40,
            'raid_frequency' => 'medium', // Raids every 2-5 hours
            'preferred_buildings' => [22, 21, 23],  // Academy, Smithy, Hideout (if exists)
            'preferred_units' => 'scouts',  // Scouts and spies
            'alliance_tendency' => 30,
        ],
    ];

    /**
     * Difficulty levels with iteration counts per automation cycle
     */
    const DIFFICULTIES = [
        'beginner' => [
            'iterations' => 15,
            'description' => 'Low activity, slower development',
        ],
        'intermediate' => [
            'iterations' => 25,
            'description' => 'Medium activity, moderate development',
        ],
        'advanced' => [
            'iterations' => 35,
            'description' => 'High activity, fast development',
        ],
        'expert' => [
            'iterations' => 50,
            'description' => 'Maximum activity, fastest development',
        ],
    ];

    /**
     * Assign a personality to an NPC
     * 
     * @param int $uid User ID
     * @param string $personality Personality type (aggressive, economic, balanced, diplomat, assassin)
     * @return bool Success
     */
    public static function assignPersonality($uid, $personality)
    {
        if (!isset(self::PERSONALITIES[$personality])) {
            return false;
        }

        $db = DB::getInstance();
        $uid = (int)$uid;
        $personality = $db->real_escape_string($personality);

        return $db->query("UPDATE users SET npc_personality='$personality' WHERE id=$uid");
    }

    /**
     * Assign a difficulty level to an NPC
     * 
     * @param int $uid User ID
     * @param string $difficulty Difficulty level (beginner, intermediate, advanced, expert)
     * @return bool Success
     */
    public static function assignDifficulty($uid, $difficulty)
    {
        if (!isset(self::DIFFICULTIES[$difficulty])) {
            return false;
        }

        $db = DB::getInstance();
        $uid = (int)$uid;
        $difficulty = $db->real_escape_string($difficulty);

        return $db->query("UPDATE users SET npc_difficulty='$difficulty' WHERE id=$uid");
    }

    /**
     * Assign random personality and difficulty to an NPC
     * 
     * @param int $uid User ID
     * @return array ['personality' => string, 'difficulty' => string]
     */
    public static function assignRandom($uid)
    {
        $personalities = array_keys(self::PERSONALITIES);
        $difficulties = array_keys(self::DIFFICULTIES);

        $personality = $personalities[mt_rand(0, count($personalities) - 1)];
        $difficulty = $difficulties[mt_rand(0, count($difficulties) - 1)];

        self::assignPersonality($uid, $personality);
        self::assignDifficulty($uid, $difficulty);

        // Initialize npc_info JSON
        $initialInfo = json_encode([
            'created_at' => time(),
            'version' => '1.0',
            'raids_sent' => 0,
            'raids_received' => 0,
            'total_buildings_built' => 0,
            'total_troops_trained' => 0,
            'last_raid_time' => null,
            'preferred_targets' => [],
            'farm_list' => []
        ]);

        $db = DB::getInstance();
        $uid = (int)$uid;
        $db->query("UPDATE users SET npc_info='$initialInfo' WHERE id=$uid");

        return [
            'personality' => $personality,
            'difficulty' => $difficulty,
        ];
    }

    /**
     * Get personality statistics for a specific personality type
     * 
     * @param string $personality Personality type
     * @return array|null Personality stats or null if invalid
     */
    public static function getPersonalityStats($personality)
    {
        return self::PERSONALITIES[$personality] ?? null;
    }

    /**
     * Get iteration count for a difficulty level
     * 
     * @param string $difficulty Difficulty level
     * @return int Number of iterations per cycle
     */
    public static function getIterationCount($difficulty)
    {
        return self::DIFFICULTIES[$difficulty]['iterations'] ?? 15;
    }

    /**
     * Get NPC configuration from database
     * 
     * @param int $uid User ID
     * @return array|null NPC config or null if not an NPC
     */
    public static function getNpcConfig($uid)
    {
        $db = DB::getInstance();
        $uid = (int)$uid;

        $result = $db->query("SELECT npc_personality, npc_difficulty, npc_info, goldclub, last_npc_action 
                              FROM users 
                              WHERE id=$uid AND access=3");

        if (!$result || $result->num_rows === 0) {
            return null;
        }

        $row = $result->fetch_assoc();
        
        // Decode JSON info
        if ($row['npc_info']) {
            $row['npc_info'] = json_decode($row['npc_info'], true);
        }

        // Add personality stats
        if ($row['npc_personality']) {
            $row['personality_stats'] = self::getPersonalityStats($row['npc_personality']);
        }

        // Add iteration count
        if ($row['npc_difficulty']) {
            $row['iterations'] = self::getIterationCount($row['npc_difficulty']);
        }

        return $row;
    }

    /**
     * Update NPC info JSON field
     * 
     * @param int $uid User ID
     * @param string $key JSON key to update
     * @param mixed $value New value
     * @return bool Success
     */
    public static function updateNpcInfo($uid, $key, $value)
    {
        $db = DB::getInstance();
        $uid = (int)$uid;
        $key = $db->real_escape_string($key);
        
        // JSON_SET requires proper value formatting
        if (is_string($value)) {
            $jsonValue = '"' . $db->real_escape_string($value) . '"';
        } elseif (is_array($value)) {
            $jsonValue = "'" . $db->real_escape_string(json_encode($value)) . "'";
        } else {
            $jsonValue = $value;
        }

        return $db->query("UPDATE users 
                          SET npc_info = JSON_SET(npc_info, '$.$key', $jsonValue) 
                          WHERE id=$uid AND access=3");
    }

    /**
     * Increment a counter in NPC info JSON
     * 
     * @param int $uid User ID
     * @param string $counter Counter name (e.g., 'raids_sent', 'total_buildings_built')  
     * @param int $amount Amount to increment (default 1)
     * @return bool Success
     */
    public static function incrementCounter($uid, $counter, $amount = 1)
    {
        $db = DB::getInstance();
        $uid = (int)$uid;
        $counter = $db->real_escape_string($counter);
        $amount = (int)$amount;

        return $db->query("UPDATE users 
                          SET npc_info = JSON_SET(npc_info, '$.$counter', 
                              COALESCE(JSON_EXTRACT(npc_info, '$.$counter'), 0) + $amount)
                          WHERE id=$uid AND access=3");
    }

    /**
     * Update last NPC action timestamp
     * 
     * @param int $uid User ID
     * @return bool Success
     */
    public static function updateLastAction($uid)
    {
        $db = DB::getInstance();
        $uid = (int)$uid;
        $time = time();

        return $db->query("UPDATE users SET last_npc_action=$time WHERE id=$uid AND access=3");
    }

    /**
     * Check if user is an NPC
     * 
     * @param int $uid User ID
     * @return bool True if user is NPC (access=3), false otherwise
     */
    public static function isNpc($uid)
    {
        $db = DB::getInstance();
        $uid = (int)$uid;

        $access = $db->fetchScalar("SELECT access FROM users WHERE id=$uid");
        return $access == 3;
    }

    /**
     * Get raid frequency in seconds for a personality
     * 
     * @param string $personality Personality type
     * @return array ['min' => int, 'max' => int] Min and max seconds between raids
     */
    public static function getRaidFrequency($personality)
    {
        $stats = self::getPersonalityStats($personality);
        if (!$stats) {
            return ['min' => 14400, 'max' => 28800]; // Default 4-8 hours
        }

        $frequency = $stats['raid_frequency'];

        switch ($frequency) {
            case 'high':
                return ['min' => 3600, 'max' => 10800];  // 1-3 hours
            case 'medium':
                return ['min' => 7200, 'max' => 18000];  // 2-5 hours
            case 'low':
                return ['min' => 43200, 'max' => 86400]; // 12-24 hours
            case 'very_low':
                return ['min' => 86400, 'max' => 172800]; // 24-48 hours
            default:
                return ['min' => 14400, 'max' => 28800]; // 4-8 hours
        }
    }
}
