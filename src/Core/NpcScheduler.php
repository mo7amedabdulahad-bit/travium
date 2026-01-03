<?php

namespace Core;

use Core\Database\DB;
use function logError;

class NpcScheduler
{
    /**
     * Process NPCs that are due for a tick.
     * 
     * @param int $serverId
     * @param int $maxPerRun Hard limit on NPCs to process per invocation
     * @return int Number of NPCs processed
     */
    public static function processDueNpcs($serverId = 1, $maxPerRun = 10)
    {
        $db = DB::getInstance();
        
        // 1. Select NPCs due for processing
        // Uses index idx_users_next_tick (access, next_tick_at)
        $result = $db->query("SELECT id, name, next_tick_at, tick_interval_seconds, war_village_id, npc_personality 
                              FROM users 
                              WHERE access = 3 
                              AND next_tick_at <= NOW() 
                              ORDER BY next_tick_at ASC 
                              LIMIT " . (int)$maxPerRun);

        if (!$result || $result->num_rows === 0) {
            return 0;
        }

        $processedCount = 0;
        
        while ($npc = $result->fetch_assoc()) {
            $start = microtime(true);
            
            try {
                // Execute NPC Logic
                // We'll use the ScriptEngine (Phase 3) here later. 
                // For now, we update the timestamp to ensure the loop continues.
                if (class_exists('Core\NpcScriptEngine')) {
                    // \Core\NpcScriptEngine::executeTick($npc);
                }

                $processedCount++;
                
                // Calculate next run time
                // Add interval to NOW() to ensure we don't fall behind if skipped
                $interval = (int)$npc['tick_interval_seconds'];
                if ($interval < 60) $interval = 60; // Safety floor
                
                $db->query("UPDATE users 
                            SET next_tick_at = DATE_ADD(NOW(), INTERVAL $interval SECOND) 
                            WHERE id = " . $npc['id']);

            } catch (\Exception $e) {
                logError("NPC Processing Error (ID: {$npc['id']}): " . $e->getMessage());
                // Bump future time even on error to prevent retry loops
                $db->query("UPDATE users 
                            SET next_tick_at = DATE_ADD(NOW(), INTERVAL 600 SECOND) 
                            WHERE id = " . $npc['id']);
            }
            
            // Performance guard: Check if we are taking too long
            if ((microtime(true) - $start) > 2.0) {
                 logError("NPC ID {$npc['id']} took too long (>2s) to process.");
            }
        }

        return $processedCount;
    }
}
