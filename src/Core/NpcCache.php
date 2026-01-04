<?php

namespace Core;

use Redis;
use function logError;

/**
 * NPC Redis Caching Layer
 * Centralized caching with jitter to prevent cache stampedes
 */
class NpcCache
{
    /** @var Redis|null */
    private static $redis = null;
    
    /** @var bool */
    private static $enabled = true;
    
    /**
     * Cache key TTL constants (in seconds)
     */
    const TTL_VILLAGE_BUILDINGS = 300;  // 5 minutes
    const TTL_VILLAGE_TROOPS = 120;     // 2 minutes
    const TTL_ALLIANCE_MEMBERS = 600;   // 10 minutes
    const TTL_SCOUT_REPORT = 3600;      // 1 hour
    const TTL_NPC_STATE = 180;          // 3 minutes
    const TTL_SERVER_SETTINGS = 1800;   // 30 minutes
    
    /**
     * Jitter amounts (in seconds)
     */
    const JITTER_VILLAGE_BUILDINGS = 30;
    const JITTER_VILLAGE_TROOPS = 15;
    const JITTER_ALLIANCE_MEMBERS = 60;
    const JITTER_SCOUT_REPORT = 300;
    const JITTER_NPC_STATE = 20;
    const JITTER_SERVER_SETTINGS = 180;
    
    /**
     * Initialize Redis connection
     */
    private static function getRedis(): ?Redis
    {
        if (self::$redis !== null) {
            return self::$redis;
        }
        
        try {
            $redis = new Redis();
            
            // Try to connect to Redis
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = (int)(getenv('REDIS_PORT') ?: 6379);
            
            if (!$redis->connect($host, $port, 2.0)) {
                logError("Redis connection failed: $host:$port");
                self::$enabled = false;
                return null;
            }
            
            // Optional auth
            $password = getenv('REDIS_PASSWORD');
            if ($password) {
                $redis->auth($password);
            }
            
            // Select database (default 0)
            $db = (int)(getenv('REDIS_DB') ?: 0);
            $redis->select($db);
            
            self::$redis = $redis;
            return $redis;
            
        } catch (\Exception $e) {
            logError("Redis initialization failed: " . $e->getMessage());
            self::$enabled = false;
            return null;
        }
    }
    
    /**
     * Get value from cache, or execute fallback and cache result
     * 
     * @param string $key Cache key
     * @param callable $fallback Function to execute on cache miss
     * @param int $ttl Base TTL in seconds
     * @param int $jitter Jitter amount in seconds (default 30)
     * @return mixed Cached value or fallback result
     */
    public static function get(string $key, callable $fallback, int $ttl, int $jitter = 30)
    {
        if (!self::$enabled) {
            // Cache disabled, execute fallback directly
            return $fallback();
        }
        
        $redis = self::getRedis();
        if (!$redis) {
            return $fallback();
        }
        
        try {
            // Try to get from cache
            $cached = $redis->get($key);
            
            if ($cached !== false) {
                // Cache hit
                $unserialized = @unserialize($cached);
                if ($unserialized !== false) {
                    return $unserialized;
                }
            }
            
            // Cache miss - execute fallback
            $value = $fallback();
            
            // Cache the result with jitter
            self::set($key, $value, $ttl, $jitter);
            
            return $value;
            
        } catch (\Exception $e) {
            logError("Redis get failed for key '$key': " . $e->getMessage());
            return $fallback();
        }
    }
    
    /**
     * Set value in cache with jittered TTL
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Base TTL in seconds
     * @param int $jitter Jitter amount in seconds (default 30)
     */
    public static function set(string $key, $value, int $ttl, int $jitter = 30): void
    {
        if (!self::$enabled) {
            return;
        }
        
        $redis = self::getRedis();
        if (!$redis) {
            return;
        }
        
        try {
            // Apply jitter to TTL
            $actualTTL = $ttl + random_int(-$jitter, $jitter);
            
            // Ensure TTL doesn't go negative
            $actualTTL = max(1, $actualTTL);
            
            // Serialize and store
            $serialized = serialize($value);
            $redis->setex($key, $actualTTL, $serialized);
            
        } catch (\Exception $e) {
            logError("Redis set failed for key '$key': " . $e->getMessage());
        }
    }
    
    /**
     * Delete a single cache key
     * 
     * @param string $key Cache key to delete
     */
    public static function delete(string $key): void
    {
        if (!self::$enabled) {
            return;
        }
        
        $redis = self::getRedis();
        if (!$redis) {
            return;
        }
        
        try {
            $redis->del($key);
        } catch (\Exception $e) {
            logError("Redis delete failed for key '$key': " . $e->getMessage());
        }
    }
    
    /**
     * Delete all keys matching a pattern
     * 
     * @param string $pattern Pattern to match (e.g., "village:123:*")
     */
    public static function deletePattern(string $pattern): void
    {
        if (!self::$enabled) {
            return;
        }
        
        $redis = self::getRedis();
        if (!$redis) {
            return;
        }
        
        try {
            // Use SCAN to avoid blocking
            $iterator = null;
            while ($keys = $redis->scan($iterator, $pattern, 100)) {
                if (!empty($keys)) {
                    $redis->del($keys);
                }
            }
        } catch (\Exception $e) {
            logError("Redis deletePattern failed for pattern '$pattern': " . $e->getMessage());
        }
    }
    
    /**
     * Helper: Generate cache key for village buildings
     */
    public static function keyVillageBuildings(int $villageId): string
    {
        return "village:{$villageId}:buildings";
    }
    
    /**
     * Helper: Generate cache key for village troops
     */
    public static function keyVillageTroops(int $villageId): string
    {
        return "village:{$villageId}:troops";
    }
    
    /**
     * Helper: Generate cache key for alliance members
     */
    public static function keyAllianceMembers(int $allianceId): string
    {
        return "alliance:{$allianceId}:members";
    }
    
    /**
     * Helper: Generate cache key for scout report
     */
    public static function keyScoutReport(int $villageId): string
    {
        return "scout:{$villageId}";
    }
    
    /**
     * Helper: Generate cache key for NPC state
     */
    public static function keyNpcState(int $npcId): string
    {
        return "npc:{$npcId}:state";
    }
    
    /**
     * Helper: Generate cache key for server settings
     */
    public static function keyServerSettings(int $serverId): string
    {
        return "server:{$serverId}:settings";
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Statistics array
     */
    public static function getStats(): array
    {
        $redis = self::getRedis();
        if (!$redis) {
            return ['enabled' => false];
        }
        
        try {
            $info = $redis->info('stats');
            
            return [
                'enabled' => true,
                'total_connections' => $info['total_connections_received'] ?? 0,
                'total_commands' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => self::calculateHitRate($info),
                'used_memory_human' => $info['used_memory_human'] ?? 'N/A'
            ];
        } catch (\Exception $e) {
            return ['enabled' => true, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Calculate cache hit rate percentage
     */
    private static function calculateHitRate(array $info): float
    {
        $hits = (int)($info['keyspace_hits'] ?? 0);
        $misses = (int)($info['keyspace_misses'] ?? 0);
        
        if ($hits + $misses === 0) {
            return 0.0;
        }
        
        return round(($hits / ($hits + $misses)) * 100, 2);
    }
    
    /**
     * Flush all cache (use with caution!)
     */
    public static function flushAll(): void
    {
        $redis = self::getRedis();
        if (!$redis) {
            return;
        }
        
        try {
            $redis->flushDB();
            logError("Redis cache flushed");
        } catch (\Exception $e) {
            logError("Redis flush failed: " . $e->getMessage());
        }
    }
}
