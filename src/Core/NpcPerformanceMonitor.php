<?php

namespace Core;

use Core\Database\DB;
use function logError;

/**
 * NPC Performance Monitor
 * Tracks and logs performance metrics for the NPC system
 */
class NpcPerformanceMonitor
{
    /** @var array Metrics collected during current tick */
    private static $currentMetrics = [];
    
    /** @var float Tick start time */
    private static $tickStartTime = 0;
    
    /** @var int Query counter */
    private static $queryCount = 0;
    
    /** @var array Cache statistics */
    private static $cacheStats = [
        'hits' => 0,
        'misses' => 0
    ];
    
    /**
     * Start tracking metrics for an NPC tick
     * 
     * @param int $npcId NPC user ID
     */
    public static function startTick(int $npcId): void
    {
        self::$tickStartTime = microtime(true);
        self::$queryCount = 0;
        self::$cacheStats = ['hits' => 0, 'misses' => 0];
        self::$currentMetrics = [
            'npc_id' => $npcId,
            'timestamp' => date('Y-m-d\TH:i:s\Z'),
            'actions_taken' => []
        ];
    }
    
    /**
     * Record an action taken during this tick
     * 
     * @param string $action Action name (e.g., "build_queued", "raid_sent")
     * @param array $context Additional context
     */
    public static function recordAction(string $action, array $context = []): void
    {
        self::$currentMetrics['actions_taken'][] = [
            'action' => $action,
            'context' => $context,
            'timestamp_ms' => round((microtime(true) - self::$tickStartTime) * 1000)
        ];
    }
    
    /**
     * Increment query counter
     */
    public static function incrementQueryCount(): void
    {
        self::$queryCount++;
    }
    
    /**
     * Record cache hit
     */
    public static function recordCacheHit(): void
    {
        self::$cacheStats['hits']++;
    }
    
    /**
     * Record cache miss
     */
    public static function recordCacheMiss(): void
    {
        self::$cacheStats['misses']++;
    }
    
    /**
     * End tick and log metrics
     * 
     * @param bool $writeToLog Whether to write to log file
     */
    public static function endTick(bool $writeToLog = true): void
    {
        $duration = microtime(true) - self::$tickStartTime;
        
        self::$currentMetrics['tick_duration_ms'] = round($duration * 1000);
        self::$currentMetrics['queries_executed'] = self::$queryCount;
        self::$currentMetrics['cache_hits'] = self::$cacheStats['hits'];
        self::$currentMetrics['cache_misses'] = self::$cacheStats['misses'];
        self::$currentMetrics['memory_peak_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        
        // Calculate cache hit rate
        $totalCacheOps = self::$cacheStats['hits'] + self::$cacheStats['misses'];
        self::$currentMetrics['cache_hit_rate'] = $totalCacheOps > 0 
            ? round((self::$cacheStats['hits'] / $totalCacheOps) * 100, 2)
            : 0;
        
        if ($writeToLog) {
            self::writeLogEntry(self::$currentMetrics);
        }
        
        // Check for performance warnings
        self::checkPerformanceThresholds();
    }
    
    /**
     * Write log entry to performance log file
     * 
     * @param array $metrics Metrics to log
     */
    private static function writeLogEntry(array $metrics): void
    {
        $logDir = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/npc_performance.log';
        $json = json_encode($metrics, JSON_UNESCAPED_SLASHES) . "\n";
        
        @file_put_contents($logFile, $json, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Check if metrics exceed warning thresholds
     */
    private static function checkPerformanceThresholds(): void
    {
        $metrics = self::$currentMetrics;
        
        // Threshold: Tick duration >2000ms
        if ($metrics['tick_duration_ms'] > 2000) {
            logError("Performance Warning: NPC {$metrics['npc_id']} tick took {$metrics['tick_duration_ms']}ms (threshold: 2000ms)");
        }
        
        // Threshold: Query count >20
        if ($metrics['queries_executed'] > 20) {
            logError("Performance Warning: NPC {$metrics['npc_id']} executed {$metrics['queries_executed']} queries (threshold: 20)");
        }
        
        // Threshold: Cache hit rate <50%
        if ($metrics['cache_hit_rate'] < 50 && ($metrics['cache_hits'] + $metrics['cache_misses']) > 5) {
            logError("Performance Warning: NPC {$metrics['npc_id']} cache hit rate {$metrics['cache_hit_rate']}% (threshold: 50%)");
        }
        
        // Threshold: Memory >128MB
        if ($metrics['memory_peak_mb'] > 128) {
            logError("Performance Warning: NPC {$metrics['npc_id']} memory usage {$metrics['memory_peak_mb']}MB (threshold: 128MB)");
        }
    }
    
    /**
     * Get aggregated statistics from log file
     * 
     * @param int $hours Number of hours to analyze (default 1)
     * @return array Aggregated statistics
     */
    public static function getAggregatedStats(int $hours = 1): array
    {
        $logFile = dirname(__DIR__, 2) . '/logs/npc_performance.log';
        
        if (!file_exists($logFile)) {
            return ['error' => 'Log file not found'];
        }
        
        $cutoffTime = time() - ($hours * 3600);
        $lines = [];
        
        $file = fopen($logFile, 'r');
        if (!$file) {
            return ['error' => 'Cannot read log file'];
        }
        
        while (($line = fgets($file)) !== false) {
            $data = @json_decode($line, true);
            if ($data && isset($data['timestamp'])) {
                $timestamp = strtotime($data['timestamp']);
                if ($timestamp >= $cutoffTime) {
                    $lines[] = $data;
                }
            }
        }
        fclose($file);
        
        if (empty($lines)) {
            return ['error' => 'No data in time range'];
        }
        
        // Calculate statistics
        $durations = array_column($lines, 'tick_duration_ms');
        $queries = array_column($lines, 'queries_executed');
        $cacheHitRates = array_column($lines, 'cache_hit_rate');
        $memories = array_column($lines, 'memory_peak_mb');
        
        sort($durations);
        $count = count($durations);
        
        return [
            'time_range_hours' => $hours,
            'total_ticks' => $count,
            'tick_duration' => [
                'avg_ms' => round(array_sum($durations) / $count, 2),
                'min_ms' => min($durations),
                'max_ms' => max($durations),
                'p50_ms' => $durations[(int)($count * 0.5)],
                'p95_ms' => $durations[(int)($count * 0.95)],
                'p99_ms' => $durations[(int)($count * 0.99)]
            ],
            'queries' => [
                'avg' => round(array_sum($queries) / $count, 2),
                'min' => min($queries),
                'max' => max($queries)
            ],
            'cache_hit_rate' => [
                'avg_pct' => round(array_sum($cacheHitRates) / $count, 2)
            ],
            'memory' => [
                'avg_mb' => round(array_sum($memories) / $count, 2),
                'max_mb' => max($memories)
            ]
        ];
    }
    
    /**
     * Get current tick metrics (for debugging)
     * 
     * @return array Current metrics
     */
    public static function getCurrentMetrics(): array
    {
        return self::$currentMetrics;
    }
    
    /**
     * Reset metrics (for testing)
     */
    public static function reset(): void
    {
        self::$currentMetrics = [];
        self::$tickStartTime = 0;
        self::$queryCount = 0;
        self::$cacheStats = ['hits' => 0, 'misses' => 0];
    }
}
