<?php

namespace Core\Cache;

use Exception;

/**
 * Redis Cache Helper Class
 * 
 * Purpose: Centralized Redis management with graceful degradation
 * Features:
 * - Automatic connection handling
 * - Graceful fallback if Redis unavailable
 * - Key namespacing
 * - JSON serialization
 * - TTL management
 * 
 * Usage:
 *   $cache = RedisCache::getInstance();
 *   $cache->set('key', $data, 3600);
 *   $data = $cache->get('key');
 */
class RedisCache
{
    private static $instance = null;
    private $redis = null;
    private $connected = false;
    private $prefix = 'travian_';
    private $defaultTTL = 3600; // 1 hour
    
    /**
     * Singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor - use getInstance()
     */
    private function __construct()
    {
        $this->connect();
    }
    
    /**
     * Connect to Redis server
     */
    private function connect()
    {
        // Check if Redis extension is loaded
        if (!extension_loaded('redis')) {
            error_log('[RedisCache] Redis extension not loaded - cache disabled');
            return;
        }
        
        try {
            $this->redis = new \Redis();
            
            // Try to connect with timeout
            $this->connected = $this->redis->connect('127.0.0.1', 6379, 2.0);
            
            if (!$this->connected) {
                throw new Exception('Connection failed');
            }
            
            // Test connection with PING
            $pong = $this->redis->ping();
            if ($pong !== '+PONG' && $pong !== true) {
                throw new Exception('PING test failed');
            }
            
            // Set serialization mode
            $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
            
        } catch (Exception $e) {
            $this->connected = false;
            $this->redis = null;
            error_log('[RedisCache] Connection failed: ' . $e->getMessage() . ' - cache disabled');
        }
    }
    
    /**
     * Check if Redis is available
     */
    public function isAvailable()
    {
        return $this->connected && $this->redis !== null;
    }
    
    /**
     * Get value from cache
     * 
     * @param string $key Cache key
     * @return mixed|null Returns cached value or null if not found/unavailable
     */
    public function get($key)
    {
        if (!$this->isAvailable()) {
            return null;
        }
        
        try {
            $fullKey = $this->prefix . $key;
            $value = $this->redis->get($fullKey);
            
            // Redis returns false if key doesn't exist
            if ($value === false) {
                return null;
            }
            
            return $value;
            
        } catch (Exception $e) {
            error_log('[RedisCache] GET failed for key "' . $key . '": ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Set value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache (will be serialized)
     * @param int $ttl Time-to-live in seconds (0 = no expiration)
     * @return bool Success
     */
    public function set($key, $value, $ttl = null)
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $fullKey = $this->prefix . $key;
            $ttl = $ttl ?? $this->defaultTTL;
            
            if ($ttl > 0) {
                // Set with expiration
                return $this->redis->setex($fullKey, $ttl, $value);
            } else {
                // Set without expiration
                return $this->redis->set($fullKey, $value);
            }
            
        } catch (Exception $e) {
            error_log('[RedisCache] SET failed for key "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete key from cache
     * 
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete($key)
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $fullKey = $this->prefix . $key;
            return $this->redis->del($fullKey) > 0;
            
        } catch (Exception $e) {
            error_log('[RedisCache] DELETE failed for key "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete multiple keys matching a pattern
     * 
     * @param string $pattern Key pattern (e.g., 'npc_personality_*')
     * @return int Number of keys deleted
     */
    public function deletePattern($pattern)
    {
        if (!$this->isAvailable()) {
            return 0;
        }
        
        try {
            $fullPattern = $this->prefix . $pattern;
            $keys = $this->redis->keys($fullPattern);
            
            if (empty($keys)) {
                return 0;
            }
            
            return $this->redis->del($keys);
            
        } catch (Exception $e) {
            error_log('[RedisCache] DELETE pattern failed for "' . $pattern . '": ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if key exists
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function exists($key)
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $fullKey = $this->prefix . $key;
            return $this->redis->exists($fullKey) > 0;
            
        } catch (Exception $e) {
            error_log('[RedisCache] EXISTS check failed for key "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get TTL (time-to-live) for a key
     * 
     * @param string $key Cache key
     * @return int Seconds until expiration, -1 if no expiration, -2 if key doesn't exist
     */
    public function ttl($key)
    {
        if (!$this->isAvailable()) {
            return -2;
        }
        
        try {
            $fullKey = $this->prefix . $key;
            return $this->redis->ttl($fullKey);
            
        } catch (Exception $e) {
            error_log('[RedisCache] TTL check failed for key "' . $key . '": ' . $e->getMessage());
            return -2;
        }
    }
    
    /**
     * Increment a counter
     * 
     * @param string $key Cache key
     * @param int $amount Amount to increment by
     * @return int|false New value or false on failure
     */
    public function increment($key, $amount = 1)
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            $fullKey = $this->prefix . $key;
            return $this->redis->incrBy($fullKey, $amount);
            
        } catch (Exception $e) {
            error_log('[RedisCache] INCREMENT failed for key "' . $key . '": ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Flush all cache keys (DANGEROUS - use with caution!)
     * 
     * @return bool Success
     */
    public function flush()
    {
        if (!$this->isAvailable()) {
            return false;
        }
        
        try {
            return $this->redis->flushDB();
            
        } catch (Exception $e) {
            error_log('[RedisCache] FLUSH failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cache statistics
     * 
     * @return array|null Stats array or null if unavailable
     */
    public function getStats()
    {
        if (!$this->isAvailable()) {
            return null;
        }
        
        try {
            $info = $this->redis->info();
            $dbSize = $this->redis->dbSize();
            
            return [
                'connected' => true,
                'version' => $info['redis_version'] ?? 'unknown',
                'memory_used' => $info['used_memory_human'] ?? 'unknown',
                'total_keys' => $dbSize,
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
            ];
            
        } catch (Exception $e) {
            error_log('[RedisCache] Stats retrieval failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Calculate cache hit rate percentage
     */
    private function calculateHitRate($info)
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        if ($total === 0) {
            return 0;
        }
        
        return round(($hits / $total) * 100, 2);
    }
    
    /**
     * Close Redis connection
     */
    public function close()
    {
        if ($this->redis !== null) {
            try {
                $this->redis->close();
            } catch (Exception $e) {
                // Ignore close errors
            }
            $this->redis = null;
            $this->connected = false;
        }
    }
    
    /**
     * Destructor - close connection
     */
    public function __destruct()
    {
        $this->close();
    }
}
