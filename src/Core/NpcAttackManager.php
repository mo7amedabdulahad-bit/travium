<?php

namespace Core;

use Core\Database\DB;
use Game\Formulas;
use Game\SpeedCalculator;
use Model\MovementsModel;

class NpcAttackManager
{
    /**
     * Execute a normal attack (conquest/destroy) from war village
     * 
     * @param int $warVillageId The attacking village ID
     * @param int $targetId The target village ID
     * @param array $template Personality template
     * @param array $policy Difficulty policy
     */
    public static function executeAttack($warVillageId, $targetId, $template, $policy)
    {
        $db = DB::getInstance();
        
        // Get village owner/race
        $villageData = $db->query("SELECT owner FROM vdata WHERE kid=$warVillageId")->fetch_assoc();
        if (!$villageData) return;
        
        $npcUserId = (int)$villageData['owner'];
        $npcRace = (int)$db->fetchScalar("SELECT race FROM users WHERE id=$npcUserId");
        
        // --- NPC Personality & Messaging Setup ---
        $personality = 'Normal';
        if (class_exists('Core\NpcConfig')) {
            $npcConf = NpcConfig::getNpcConfig($npcUserId);
            if ($npcConf && isset($npcConf['personality'])) {
                $personality = $npcConf['personality'];
            }
        }
        
        // Get available troops
        $units = $db->query("SELECT u1,u2,u3,u4,u5,u6,u7,u8,u9,u10,u11 FROM units WHERE kid=$warVillageId")->fetch_assoc();
        if (!$units) return;
        
        // --- Smart Troop Selection ---
        // Define Defensive Units (to exclude from attacks for non-aggressive NPCs)
        // Romans: 1=Leg(Hybrid), 2=Praet(Def)
        // Teutons: 12=Spear(Def), 15=Paladin(Def)
        // Gauls: 21=Phalanx(Def), 25=Druid(Def)
        // Egyptians: 51=Slave, 52=Ash
        // Huns: 62=Bow
        $defensiveUnits = [2, 12, 15, 21, 25, 51, 52, 62];
        
        $isAggressive = in_array($personality, ['Aggressive', 'Raider', 'Assassin']);
        
        $troopsToSend = [];
        $totalTroopsAvailable = 0;
        
        for ($i = 1; $i <= 10; $i++) {
            $available = (int)$units['u' . $i];
            $totalTroopsAvailable += $available;
            
            $unitId = ($npcRace - 1) * 10 + $i;
            
            // Logic: If NOT Aggressive, do NOT send dedicated defense units
            if (!$isAggressive && in_array($unitId, $defensiveUnits)) {
                $troopsToSend[$i] = 0;
            } else {
                // Send 80% of offensive/hybrid troops
                $troopsToSend[$i] = ($available > 0) ? max(1, floor($available * 0.8)) : 0;
            }
        }
        $troopsToSend[11] = 0; // Don't send hero
        
        // --- Retaliation Threshold ---
        // If total army size is too small, do not attack (retreat/build up)
        // Threshold: 50 units (example)
        if ($totalTroopsAvailable < 50) {
            // Send WARNING message instead of attacking
            if (class_exists('Core\NpcMessagingSystem')) {
                // Get target user ID
                $targetUserId = (int)$db->fetchScalar("SELECT owner FROM vdata WHERE kid=$targetId");
                $targetAccess = (int)$db->fetchScalar("SELECT access FROM users WHERE id=$targetUserId");
                
                // Only message real players
                if ($targetAccess < 3) {
                     $msg = NpcMessagingSystem::getWarningMessage($personality);
                     $mModel = new \Model\MessageModel();
                     $mModel->sendMessage($npcUserId, $targetUserId, 'Warning', $msg, 0);
                }
            }
            return; 
        }

        // Check if we have any troops selected to send
        if (array_sum($troopsToSend) == 0) return;
        
        // Calculate travel time
        $calculator = new SpeedCalculator();
        $calculator->setFrom($warVillageId);
        $calculator->setTo($targetId);
        
        $speeds = [];
        for ($i = 1; $i <= 10; $i++) {
            if ($troopsToSend[$i] > 0) {
                $unitId = ($npcRace - 1) * 10 + $i;
                $speeds[] = Formulas::uSpeed($unitId);
            }
        }
        
        if (empty($speeds)) return;
        
        $calculator->setMinSpeed($speeds);
        $travelTime = $calculator->calc();
        
        $startTime = time() * 1000;
        $endTime = $startTime + ($travelTime * 1000);
        
        // Create movement
        $movementModel = new MovementsModel();
        $movementModel->addMovement(
            $warVillageId,
            $targetId,
            $npcRace,
            $troopsToSend,
            0,                        // ctar1 (no catapult targeting for now)
            0,                        // ctar2
            0,                        // spyType
            false,                    // redeployHero
            0,                        // mode (0 = going)
            MovementsModel::ATTACKTYPE_NORMAL,  // attack_type (3 = normal attack)
            $startTime,
            $endTime,
            null
        );
        
        // Deduct troops
        $updateParts = [];
        for ($i = 1; $i <= 10; $i++) {
            if ($troopsToSend[$i] > 0) {
                $updateParts[] = "u$i = u$i - " . $troopsToSend[$i];
            }
        }
        
        if (!empty($updateParts)) {
            $db->query("UPDATE units SET " . implode(', ', $updateParts) . " WHERE kid=$warVillageId");
            
            // --- Send Retaliation Message ---
            if (class_exists('Core\NpcMessagingSystem')) {
                // Get target user ID
                $targetUserId = (int)$db->fetchScalar("SELECT owner FROM vdata WHERE kid=$targetId");
                $targetAccess = (int)$db->fetchScalar("SELECT access FROM users WHERE id=$targetUserId");
                
                // Only message real players and avoid spam (maybe check existing messages?)
                // For now, always send on attack
                if ($targetAccess < 3) {
                     $msg = NpcMessagingSystem::getRetaliationMessage($personality);
                     $mModel = new \Model\MessageModel();
                     $mModel->sendMessage($npcUserId, $targetUserId, 'Declaration of War', $msg, 0);
                }
            }
        }
    }
}
