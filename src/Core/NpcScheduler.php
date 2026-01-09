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
        
        // Phase 5: Process world events BEFORE processing NPCs
        self::processWorldEvents($serverId);
        
        // 1. Select NPCs due for processing
        // Uses index idx_users_next_tick (access, next_tick_at)
        $result = $db->query("SELECT id, name, aid, next_tick_at, tick_interval_seconds, war_village_id, npc_personality, npc_difficulty, ww_operation_state, ww_alliance_role, expansion_plan_json, npc_memory_json 
                              FROM users 
                              WHERE access = 3 
                              AND next_tick_at <= NOW() 
                              ORDER BY next_tick_at ASC 
                              LIMIT " . (int)$maxPerRun);

        if (!$result || $result->num_rows === 0) {
            // logError("[NpcScheduler] No NPCs due for processing.");
            return 0;
        }

        // logError("[NpcScheduler] Found " . $result->num_rows . " NPCs due.");

        $processedCount = 0;
        
        while ($npc = $result->fetch_assoc()) {
            $start = microtime(true);
            
            try {
                // Execute NPC Logic via ScriptEngine
                if (class_exists('Core\NpcScriptEngine')) {
                    \Core\NpcScriptEngine::executeTick($npc);
                } else {
                    // BUGFIX: Log error if engine missing instead of silent failure
                    logError("NpcScheduler: Core\\NpcScriptEngine class not found - NPC ID {$npc['id']} skipped logic");
                }

                $processedCount++;
                
                // Calculate next run time with random stagger
                // Add interval to NOW() to ensure we don't fall behind if skipped
                $interval = (int)$npc['tick_interval_seconds'];
                if ($interval < 60) $interval = 60; // Safety floor
                
                // Add random stagger (0-30 seconds) to prevent all NPCs scheduling at same time
                $stagger = rand(0, 30);
                $totalDelay = $interval + $stagger;
                
                $updateResult = $db->query("UPDATE users 
                            SET next_tick_at = DATE_ADD(NOW(), INTERVAL $totalDelay SECOND) 
                            WHERE id = " . $npc['id']);
                            
                if (!$updateResult) {
                    logError("NpcScheduler: Failed to update next_tick_at for NPC {$npc['id']}: " . $db->mysqli->error);
                }

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

        // Log summary
        if ($processedCount > 0) {
            logError("[NpcScheduler] Processed $processedCount NPCs");
        }

        return $processedCount;
    }
    
    /**
     * Process world events (Phase 5)
     * 
     * @param int $serverId Server/world ID
     */
    private static function processWorldEvents($serverId)
    {
        $db = DB::getInstance();
        
        // Get unprocessed events (limit 20 per cycle to prevent lag)
        $events = $db->query("
            SELECT * FROM npc_world_events 
            WHERE server_id = $serverId AND processed_at IS NULL 
            ORDER BY created_at ASC 
            LIMIT 20
        ");
        
        if (!$events || $events->num_rows === 0) return;
        
        while ($event = $events->fetch_assoc()) {
            try {
                self::handleEvent($event);
                
                // Mark as processed
                $db->query("UPDATE npc_world_events SET processed_at = NOW() WHERE id = {$event['id']}");
            } catch (\Exception $e) {
                logError("Event Processing Error (Event ID: {$event['id']}): " . $e->getMessage());
                // Still mark as processed to prevent infinite retry
                $db->query("UPDATE npc_world_events SET processed_at = NOW() WHERE id = {$event['id']}");
            }
        }
    }
    
    /**
     * Handle a specific event type
     * 
     * @param array $event Event row from database
     */
    private static function handleEvent($event)
    {
        $db = DB::getInstance();
        
        switch ($event['event_type']) {
            case 'AllianceAttacked':
                // Get the attacked NPC
                $attackedNpcId = (int)$db->fetchScalar("
                    SELECT owner FROM vdata WHERE kid = {$event['target_village_id']}
                ");
                
                if ($attackedNpcId > 0) {
                    // 1. Coordinate alliance mutual defense
                    NpcAllianceCoordination::coordinateMutualDefense($attackedNpcId, $event['attacker_id']);
                    
                    // 2. Add attacker to retaliation list
                    NpcRetaliationManager::addRetaliationTarget($attackedNpcId, $event['attacker_id'], 1.0);
                }
                break;
                
            case 'WWPlanReleased':
                // Phase 6: Plans released, transition eligible NPCs to plan hunting
                NpcWWOperations::onPlansReleased($event['server_id']);
                break;
                
            case 'WWUnderAttack':
                // Phase 6: WW under attack, coordinate defense
                $wwVillageId = $event['ww_village_id'] ?? $event['target_village_id'];
                if ($wwVillageId) {
                    $npcId = (int)$db->fetchScalar("SELECT owner FROM vdata WHERE kid = $wwVillageId");
                    if ($npcId) {
                        NpcWWDefender::defendWorldWonder($npcId, $event);
                    }
                }
                break;
                
            case 'WWLevelUp':
                // Phase 6: Enemy WW leveled up, auto-attack if enabled
                $wwVillageId = $event['ww_village_id'] ?? $event['target_village_id'];
                if ($wwVillageId) {
                    NpcWWDefender::attackWWOnLevelUp($wwVillageId);
                }
                break;
                
            case 'WWDefeated':
                // Phase 6: WW destroyed/captured, handle fallback
                $npcId = (int)($event['target_npc_id'] ?? 0);
                if ($npcId) {
                    NpcWWOperations::handleWWDefeat($npcId);
                }
                break;
                
            case 'NpcDefeated':
                // Future: Handle major NPC defeat
                break;
        }
    }
}
