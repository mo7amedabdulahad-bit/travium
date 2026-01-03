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
        // 1. Determine Global Settings & Phase
        $settings = NpcConfig::getServerSettings();
        if (!$settings) return; // Not configured

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
        // Let's assume standard 5 types for templates: Raider, Guardian, Supplier, Diplomat, Assassin
        // If not found, default to 'Apprentice' or similar in templates? 
        // For safe logic, let's map unknown to 'Guardian'
        $validPersonalities = ['Raider', 'Guardian', 'Supplier', 'Diplomat', 'Assassin'];
        if (!in_array($personality, $validPersonalities)) $personality = 'Guardian';

        $template = NpcConfig::getPersonalityTemplate($personality, $phase);
        if (!$template) {
            logError("NPC {$npcRow['id']}: No template found for personality=$personality, phase=$phase");
            return;
        }
        logError("NPC {$npcRow['id']}: Using template for $personality / $phase");

        // 3. Get Villages
        $db = DB::getInstance();
        $uid = (int)$npcRow['id'];
        $villages = $db->query("SELECT kid, capital FROM vdata WHERE owner=$uid");
        
        $warVillageId = $npcRow['war_village_id'];

        while ($village = $villages->fetch_assoc()) {
            $kid = $village['kid'];
            
            // 4. Per-Village Actions
            
            // Mistake Rate Check (Difficulty)
            if (mt_rand(1, 100) <= $policy['mistake_rate_percent']) {
                continue; // Skip this village this tick
            }

            // A. Buildings
            if (isset($template['build_priorities'])) {
                 NpcBuildingManager::executeBuilds($kid, $template['build_priorities'], $policy['action_budget_multiplier']);
            }

            // B. Troops
            if (isset($template['troop_template'])) {
                NpcTroopManager::executeTroopProduction($kid, $template['troop_template'], $policy['action_budget_multiplier']);
            }

            // C. Special Logic (War vs Passive)
            // If it's the war village, execute military AI
            // If not, do passive logic
            if ($kid != $warVillageId) {
                NpcPassiveVillage::doPassiveAction($kid);
            } else {
                logError("NPC {$npcRow['id']}: Executing war village logic for village $kid");
                // Phase 4: War Village Logic
                self::executeWarVillageLogic($kid, $npcRow, $template, $policy);
            }
        }
    }

    /**
     * Execute war village military AI
     * 
     * @param int $warVillageId The war village ID
     * @param array $npcRow NPC user row
     * @param array $template Personality template
     * @param array $policy Difficulty policy
     */
    private static function executeWarVillageLogic($warVillageId, $npcRow, $template, $policy)
    {
        // Phase 5: Check retaliation list first
        $retaliationTargets = NpcRetaliationManager::getRetaliationTargets($npcRow['id']);
        
        $target = null;
        
        // Difficulty-based retaliation priority (same as reinforcement rates)
        $difficulty = $npcRow['npc_difficulty'] ?? 'Medium';
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
}
