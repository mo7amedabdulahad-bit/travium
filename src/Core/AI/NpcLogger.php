<?php

namespace Core\AI;

/**
 * NPC Activity Logger
 * 
 * Logs all NPC actions for monitoring and debugging
 * 
 * @package Core\AI
 */
class NpcLogger
{
    private static $logFile = '/home/travium/logs/npc_activity.log';
    private static $detailedLog = '/home/travium/logs/npc_detailed.log';
    
    /**
     * Log general NPC action
     * 
     * @param int $uid User ID
     * @param string $action Action type (build, train, raid, etc)
     * @param string $details Details of the action
     * @param array $data Additional data
     */
    public static function log($uid, $action, $details, $data = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        
        // Format: [TIME] [NPC_UID] ACTION: Details
        $logLine = sprintf(
            "[%s] [NPC:%d] %s: %s",
            $timestamp,
            $uid,
            strtoupper($action),
            $details
        );
        
        // Add data if present
        if (!empty($data)) {
            $logLine .= " | " . json_encode($data);
        }
        
        $logLine .= "\n";
        
        // Write to log file (suppress errors if file not writable)
        @file_put_contents(self::$logFile, $logLine, FILE_APPEND);
        
        // Also log to detailed log
        self::logDetailed($uid, $action, $details, $data);
    }
    
    /**
     * Log detailed action with full context
     */
    private static function logDetailed($uid, $action, $details, $data)
    {
        $timestamp = date('Y-m-d H:i:s');
        
        $detailedEntry = [
            'timestamp' => $timestamp,
            'uid' => $uid,
            'action' => $action,
            'details' => $details,
            'data' => $data
        ];
        
        $logLine = json_encode($detailedEntry) . "\n";
        @file_put_contents(self::$detailedLog, $logLine, FILE_APPEND);
    }
    
    /**
     * Log building selection
     */
    public static function logBuilding($uid, $buildingId, $buildingName, $reason)
    {
        self::log($uid, 'BUILD', "Selected $buildingName (ID: $buildingId)", [
            'building_id' => $buildingId,
            'building_name' => $buildingName,
            'reason' => $reason
        ]);
    }
    
    /**
     * Log unit training
     */
    public static function logTraining($uid, $unitId, $unitName, $amount)
    {
        self::log($uid, 'TRAIN', "Training $amount x $unitName (ID: $unitId)", [
            'unit_id' => $unitId,
            'unit_name' => $unitName,
            'amount' => $amount
        ]);
    }
    
    /**
     * Log raid sent
     */
    public static function logRaid($uid, $fromKid, $toKid, $targetName, $distance, $troops)
    {
        $troopCount = array_sum($troops);
        self::log($uid, 'RAID', "Sent $troopCount troops to $targetName (Distance: $distance tiles)", [
            'from_kid' => $fromKid,
            'to_kid' => $toKid,
            'target_name' => $targetName,
            'distance' => $distance,
            'troops' => $troops,
            'total_troops' => $troopCount
        ]);
    }
    
    /**
     * Log raid target selection
     */
    public static function logTargetSelection($uid, $targetCount, $selectedTarget)
    {
        self::log($uid, 'TARGET', "Selected target from $targetCount options: {$selectedTarget['name']}", [
            'available_targets' => $targetCount,
            'selected_kid' => $selectedTarget['kid'],
            'selected_name' => $selectedTarget['name'],
            'distance' => $selectedTarget['distance'],
            'score' => $selectedTarget['score']
        ]);
    }
    
    /**
     * Log failed action
     */
    public static function logFailure($uid, $action, $reason)
    {
        self::log($uid, 'FAILED', "$action failed: $reason", [
            'action' => $action,
            'reason' => $reason
        ]);
    }
    
    /**
     * Log AI decision cycle start
     */
    public static function logCycleStart($uid, $iterations)
    {
        self::log($uid, 'CYCLE', "Starting AI cycle with $iterations iterations", [
            'iterations' => $iterations
        ]);
    }
    
    /**
     * Get recent logs for an NPC
     */
    public static function getRecentLogs($uid, $limit = 50)
    {
        if (!file_exists(self::$logFile)) {
            return [];
        }
        
        $logs = file(self::$logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $npcLogs = [];
        
        // Filter logs for this NPC (reverse order for recent first)
        foreach (array_reverse($logs) as $log) {
            if (strpos($log, "uid=$uid") !== false || preg_match("/\[([^\]]+)\]/", $log)) {
                $npcLogs[] = $log;
                if (count($npcLogs) >= $limit) {
                    break;
                }
            }
        }
        
        return $npcLogs;
    }
    
    /**
     * Get all logs (tail -n style)
     */
    public static function tailLogs($lines = 100)
    {
        if (!file_exists(self::$logFile)) {
            return "No log file found at " . self::$logFile;
        }
        
        $logs = file(self::$logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return implode("\n", array_slice($logs, -$lines));
    }
    
    /**
     * Clear logs
     */
    public static function clearLogs()
    {
        @unlink(self::$logFile);
        @unlink(self::$detailedLog);
    }
}
