<?php

namespace Core;

use Core\Database\DB;
use Game\Buildings\BuildingAction;
use Game\Formulas;
use Model\VillageModel;

class NpcBuildingManager
{
    public static function executeBuilds($kid, $priorities, $budgetMultiplier = 1.0)
    {
        $db = DB::getInstance();

        // Check if building queue is full
        // Using existing logic or simple count
        $queueCount = $db->fetchScalar("SELECT COUNT(*) FROM building_upgrade WHERE kid=$kid");
        if ($queueCount >= 2) return; // Full (assuming active + loop)

        // Get Resources
        $resources = $db->query("SELECT wood, clay, iron, crop, maxstore, maxcrop FROM vdata WHERE kid=$kid")->fetch_assoc();
        
        // Simple logic: iterate priorities, find first valid one
        foreach ($priorities as $buildingName) {
            // Convert name to ID (needs mapping or helper)
            // For now, let's assume specific IDs or types. 
            // Phase 0 template uses names like "Barracks". We need a mapper.
            $bType = self::getBuildingTypeByName($buildingName);
            if (!$bType) continue;

            // Check if we need to build it (not already maxed or present if unique)
            // This requires checking 'fdata'
            // Simplified: check if we can upgrade an existing one or build new
            
            // Find a spot
            $location = self::findBuildingLocation($kid, $bType);
            if (!$location) continue; // No spot or maxed

            // Check cost
            $cost = Formulas::buildingCost($bType, $location['level'] + 1);
            
            // Apply budget guard
            if (
                $resources['wood'] < ($cost[0] * $budgetMultiplier) ||
                $resources['clay'] < ($cost[1] * $budgetMultiplier) ||
                $resources['iron'] < ($cost[2] * $budgetMultiplier) ||
                $resources['crop'] < ($cost[3] * $budgetMultiplier)
            ) {
                continue; // Too expensive
            }

            // Build it using Game logic
            // BuildingAction::upgrade($kid, $field); 
            // But BuildingAction usually expects user session...
            // We might need to insert into `building_upgrade` directly or use `MasterBuilder`.
            
            // Using direct Model\MasterBuilder approach is safer for automation
            // Or just mimicking what RegisterModel does for initial buildings
            
            // For robust integration, let's assume we insert into `building_upgrade`
            // and let `Automation::buildComplete` handle the actual processing tick.
            
            $commence = time();
            $duration = Formulas::buildingTime($bType, $location['level'] + 1); 
            $loop = 0; // Not master, direct
            
            // Deduct resources
            $db->query("UPDATE vdata SET wood=wood-{$cost[0]}, clay=clay-{$cost[1]}, iron=iron-{$cost[2]}, crop=crop-{$cost[3]} WHERE kid=$kid");
            
            // Insert queue
            $db->query("INSERT INTO building_upgrade (kid, building_field, type, loop_upgrade, commence, duration, isMaster) 
                        VALUES ($kid, {$location['field']}, $bType, $loop, $commence, $duration, 0)");
            
            return; // Action taken, done for this tick
        }
    }

    private static function getBuildingTypeByName($name)
    {
        // Simple mapper
        $map = [
            'Woodcutter' => 1, 'Clay Pit' => 2, 'Iron Mine' => 3, 'Cropland' => 4,
            'Sawmill' => 5, 'Brickyard' => 6, 'Iron Foundry' => 7, 'Grain Mill' => 8, 'Bakery' => 9,
            'Warehouse' => 10, 'Granary' => 11, 'Blacksmith' => 12, 'Armoury' => 13, 
            'Tournament Square' => 14, 'Main Building' => 15, 'Rally Point' => 16, 
            'Marketplace' => 17, 'Embassy' => 18, 'Barracks' => 19, 'Stable' => 20, 
            'Workshop' => 21, 'Academy' => 22, 'Cranny' => 23, 'Town Hall' => 24, 
            'Residence' => 25, 'Palace' => 26, 'Treasury' => 27, 'Trade Office' => 28, 
            'Great Barracks' => 29, 'Great Stable' => 30, 'City Wall' => 31, 'Earth Wall' => 32, 'Palisade' => 33, 
            'Stonemason' => 34, 'Brewery' => 35, 'Trapper' => 36, 'Hero\'s Mansion' => 37, 
            'Great Warehouse' => 38, 'Great Granary' => 39, 'Wonder of the World' => 40, 'Horse Drinking Trough' => 41, 
            'Stone Wall' => 42, 'Makeshift Wall' => 43
        ];
        return $map[$name] ?? null;
    }

    private static function findBuildingLocation($kid, $type)
    {
        $db = DB::getInstance();
        $fdata = $db->query("SELECT * FROM fdata WHERE kid=$kid")->fetch_assoc();
        
        // 1. Resources (1-18)
        if ($type <= 4) {
             // Find lowest level resource field of this type
             $minLvl = 999;
             $targetField = null;
             for ($i = 1; $i <= 18; $i++) {
                  $fType = $fdata['f' . $i . 't'];
                  if ($fType == $type) {
                      $lvl = $fdata['f' . $i];
                      if ($lvl < $minLvl && $lvl < 10) { // Cap at 10 for simplicity
                          $minLvl = $lvl;
                          $targetField = $i;
                      }
                  }
             }
             if ($targetField) return ['field' => $targetField, 'level' => $minLvl];
        } else {
            // 2. Buildings (19-40)
            // Check if already exists
            for ($i = 19; $i <= 40; $i++) {
                if ($fdata['f' . $i . 't'] == $type) {
                    $lvl = $fdata['f' . $i];
                    if ($lvl < 20) return ['field' => $i, 'level' => $lvl];
                    // Multi-build check (Warehouse/Granary) could go here
                }
            }
            
            // Build new
            for ($i = 19; $i <= 40; $i++) {
                if ($fdata['f' . $i . 't'] == 0) {
                    return ['field' => $i, 'level' => 0];
                }
            }
        }
        
        return null;
    }
}
