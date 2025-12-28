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
     * Assign a personality to an NPC with unique stat variation
     * 
     * @param int $uid User ID
     * @param string $personality Personality type (aggressive, economic, balanced, diplomat, assassin)
     * @param float $variation Variation percentage (default 0.15 = ±15%)
     * @return bool Success
     */
    public static function assignPersonality($uid, $personality, $variation = 0.15)
    {
        if (!isset(self::PERSONALITIES[$personality])) {
            return false;
        }

        $db = DB::getInstance();
        $uid = (int)$uid;
        $personality_escaped = $db->real_escape_string($personality);

        // Get base stats for this personality
        $baseStats = self::PERSONALITIES[$personality];
        
        // Apply variation to numeric stats (±15% by default)
        $variedStats = [];
        foreach ($baseStats as $key => $value) {
            if (is_numeric($value)) {
                // Add random variation
                $min = $value * (1 - $variation);
                $max = $value * (1 + $variation);
                $variedStats[$key] = mt_rand((int)$min, (int)$max);
            } else {
                // Keep non-numeric values as-is
                $variedStats[$key] = $value;
            }
        }
        
        // Get existing npc_info or create new
        $existingInfo = $db->fetchScalar("SELECT npc_info FROM users WHERE id=$uid");
        $info = $existingInfo ? json_decode($existingInfo, true) : [];
        
        // Store varied stats in npc_info
        $info['personality_stats'] = $variedStats;
        $info['variation_seed'] = mt_rand(1000, 9999); // For reproducible behavior
        $info['personality_assigned_at'] = time();
        
        $infoJson = $db->real_escape_string(json_encode($info));
        
        // Update personality and varied stats
        return $db->query("UPDATE users 
                          SET npc_personality='$personality_escaped', 
                              npc_info='$infoJson' 
                          WHERE id=$uid");
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
        
        // Grant gold club for farm-list access (permanent)
        self::grantGoldClub($uid);

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
     * Get randomized iteration count for a difficulty with variation
     * 
     * Adds natural variation to make NPCs feel less robotic
     * 
     * @param string $difficulty Difficulty level
     * @param float $variation Variation percentage (default 0.2 = ±20%)
     * @return int Number of iterations with random variation applied
     */
    public static function getRandomizedIterations($difficulty, $variation = 0.2)
    {
        $base = self::getIterationCount($difficulty);
        
        $min = (int)($base * (1 - $variation));
        $max = (int)($base * (1 + $variation));
        
        return mt_rand($min, $max);
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

        // Use varied stats if available, otherwise fall back to base stats
        if ($row['npc_personality']) {
            if (isset($row['npc_info']['personality_stats'])) {
                // Use varied stats from npc_info (new system with variation)
                $row['personality_stats'] = $row['npc_info']['personality_stats'];
            } else {
                // Fall back to base stats (old NPCs without variation)
                $row['personality_stats'] = self::getPersonalityStats($row['npc_personality']);
            }
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
     * Uses server speed to calculate appropriate raid cooldowns:
     * - Base frequency at 1x speed: 6 hours
     * - Scales down with server speed (25x server = 6/25 = 0.24 hours = ~14 minutes)
     * - Modified by personality multiplier
     * 
     * @param string $personality Personality type
     * @return array ['min' => int, 'max' => int] Min and max seconds between raids
     */
    public static function getRaidFrequency($personality)
    {
        $stats = self::getPersonalityStats($personality);
        if (!$stats) {
            $personality = 'balanced'; // Default
            $stats = self::PERSONALITIES['balanced'];
        }

        $frequency = $stats['raid_frequency'];
        
        // Get server speed (function from game core)
        $gameSpeed = function_exists('getGameSpeed') ? getGameSpeed() : 1;
        $gameSpeed = max(1, $gameSpeed); // Ensure minimum 1x
        
        // Base cooldown at 1x speed: 6 hours (21600 seconds)
        $baseCooldown = 21600;
        
        // Calculate speed-adjusted base cooldown
        $speedAdjustedBase = $baseCooldown / $gameSpeed;
        
        // Personality multipliers (relative to base)
        $multipliers = [
            'high' => ['min' => 0.15, 'max' => 0.3],      // Aggressive: 15-30% of base
            'medium' => ['min' => 0.35, 'max' => 0.7],    // Assassin/Balanced: 35-70% of base
            'low' => ['min' => 1.0, 'max' => 2.0],        // Economic: 100-200% of base
            'very_low' => ['min' => 2.0, 'max' => 3.0],   // Diplomat: 200-300% of base
        ];
        
        $mult = $multipliers[$frequency] ?? $multipliers['medium'];
        
        $min = (int)($speedAdjustedBase * $mult['min']);
        $max = (int)($speedAdjustedBase * $mult['max']);
        
        // Ensure minimums (prevent raids every second on ultra-fast servers)
        $absoluteMin = 300; // 5 minutes minimum
        $min = max($absoluteMin, $min);
        $max = max($min + 300, $max); // Ensure max > min by at least 5 minutes
        
        return ['min' => $min, 'max' => $max];
    }
    
    /**
     * Grant permanent gold club to an NPC for farm-list access
     * 
     * @param int $uid User ID
     * @return bool Success
     */
    public static function grantGoldClub($uid)
    {
        $db = DB::getInstance();
        $uid = (int)$uid;
        
        // Grant gold club (permanent server-wide flag)
        // Set to 1 = active/permanent
        return $db->query("UPDATE users SET goldclub=1 WHERE id=$uid");
    }
    
    /**
     * Create farm-list for an NPC with initial targets
     * 
     * @param int $uid User ID
     * @param int $kid Village ID
     * @return int|false Farm-list ID or false on failure
     */
    public static function createNpcFarmList($uid, $kid)
    {
        $db = DB::getInstance();
        $uid = (int)$uid;
        $kid = (int)$kid;
        
        // Create farm-list
        $db->query("INSERT INTO farmlist (kid, owner, name, auto) 
                    VALUES ($kid, $uid, 'NPC Farm List', 1)");
        
        $listId = $db->lastInsertId();
        
        if (!$listId) {
            return false;
        }
        
        // Find initial targets (oasis and weak players nearby)
        $targets = self::findInitialFarmTargets($kid, 10);
        
        // Add targets to farm-list
        foreach ($targets as $targetKid) {
            // Default troops (adjust based on race later)
            $db->query("INSERT INTO raidlist (kid, lid, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10, t11) 
                        VALUES ($targetKid, $listId, 5, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0)");
        }
        
        \Core\AI\NpcLogger::log($uid, 'FARMLIST', "Created farm-list with " . count($targets) . " targets", [
            'list_id' => $listId,
            'targets' => count($targets)
        ]);
        
        return $listId;
    }
    
    /**
     *Find initial farm targets for an NPC
     * 
     * @param int $kid Village ID
     * @param int $limit Maximum targets
     * @return array Array of target kids
     */
    private static function findInitialFarmTargets($kid, $limit = 10)
    {
        $db = DB::getInstance();
        $xy = \Game\Formulas::kid2xy($kid);
        $maxDistance = 15;
        
        $targets = [];
        
        // Get oasis within range
        $oasisQuery = "SELECT w.id as kid
                       FROM wdata w
                       WHERE w.oasistype > 0
                         AND w.occupied = 0
                         AND ABS(w.x - {$xy['x']}) <= $maxDistance
                         AND ABS(w.y - {$xy['y']}) <= $maxDistance
                       LIMIT $limit";
        
        $result = $db->query($oasisQuery);
        while ($row = $result->fetch_assoc()) {
            $targets[] = $row['kid'];
        }
        
        // If not enough oasis, add weak players
        if (count($targets) < $limit) {
            $remaining = $limit - count($targets);
            $villageQuery = "SELECT v.kid
                            FROM vdata v
                            JOIN wdata w ON v.kid = w.id
                            WHERE v.owner > 1
                              AND v.owner != (SELECT owner FROM vdata WHERE kid=$kid)
                              AND v.pop < 200
                              AND ABS(w.x - {$xy['x']}) <= $maxDistance
                              AND ABS(w.y - {$xy['y']}) <= $maxDistance
                            ORDER BY v.pop ASC
                            LIMIT $remaining";
            
            $result = $db->query($villageQuery);
            while ($row = $result->fetch_assoc()) {
                $targets[] = $row['kid'];
            }
        }
        
        return $targets;
    }
    
    /**
     * Refresh farm-lists for all NPCs (called daily)
     * Removes failed targets, adds new ones
     * 
     * @return int Number of farm-lists refreshed
     */
    public static function refreshNpcFarmLists()
    {
        $db = DB::getInstance();
        $refreshed = 0;
        
        // Get all NPC farm-lists older than 24 hours
        $dayAgo = time() - 86400;
        $query = "SELECT f.id as list_id, f.owner as uid, f.kid
                  FROM farmlist f
                  JOIN users u ON f.owner = u.id
                  WHERE u.access = 3
                    AND (f.lastRefresh IS NULL OR f.lastRefresh < $dayAgo)
                  LIMIT 50";
        
        $result = $db->query($query);
        
        if (!$result || $result->num_rows == 0) {
            return 0;
        }
        
        while ($row = $result->fetch_assoc()) {
            $listId = $row['list_id'];
            $uid = $row['uid'];
            $kid = $row['kid'];
            
            // Remove failed targets (villages that no longer exist, high loss raids)
            self::removeFailedTargets($listId);
            
            // Count remaining targets
            $remaining = $db->fetchScalar("SELECT COUNT(*) FROM raidlist WHERE lid=$listId");
            
            // Add new targets if needed (maintain ~10 targets)
            if ($remaining < 10) {
                $needed = 10 - $remaining;
                $newTargets = self::findInitialFarmTargets($kid, $needed);
                
                foreach ($newTargets as $targetKid) {
                    // Check if already in list
                    $exists = $db->fetchScalar("SELECT COUNT(*) FROM raidlist WHERE lid=$listId AND kid=$targetKid");
                    if (!$exists) {
                        $db->query("INSERT INTO raidlist (kid, lid, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10, t11) 
                                    VALUES ($targetKid, $listId, 5, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0)");
                    }
                }
                
                \Core\AI\NpcLogger::log($uid, 'FARMLIST', "Refreshed farm-list: removed failed, added $needed new targets", [
                    'list_id' => $listId,
                    'remaining' => $remaining,
                    'added' => count($newTargets)
                ]);
            }
            
            // Update lastRefresh timestamp
            $db->query("UPDATE farmlist SET lastRefresh=" . time() . " WHERE id=$listId");
            $refreshed++;
        }
        
        return $refreshed;
    }
    
    /**
     * Remove failed targets from farm-list
     * 
     * @param int $listId Farm-list ID
     * @return int Number of targets removed
     */
    private static function removeFailedTargets($listId)
    {
        $db = DB::getInstance();
        $removed = 0;
        
        // Get all targets in this farm-list
        $targets = $db->query("SELECT id, kid FROM raidlist WHERE lid=$listId");
        
        while ($row = $targets->fetch_assoc()) {
            $slotId = $row['id'];
            $targetKid = $row['kid'];
            
            // Check if target village still exists
            $exists = $db->fetchScalar("SELECT COUNT(*) FROM vdata WHERE kid=$targetKid");
            
            if (!$exists) {
                // Village doesn't exist anymore - remove
                $db->query("DELETE FROM raidlist WHERE id=$slotId");
                $removed++;
                continue;
            }
            
            // Check recent raid reports for this target
            // Remove if last 3 raids had >50% losses
            $recentReports = $db->query("SELECT * FROM ndata 
                                        WHERE toWref=$targetKid 
                                          AND type=4 
                                        ORDER BY time DESC 
                                        LIMIT 3");
            
            $highLossCount = 0;
            $totalReports = 0;
            
            while ($report = $recentReports->fetch_assoc()) {
                $totalReports++;
                // Parse report data to check losses
                // For simplicity, we'll use bounty as success indicator
                // If bounty = 0 for multiple raids, likely failed/high loss
                if ($report['bounty'] == 0) {
                    $highLossCount++;
                }
            }
            
            // If majority of recent raids failed, remove target
            if ($totalReports >= 3 && $highLossCount >= 2) {
                $db->query("DELETE FROM raidlist WHERE id=$slotId");
                $removed++;
            }
        }
        
        return $removed;
    }
}
