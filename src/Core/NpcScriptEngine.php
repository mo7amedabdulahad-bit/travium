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
            // Log warning or fallback?
            return;
        }

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
        // 1. Scout new targets periodically (not implemented yet)
        // NpcScoutingManager::executeScouts($warVillageId, $template, $policy);

        // 2. Select target
        $target = NpcTargetSelector::selectTarget($warVillageId, $template, $policy);
        if (!$target) return; // No valid targets

        // 3. Decide: Raid or Attack?
        // 50% chance for raid, 50% for attack (fully aggressive)
        $action = mt_rand(0, 1) ? 'raid' : 'attack';
        
        if ($action === 'raid') {
            NpcRaidManager::executeRaid($warVillageId, $target, $template, $policy);
        } else {
            NpcAttackManager::executeAttack($warVillageId, $target, $template, $policy);
        }
    }
}
