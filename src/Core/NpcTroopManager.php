<?php

namespace Core;

use Core\Database\DB;
use Game\Formulas;

class NpcTroopManager
{
    public static function executeTroopProduction($kid, $template, $budgetMultiplier = 1.0)
    {
        $db = DB::getInstance();

        // Check if training queue is full
        // Check 'training' table
        $trainingCount = $db->fetchScalar("SELECT COUNT(*) FROM training WHERE kid=$kid");
        if ($trainingCount >= 2) return;

        // Get Resources
        $resources = $db->query("SELECT wood, clay, iron, crop FROM vdata WHERE kid=$kid")->fetch_assoc();
        
        // Get Unit mapping (u1..u10 based on tribe)
        $owner = $db->fetchScalar("SELECT owner FROM vdata WHERE kid=$kid");
        $user = $db->query("SELECT race FROM users WHERE id=$owner")->fetch_assoc();
        $tribe = $user['race'];

        foreach ($template as $unitName => $targetPercent) {
            // Map unit name to ID (u1..u50)
            $unitId = self::getUnitId($tribe, $unitName);
            if (!$unitId) continue;

            $unitIndex = ($unitId - 1) % 10 + 1; // 1-10 within tribe

            $cost = Formulas::uCost($unitId);
            
            // Calculate max affordable
            $maxWood = floor(($resources['wood'] * $budgetMultiplier) / $cost[0]);
            $maxClay = floor(($resources['clay'] * $budgetMultiplier) / $cost[1]);
            $maxIron = floor(($resources['iron'] * $budgetMultiplier) / $cost[2]);
            $maxCrop = floor(($resources['crop'] * $budgetMultiplier) / $cost[3]);
            
            $maxTrain = min($maxWood, $maxClay, $maxIron, $maxCrop);
            $maxTrain = min($maxTrain, 5); // Cap batch size for now
            
            if ($maxTrain > 0) {
                // Determine building type (Barracks=19, Stable=20, Workshop=21)
                $buildingType = self::getTrainingBuilding($unitIndex);
                // Assume building exists
                
                $totalWood = $maxTrain * $cost[0];
                $totalClay = $maxTrain * $cost[1];
                $totalIron = $maxTrain * $cost[2];
                $totalCrop = $maxTrain * $cost[3];

                // Deduct
                $db->query("UPDATE vdata SET wood=wood-$totalWood, clay=clay-$totalClay, iron=iron-$totalIron, crop=crop-$totalCrop WHERE kid=$kid");

                // Insert into training
                $commence = time(); // Simplified, should check end of last training
                $top = $commence + ($maxTrain * Formulas::uTime($unitId)); // Simplified duration
                
                $db->query("INSERT INTO training (kid, unit, amt, pop, commence, end, nr) 
                            VALUES ($kid, $unitId, $maxTrain, {$cost[3]}, $commence, $top, $unitIndex)");
                            
                return; // Trained one batch, enough for this tick
            }
        }
    }

    private static function getUnitId($tribe, $name)
    {
        // Simple mapper
        // Romans (1), Teutons (2), Gauls (3)
        // This needs a proper lookup table. 
        // For Proof of Concept, mapping some common names
        $map = [
            1 => ['Legionnaire' => 1, 'Praetorian' => 2, 'Imperian' => 3, 'Equites Legati' => 4, 'Equites Imperatoris' => 5, 'Equites Caesaris' => 6],
            2 => ['Clubswinger' => 11, 'Spearman' => 12, 'Axeman' => 13, 'Scout' => 14, 'Paladin' => 15, 'Teutonic Knight' => 16],
            3 => ['Phalanx' => 21, 'Swordsman' => 22, 'Pathfinder' => 23, 'Theutates Thunder' => 24, 'Druidrider' => 25, 'Haeduan' => 26]
        ];
        
        return $map[$tribe][$name] ?? null;
    }

    private static function getTrainingBuilding($unitIndex)
    {
        if ($unitIndex <= 3) return 19; // Barracks
        if ($unitIndex <= 6) return 20; // Stable
        return 21; // Workshop
    }
}
