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
            // If it's the war village, we might do attacks (Phase 4)
            // If not, do passive logic
            if ($kid != $warVillageId) {
                NpcPassiveVillage::doPassiveAction($kid);
            } else {
                // Phase 4: NpcWarManager::executeWarLogic(...)
            }
        }
    }
}
