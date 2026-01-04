<?php

namespace Core;

use Core\Database\DB;
use function logError;

class NpcScriptEngine
{
    /**
     * Execute a single tick for an NPC.
     * 
     * @param array $npcRow user row from db
     */
    public static function executeTick($npcRow)
    {
        // Start performance monitoring
        NpcPerformanceMonitor::startTick($npcRow['id']);
        
        try {
            // 1. Determine Global Settings & Phase
            $settings = NpcConfig::getServerSettings();
            if (!$settings) {
                logError("NPC {$npcRow['id']}: No server settings found, aborting");
                NpcPerformanceMonitor::endTick();
                return;
            }

        // Simple phase logic for now (Game age based)
        // Early: < 2 days, Mid: < 7 days, Late: > 7 days
        $ageHours = (time() - \Core\Config::getInstance()->game->start_time) / 3600;
        $phase = 'Early';
        if ($ageHours > 48) $phase = 'Mid';
        if ($ageHours > 168) $phase = 'Late';
        
        // 2. Load Policy & Template
        $difficulty = $npcRow['npc_difficulty'] ?? 'Medium';
        $policy = NpcConfig::getDifficultyPolicy($difficulty);
        
        $personality = $npcRow['npc_personality'] ?? 'Balanced';
        // Map Phase 1 personalities to template types if needed, or use direct
        // Assuming template keys match directly or we map them.
            // Load Personality Template
            $template = self::getTemplate($npcRow);
            if (!$template) {
                // Try fallback if primary fails
                $template = self::getTemplate($npcRow);
                if (!$template) {
                    NpcPerformanceMonitor::endTick();
                    return; // Fail silent
                }
            }

            // Execute logic using template parameters
            if (isset($template['build_priorities_json']) && !empty($template['build_priorities_json'])) {
                 $queue = $template['build_priorities_json'];
                 // If stored as JSON string in DB, decode it
                 if (is_string($queue)) {
                     $queue = json_decode($queue, true);
                 }
                 if (is_array($queue)) {
                     foreach ($queue as $buildingName) {
                         if (!empty($buildingName)) {
                             NpcBuildingManager::ensureBuilding($npcRow['id'], $buildingName, $template['behavior_params_json']['build_rate'] ?? 50);
                         }
                     }
                 }
            }
            
            // Phase 6: World Wonder Operations
            if (!empty($npcRow['ww_operation_state']) && $npcRow['ww_operation_state'] !== 'Idle') {
                NpcWWOperations::progressWWOperation($npcRow);
                NpcPerformanceMonitor::recordAction('ww_operation_tick', [
                    'state' => $npcRow['ww_operation_state'],
                    'role' => $npcRow['ww_alliance_role']
                ]);
            }
            
            // Phase 7: Village Expansion Check
            if (NpcExpansionManager::checkExpansionEligibility($npcRow)) {
                NpcExpansionManager::planExpansion($npcRow);
                NpcPerformanceMonitor::recordAction('expansion_planned');
            }
            
            // Phase 7: Accelerated development for new villages
            self::developNewVillages($npcRow);
            
            // War Village Logic (Attacking/Raiding)
            // Only if war_village_id is set
            if (!empty($npcRow['war_village_id'])) {
                self::executeWarVillageLogic($npcRow, $template);
            }
            
            // End performance monitoring and log metrics
            NpcPerformanceMonitor::endTick(true);
            
        } catch (\Throwable $e) {
            // Log only actual errors
            if (function_exists('logError')) {
                logError("NPC Engine Error for UID {$npcRow['id']}: " . $e->getMessage());
            }
            NpcPerformanceMonitor::endTick();
        }
    }

    /**
     * Execute war village military AI
     * 
     * @param array $npc NPC user row
     * @param array $template Personality template
     */
    private static function executeWarVillageLogic($npc, $template)
    {
        $warVillageId = $npc['war_village_id'];
        $policy = NpcConfig::getDifficultyPolicy($npc['npc_difficulty'] ?? 'Medium');

        // Phase 5: Check retaliation list first
        $retaliationTargets = NpcRetaliationManager::getRetaliationTargets($npc['id']);
        
        $target = null;
        
        // Difficulty-based retaliation priority (same as reinforcement rates)
        $difficulty = $npc['npc_difficulty'] ?? 'Medium';
        $retaliationChances = [
            'Easy' => 50,      // 50% chance
            'Medium' => 70,    // 70% chance
            'Hard' => 100      // 100% always retaliate
        ];
        $retaliationChance = $retaliationChances[$difficulty] ?? 70;
        
        if (!empty($retaliationTargets) && mt_rand(1, 100) <= $retaliationChance) {
           $target = self::selectRetaliationTarget($warVillageId, $retaliationTargets);
        }
        
        // If no retaliation target selected, use normal target selection
        if (!$target) {
            $target = NpcTargetSelector::selectTarget($warVillageId, $template, $policy);
            if ($target) {
                logError("NPC war village $warVillageId: Selected normal target $target");
            }
        }
        
        if (!$target) {
            logError("NPC war village $warVillageId: No valid targets found");
            return; // No valid targets
        }

        // 3. Decide: Raid or Attack?
        // 50% chance for raid, 50% for attack (fully aggressive)
        $action = mt_rand(0, 1) ? 'raid' : 'attack';
        logError("NPC war village $warVillageId: Executing $action on target $target");
        
        if ($action === 'raid') {
            NpcRaidManager::executeRaid($warVillageId, $target, $template, $policy);
        } else {
            NpcAttackManager::executeAttack($warVillageId, $target, $template, $policy);
        }
    }
    
    /**
     * Select a retaliation target from the priority list
     * 
     * @param int $warVillageId War village ID
     * @param array $retaliationTargets Sorted retaliation list
     * @return int|null Target village ID
     */
    private static function selectRetaliationTarget($warVillageId, $retaliationTargets)
    {
        $db = DB::getInstance();
        
        // Try top 3 highest priority targets
        $topTargets = array_slice($retaliationTargets, 0, 3);
        
        foreach ($topTargets as $targetInfo) {
            $attackerId = (int)$targetInfo['user_id'];
            
            // Get attacker's villages
            $villages = $db->query("SELECT kid FROM vdata WHERE owner=$attackerId");
            
            $validVillages = [];
            while ($row = $villages->fetch_assoc()) {
                $kid = (int)$row['kid'];
                
                // Check if in range (use same 50-tile range as normal targeting)
                $coords = $db->query("SELECT x, y FROM wdata WHERE id=$kid")->fetch_assoc();
                $warCoords = $db->query("SELECT x, y FROM wdata WHERE id=$warVillageId")->fetch_assoc();
                
                if ($coords && $warCoords) {
                    $distance = max(abs($coords['x'] - $warCoords['x']), abs($coords['y'] - $warCoords['y']));
                    if ($distance <= 50) {
                        $validVillages[] = $kid;
                    }
                }
            }
            
            if (!empty($validVillages)) {
                // Return random village from this high-priority attacker
                return $validVillages[array_rand($validVillages)];
            }
        }
        
        return null; // No retaliation targets in range
    }
    
    /**
     * Get behavior template for an NPC
     * 
     * @param array $npcRow NPC user row
     * @return array|null Template with behavior parameters
     */
    private static function getTemplate($npcRow)
    {
        $personality = $npcRow['npc_personality'] ?? 'Balanced';
        
        // Basic template based on personality
        $templates = [
            'Aggressive' => [
                'behavior_params_json' => ['build_rate' => 70, 'military_focus' => 80],
                'build_priorities_json' => ['Barracks', 'Smithy', 'Academy', 'Stable']
            ],
            'Raider' => [
                'behavior_params_json' => ['build_rate' => 60, 'military_focus' => 70],
                'build_priorities_json' => ['Stable', 'Barracks', 'Marketplace']
            ],
            'Assassin' => [
                'behavior_params_json' => ['build_rate' => 55, 'military_focus' => 65],
                'build_priorities_json' => ['Academy', 'Smithy', 'Stable']
            ],
            'Balanced' => [
                'behavior_params_json' => ['build_rate' => 50, 'military_focus' => 50],
                'build_priorities_json' => ['Barracks', 'Warehouse', 'Granary', 'Smithy']
            ],
            'Builder' => [
                'behavior_params_json' => ['build_rate' => 80, 'military_focus' => 30],
                'build_priorities_json' => ['Warehouse', 'Granary', 'Marketplace', 'Main Building']
            ],
            'Defensive' => [
                'behavior_params_json' => ['build_rate' => 60, 'military_focus' => 60],
                'build_priorities_json' => ['Barracks', 'Wall', 'Warehouse', 'Granary']
            ]
        ];
        
        return $templates[$personality] ?? $templates['Balanced'];
    }
    
    /**
     * Develop new villages with accelerated build orders
     * 
     * @param array $npcRow NPC user row
     */
    private static function developNewVillages($npcRow)
    {
        $db = DB::getInstance();
        
        // Get all new villages  (< 48 hours old)
        $newVillages = $db->query("
            SELECT nv.village_id, nv.village_role, nv.founded_at
            FROM npc_villages nv
            WHERE nv.npc_player_id = {$npcRow['id']}
              AND nv.founded_at IS NOT NULL
              AND TIMESTAMPDIFF(HOUR, nv.founded_at, NOW()) < 48
        ");
        
        if (!$newVillages || $newVillages->num_rows === 0) return;
        
        while ($village = $newVillages->fetch_assoc()) {
            NpcExpansionManager::developNewVillage(
                $village['village_id'], 
                $village['village_role']
            );
        }
    }
}
