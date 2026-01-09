<?php

namespace Core\AI;

use Core\NpcConfig;
use Core\Database\DB;

/**
 * Personality-Driven Building AI
 * 
 * Modifies building selection based on NPC personality to create
 * more realistic and varied AI behavior.
 * 
 * @package Core\AI
 * @version 1.0
 * @date 2025-12-28
 */
class PersonalityAI
{
    /**
     * Building type categories for personality-based selection
     */
    const MILITARY_BUILDINGS = [
        19, // Barracks
        20, // Stable
        21, // Workshop
        13, // Smithy
        22, // Academy
        41, // Horse Drinking Trough
        14, // Tournament Square
    ];

    const ECONOMY_BUILDINGS = [
        10, // Warehouse
        11, // Granary
        38, // Great Warehouse
        39, // Great Granary
        5,  // Sawmill
        6,  // Brickyard
        7,  // Iron Foundry
        8,  // Grain Mill
        9,  // Bakery
    ];

    const DIPLOMAT_BUILDINGS = [
        17, // Marketplace
        18, // Embassy
        23, // Residence
        26, // Palace
        28, // Main Building (important for diplomats)
    ];

    const ASSASSIN_BUILDINGS = [
        22, // Academy (for scouts/spies)
        13, // Smithy
        24, // Hideout (if exists)
    ];

    /**
     * Select a building or resource field based on NPC personality
     * 
     * @param int $uid User ID (must be NPC)
     * @param array $buildings Building array from AutoUpgradeAI
     * @param int $creationTime Village creation timestamp
     * @return int|false Field number to upgrade (1-40) or false
     */
    public static function selectBuildingByPersonality($uid, $buildings, $creationTime)
    {
        // Get NPC configuration
        $config = NpcConfig::getNpcConfig($uid);
        
        if (!$config || !isset($config['npc_personality'])) {
            return self::selectRandom($buildings, $creationTime);
        }

        $personality = $config['npc_personality'];
        $personalityStats = $config['personality_stats'];
        $age = time() - $creationTime;

        // 1. EARLY GAME: Focus strictly on resource fields
        if ($age < 5400) { // First 1.5 hours
            return mt_rand(1, 18); 
        }

        // 2. ESSENTIALS CHECK: Ensure core buildings exist after early game
        $essentialField = self::checkEssentialBuildings($buildings, $personality);
        if ($essentialField !== false) {
            return $essentialField;
        }

        // 3. PERSONALITY LOGIC
        // Aggressive: Military > Resource > Economy
        // Economic: Resource > Economy > Military
        
        $militaryFocus = $personalityStats['military_focus'];
        $economyFocus = $personalityStats['economy_focus'];
        
        // Smart correction: If resources are critically low (negative crop), prioritize crop fields
        // This would require resource data which we don't strictly have here, but we can assume
        // Granary/Warehouse importance in selectEconomyBuilding.

        $roll = mt_rand(1, 100);

        if ($personality === 'Aggressive' || $personality === 'Raider') {
            // Aggressive Logic: Prioritize Military, but don't neglect supply
            if ($roll <= 60) {
                return self::selectMilitaryBuilding($buildings, $personality);
            } elseif ($roll <= 80) {
                return mt_rand(1, 18); // Resources to fuel army
            } else {
                return self::selectEconomyBuilding($buildings, $personality); // Storage for army
            }
        } elseif ($personality === 'Economic' || $personality === 'Farmer') {
            // Economic Logic: Resources > Storage > Market
            if ($roll <= 70) {
                return mt_rand(1, 18);
            } elseif ($roll <= 90) {
                return self::selectEconomyBuilding($buildings, $personality);
            } else {
                return self::selectMilitaryBuilding($buildings, $personality); // Defense
            }
        } 
        
        // Fallback / Balanced Logic
        if ($roll <= $economyFocus) {
             if (mt_rand(1, 100) <= 60) {
                return mt_rand(1, 18);
            } else {
                return self::selectEconomyBuilding($buildings, $personality);
            }
        } elseif ($roll <= ($economyFocus + $militaryFocus)) {
            return self::selectMilitaryBuilding($buildings, $personality);
        } else {
            return self::selectPreferredBuilding($buildings, $personality, $personalityStats);
        }
    }

    /**
     * Check for essential buildings that every village needs
     * Rally Point, Warehouse, Granary, Barracks
     */
    private static function checkEssentialBuildings($buildings, $personality)
    {
        // Check Warehouse (GID 10)
        if (!self::hasBuilding($buildings, 10)) return self::findEmptySlot($buildings, 19, 38);
        
        // Check Granary (GID 11)
        if (!self::hasBuilding($buildings, 11)) return self::findEmptySlot($buildings, 19, 38);
        
        // Check Rally Point (GID 16) - usually slot 39
        if (!isset($buildings[39]) || $buildings[39]['item_id'] == 0) return 39;

        // Check Barracks (GID 19)
        if (!self::hasBuilding($buildings, 19)) return self::findEmptySlot($buildings, 19, 38);

        // Check Market (GID 17) - useful for all
        if (!self::hasBuilding($buildings, 17)) return self::findEmptySlot($buildings, 19, 38);

        return false;
    }

    private static function hasBuilding($buildings, $gid)
    {
        foreach ($buildings as $b) {
            if (isset($b['item_id']) && $b['item_id'] == $gid) return true;
        }
        return false;
    }

    private static function findEmptySlot($buildings, $min, $max)
    {
        for ($i = $min; $i <= $max; $i++) {
            if (isset($buildings[$i]) && $buildings[$i]['item_id'] == 0) return $i;
        }
        return mt_rand($min, $max); // Fallback
    }

    /**
     * Select a military building based on personality
     * 
     * @param array $buildings Building array
     * @param string $personality Personality type
     * @return int Field number
     */
    private static function selectMilitaryBuilding($buildings, $personality)
    {
        $preferred = [];

        // Get personality-specific preferred buildings
        $stats = NpcConfig::getPersonalityStats($personality);
        if (isset($stats['preferred_buildings']) && is_array($stats['preferred_buildings'])) {
            $preferredGids = $stats['preferred_buildings'];
            
            // Find fields with these buildings
            for ($i = 19; $i <= 38; $i++) {
                if (isset($buildings[$i]) && in_array($buildings[$i]['item_id'], $preferredGids)) {
                    $preferred[] = $i;
                }
            }
        }

        // If no preferred buildings exist, find empty slots for military buildings
        if (empty($preferred)) {
            for ($i = 19; $i <= 38; $i++) {
                if (isset($buildings[$i]) && $buildings[$i]['item_id'] == 0) {
                    // Empty slot - will trigger newBuilding() which will create military building
                    return $i;
                }
            }
        }

        // If we have preferred buildings, select one
        if (!empty($preferred)) {
            return $preferred[array_rand($preferred)];
        }

        // Fallback: any military building
        $militaryFields = [];
        for ($i = 19; $i <= 38; $i++) {
            if (isset($buildings[$i]) && in_array($buildings[$i]['item_id'], self::MILITARY_BUILDINGS)) {
                $militaryFields[] = $i;
            }
        }

        if (!empty($militaryFields)) {
            return $militaryFields[array_rand($militaryFields)];
        }

        // Ultimate fallback: random building slot
        return mt_rand(19, 38);
    }

    /**
     * Select an economy building based on personality
     * 
     * @param array $buildings Building array
     * @param string $personality Personality type
     * @return int Field number
     */
    private static function selectEconomyBuilding($buildings, $personality)
    {
        // Prioritize warehouse/granary if storage is low
        $economyFields = [];
        
        for ($i = 19; $i <= 38; $i++) {
            if (isset($buildings[$i]) && in_array($buildings[$i]['item_id'], self::ECONOMY_BUILDINGS)) {
                // Give higher priority to warehouse/granary
                if (in_array($buildings[$i]['item_id'], [10, 11, 38, 39])) {
                    $economyFields[] = $i;
                    $economyFields[] = $i; // Add twice for higher probability
                } else {
                    $economyFields[] = $i;
                }
            }
        }

        if (!empty($economyFields)) {
            return $economyFields[array_rand($economyFields)];
        }

        // No economy buildings yet - select resource field
        return mt_rand(1, 18);
    }

    /**
     * Select building from personality's preferred list
     * 
     * @param array $buildings Building array
     * @param string $personality Personality type
     * @param array $personalityStats Personality statistics
     * @return int Field number
     */
    private static function selectPreferredBuilding($buildings, $personality, $personalityStats)
    {
        // For diplomat personality, prioritize specific buildings
        if ($personality === 'diplomat') {
            $diplomatFields = [];
            for ($i = 19; $i <= 38; $i++) {
                if (isset($buildings[$i]) && in_array($buildings[$i]['item_id'], self::DIPLOMAT_BUILDINGS)) {
                    $diplomatFields[] = $i;
                }
            }
            
            if (!empty($diplomatFields)) {
                return $diplomatFields[array_rand($diplomatFields)];
            }
        }

        // For assassin personality, prioritize academy and smithy
       if ($personality === 'assassin') {
            $assassinFields = [];
            for ($i = 19; $i <= 38; $i++) {
                if (isset($buildings[$i]) && in_array($buildings[$i]['item_id'], self::ASSASSIN_BUILDINGS)) {
                    $assassinFields[] = $i;
                }
            }
            
            if (!empty($assassinFields)) {
                return $assassinFields[array_rand($assassinFields)];
            }
        }

        // Balanced or fallback: mix of resource fields and buildings
        if (mt_rand(1, 100) <= 50) {
            return mt_rand(1, 18); // Resource field
        } else {
            return mt_rand(19, 38); // Building
        }
    }

    /**
     * Fallback random selection (same as original behavior)
     * 
     * @param array $buildings Building array
     * @param int $creationTime Village creation timestamp
     * @return int Field number
     */
    private static function selectRandom($buildings, $creationTime)
    {
        if ((time() - $creationTime) < 5400) {
            return mt_rand(1, 18);
        } else {
            return mt_rand(1, 5) <= 3 ? mt_rand(1, 18) : mt_rand(19, 40);
        }
    }

    /**
     * Get building selection stats for debugging
     * 
     * @param int $uid User ID
     * @param array $buildings Building array
     * @return array Debug information
     */
    public static function getSelectionDebugInfo($uid, $buildings)
    {
        $config = NpcConfig::getNpcConfig($uid);
        
        if (!$config) {
            return ['error' => 'Not an NPC'];
        }

        $militaryCount = 0;
        $economyCount = 0;
        $diplomatCount = 0;

        for ($i = 19; $i <= 38; $i++) {
            if (!isset($buildings[$i]) || $buildings[$i]['item_id'] == 0) continue;
            
            if (in_array($buildings[$i]['item_id'], self::MILITARY_BUILDINGS)) {
                $militaryCount++;
            }
            if (in_array($buildings[$i]['item_id'], self::ECONOMY_BUILDINGS)) {
                $economyCount++;
            }
            if (in_array($buildings[$i]['item_id'], self::DIPLOMAT_BUILDINGS)) {
                $diplomatCount++;
            }
        }

        return [
            'personality' => $config['npc_personality'],
            'difficulty' => $config['npc_difficulty'],
            'military_focus' => $config['personality_stats']['military_focus'],
            'economy_focus' => $config['personality_stats']['economy_focus'],
            'current_buildings' => [
                'military' => $militaryCount,
                'economy' => $economyCount,
                'diplomat' => $diplomatCount,
                'total' => count(array_filter($buildings, function($b) { 
                    return isset($b['item_id']) && $b['item_id'] > 0; 
                })),
            ],
        ];
    }
}
