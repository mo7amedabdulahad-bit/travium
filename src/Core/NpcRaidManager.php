<?php

namespace Core;

use Core\Database\DB;
use Game\Formulas;
use Game\SpeedCalculator;
use Model\MovementsModel;

class NpcRaidManager
{
    /**
     * Execute a raid attack from war village
     * 
     * @param int $warVillageId The attacking village ID
     * @param int $targetId The target village/oasis ID
     * @param array $template Personality template
     * @param array $policy Difficulty policy
     */
    public static function executeRaid($warVillageId, $targetId, $template, $policy)
    {
        $db = DB::getInstance();
        
        // Get village owner/race
        $villageData = $db->query("SELECT owner FROM vdata WHERE kid=$warVillageId")->fetch_assoc();
        if (!$villageData) return;
        
        $npcUserId = (int)$villageData['owner'];
        $npcRace = (int)$db->fetchScalar("SELECT tribe FROM users WHERE id=$npcUserId");
        
        // Get available troopsconst from units table
        $units = $db->query("SELECT u1,u2,u3,u4,u5,u6,u7,u8,u9,u10,u11 FROM units WHERE kid=$warVillageId")->fetch_assoc();
        if (!$units) return;
        
        // Simple logic: Send 50% of available troops (aggressive but not suicidal)
        $troopsToSend = [];
        for ($i = 1; $i <= 10; $i++) {
            $available = (int)$units['u' . $i];
            $troopsToSend[$i] = ($available > 0) ? max(1, floor($available * 0.5)) : 0;
        }
        $troopsToSend[11] = 0; // Don't send hero
        
        // Check if we have any troops
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
        
        $startTime = time();
        $endTime = $startTime + $travelTime;
        
        // Create movement using existing game model
        $movementModel = new MovementsModel();
        $movementModel->addMovement(
            $warVillageId,           // from kid
            $targetId,                // to kid
            $npcRace,                 // race
            $troopsToSend,            // units array
            0,                        // ctar1 (catapult target 1)
            0,                        // ctar2 (catapult target 2)
            0,                        // spyType
            false,                    // redeployHero
            0,                        // mode (0 = going)
            MovementsModel::ATTACKTYPE_RAID,  // attack_type (4 = raid)
            $startTime,               // start_time
            $endTime,                 // end_time
            null                      // data
        );
        
        // Deduct troops from units table
        $updateParts = [];
        for ($i = 1; $i <= 10; $i++) {
            if ($troopsToSend[$i] > 0) {
                $updateParts[] = "u$i = u$i - " . $troopsToSend[$i];
            }
        }
        
        if (!empty($updateParts)) {
            $db->query("UPDATE units SET " . implode(', ', $updateParts) . " WHERE kid=$warVillageId");
        }
    }
}
