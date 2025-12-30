<?php

namespace Core\Cache;

use Core\Database\DB;
use Game\Formulas;

/**
 * Building Cost Cache Generator
 * 
 * Purpose: Pre-calculate all building costs and cache in Redis
 * Costs never change during game, so we calculate once and cache forever
 * 
 * Usage:
 *   BuildingCostCache::generateCache();  // Generate/update cache
 *   BuildingCostCache::getCost($type, $level);  // Get cached cost
 */
class BuildingCostCache
{
    private static $cache = null;
    private static $redis = null;
    private static $cacheKey = 'building_costs_all';
    
    /**
     * Initialize cache
     */
    private static function init()
    {
        if (self::$cache !== null) {
            return;
        }
        
        self::$redis = RedisCache::getInstance();
        
        // Try to load from Redis first
        if (self::$redis->isAvailable()) {
            $cached = self::$redis->get(self::$cacheKey);
            if ($cached !== null) {
                self::$cache = $cached;
                return;
            }
        }
        
        // Generate if not in cache
        self::generateCache();
    }
    
    /**
     * Generate complete building cost cache
     * Calculates costs for all building types, all levels (1-20)
     * 
     * @return bool Success
     */
    public static function generateCache()
    {
        $costs = [];
        
        // Loop through all building types (1-43)
        for ($type = 1; $type <= 43; $type++) {
            $costs[$type] = [];
            
            // Loop through levels 1-20
            for ($level = 1; $level <= 20; $level++) {
                try {
                    // Get cost using existing formula
                    $cost = Formulas::buildingCost($type, $level);
                    
                    // Get build time
                    $time = Formulas::buildingTime($type, $level, 0, false);
                    
                    $costs[$type][$level] = [
                        'wood' => $cost[0],
                        'clay' => $cost[1],
                        'iron' => $cost[2],
                        'crop' => $cost[3],
                        'time' => $time,
                    ];
                    
                } catch (\Exception $e) {
                    // Some building types might not exist or have errors
                    // Skip them
                    continue;
                }
            }
        }
        
        // Store in memory
        self::$cache = $costs;
        
        // Store in Redis (no expiration - costs never change)
        if (self::$redis && self::$redis->isAvailable()) {
            self::$redis->set(self::$cacheKey, $costs, 0); // TTL = 0 (never expire)
            error_log('[BuildingCostCache] Generated and cached costs for ' . count($costs) . ' building types');
        }
        
        return true;
    }
    
    /**
     * Get building cost from cache
     * 
     * @param int $type Building type (1-43)
     * @param int $level Building level (1-20)
     * @return array|null Cost array [wood, clay, iron, crop, time] or null if not found
     */
    public static function getCost($type, $level)
    {
        self::init();
        
        // Check cache
        if (isset(self::$cache[$type][$level])) {
            return self::$cache[$type][$level];
        }
        
        // Fallback to formula calculation
        try {
            $cost = Formulas::buildingCost($type, $level);
            $time = Formulas::buildingTime($type, $level, 0, false);
            
            return [
                'wood' => $cost[0],
                'clay' => $cost[1],
                'iron' => $cost[2],
                'crop' => $cost[3],
                'time' => $time,
            ];
        } catch (\Exception $e) {
            error_log('[BuildingCostCache] Failed to get cost for type=' . $type . ', level=' . $level);
            return null;
        }
    }
    
    /**
     * Check if cache is populated
     * 
     * @return bool
     */
    public static function isCached()
    {
        self::init();
        return self::$cache !== null && !empty(self::$cache);
    }
    
    /**
     * Clear the cache (regenerate next time)
     */
    public static function clearCache()
    {
        self::$cache = null;
        
        if (self::$redis && self::$redis->isAvailable()) {
            self::$redis->delete(self::$cacheKey);
            error_log('[BuildingCostCache] Cache cleared');
        }
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Stats
     */
    public static function getStats()
    {
        self::init();
        
        $typeCount = is_array(self::$cache) ? count(self::$cache) : 0;
        $totalCosts = 0;
        
        if (is_array(self::$cache)) {
            foreach (self::$cache as $type => $levels) {
                $totalCosts += count($levels);
            }
        }
        
        return [
            'cached' => self::isCached(),
            'type_count' => $typeCount,
            'total_costs' => $totalCosts,
            'redis_available' => self::$redis ? self::$redis->isAvailable() : false,
        ];
    }
}
