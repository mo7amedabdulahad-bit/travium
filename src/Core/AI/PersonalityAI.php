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
            // Fallback to random if not an NPC
            return self::selectRandom($buildings, $creationTime);
        }

        $personality = $config['npc_personality'];
        $personalityStats = $config['personality_stats'];

        // Early game: focus on resource fields
        if ((time() - $creationTime) < 5400) { // First 1.5 hours
            return mt_rand(1, 18); // Always resource fields early
        }

        // Determine if should build resource field or building
        $militaryFocus = $personalityStats['military_focus'];
        $economyFocus = $personalityStats['economy_focus'];

        // Roll to decide: resource field, military building, or economy building
        $roll = mt_rand(1, 100);

        if ($roll <= $economyFocus) {
            // Economy-focused: prioritize resource fields or economy buildings
            if (mt_rand(1, 100) <= 60) {
                return mt_rand(1, 18); // Resource field
            } else {
                return self::selectEconomyBuilding($buildings, $personality);
            }
        } elseif ($roll <= ($economyFocus + $militaryFocus)) {
            // Military-focused: select military building
            return self::selectMilitaryBuilding($buildings, $personality);
        } else {
            // Balanced/other: select from personality preferences
            return self::selectPreferredBuilding($buildings, $personality, $personalityStats);
        }
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
