<?php

namespace Core;

use Core\Database\DB;
use function logError;

/**
 * NPC Village Expansion Manager
 * Handles village founding, naming, and accelerated development
 */
class NpcExpansionManager
{
    /**
     * Check if NPC is eligible for village expansion
     * 
     * @param array $npcRow NPC user row
     * @return bool True if eligible
     */
    public static function checkExpansionEligibility($npcRow)
    {
        $db = DB::getInstance();
        
        // 1. Check game phase (must be mid-game or later, >48 hours)
        $ageHours = (time() - \Core\Config::getInstance()->game->start_time) / 3600;
        if ($ageHours < 48) {
            return false; // Too early
        }
        
        // 2. Check current village count (cap at 4)
        $villageCount = (int)$db->fetchScalar("
            SELECT COUNT(*) FROM vdata WHERE owner = {$npcRow['id']}
        ");
        
        if ($villageCount >= 4) {
            return false; // Already at maximum
        }
        
        // 3. Check if expansion is in progress
        $expansionPlan = json_decode($npcRow['expansion_plan_json'] ?? '{}', true);
        if (!empty($expansionPlan['in_progress'])) {
            return false; // Already expanding
        }
        
        // 4. Get capital village
        $capital = $db->query("
            SELECT kid FROM vdata 
            WHERE owner = {$npcRow['id']} AND capital = 1
            LIMIT 1
        ")->fetch_assoc();
        
        if (!$capital) return false;
        
        $capitalId = $capital['kid'];
        
        // 5. Check Residence/Palace level (need level 10+)
        // fdata uses fields f1-f99 where field number = building slot
        // Residence = type 25, Palace = type 26
        $residenceLevel = (int)$db->fetchScalar("
            SELECT MAX(f.f25) as residence_level FROM fdata f
            WHERE f.kid = $capitalId
        ");
        
        $palaceLevel = (int)$db->fetchScalar("
            SELECT MAX(f.f26) as palace_level FROM fdata f
            WHERE f.kid = $capitalId
        ");
        
        $maxLevel = max($residenceLevel, $palaceLevel);
        
        if ($maxLevel < 10) {
            return false; // Residence/Palace not high enough
        }
        
        // 6. Check settlers (need 3 settlers trained)
        $settlers = self::getSettlerCount($capitalId);
        if ($settlers < 3) {
            return false; // Not enough settlers
        }
        
        // 7. Check resources (need 750 of each)
        $resources = $db->query("
            SELECT wood, clay, iron, crop 
            FROM vdata WHERE kid = $capitalId
        ")->fetch_assoc();
        
        if (!$resources || 
            $resources['wood'] < 750 || 
            $resources['clay'] < 750 || 
            $resources['iron'] < 750 || 
            $resources['crop'] < 750) {
            return false; // Insufficient resources
        }
        
        // 8. Personality factor (some personalities expand earlier)
        $personality = $npcRow['npc_personality'] ?? 'Balanced';
        $earlyExpanders = ['Guardian', 'Supplier', 'Economic'];
        $lateExpanders = ['Raider', 'Assassin', 'Aggressive'];
        
        if (in_array($personality, $lateExpanders) && $ageHours < 96) {
            // Military-focused NPCs wait longer (4 days)
            return false;
        }
        
        return true;
    }
    
    /**
     * Get settler count (unit type 9 or 10 depending on tribe)
     */
    private static function getSettlerCount($villageId)
    {
        $db = DB::getInstance();
        
        $units = $db->query("SELECT u9, u10 FROM units WHERE kid = $villageId")->fetch_assoc();
        
        if (!$units) return 0;
        
        return max((int)($units['u9'] ?? 0), (int)($units['u10'] ?? 0));
    }
    
    /**
     * Plan and execute village expansion
     * 
     * @param array $npcRow NPC user row
     */
    public static function planExpansion($npcRow)
    {
        $db = DB::getInstance();
        
        // Determine next village number
        $villageCount = (int)$db->fetchScalar("
            SELECT COUNT(*) FROM vdata WHERE owner = {$npcRow['id']}
        ");
        
        $nextVillageNumber = $villageCount + 1;
        
        // Select expansion site
        $coords = NpcVillagePlacement::selectExpansionSite($npcRow, $nextVillageNumber);
        
        if (!$coords) {
            logError("NPC {$npcRow['id']}: No suitable expansion site found");
            return;
        }
        
        // Create expansion plan
        $plan = [
            'in_progress' => true,
            'village_number' => $nextVillageNumber,
            'target_x' => $coords['x'],
            'target_y' => $coords['y'],
            'role' => $coords['role'],
            'started_at' => time()
        ];
        
        $db->query("
            UPDATE users 
            SET expansion_plan_json = '" . $db->real_escape_string(json_encode($plan)) . "'
            WHERE id = {$npcRow['id']}
        ");
        
        // Execute founding
        self::foundNewVillage($npcRow, $coords);
    }
    
    /**
     * Found new village at coordinates
     * 
     * @param array $npcRow NPC user row
     * @param array $coords Coordinates and role info
     */
    public static function foundNewVillage($npcRow, $coords)
    {
        $db = DB::getInstance();
        
        // Get capital village
        $capital = $db->query("
            SELECT kid FROM vdata 
            WHERE owner = {$npcRow['id']} AND capital = 1
        ")->fetch_assoc();
        
        if (!$capital) {
            logError("NPC {$npcRow['id']}: No capital found for expansion");
            return;
        }
        
        $capitalId = $capital['kid'];
        
        // Train settlers if not available (this would normally be done gradually)
        // For simulation, assume settlers are trained
        
        // Send settlers to target location
        $targetTileId = self::getTileId($coords['x'], $coords['y']);
        
        if (!$targetTileId) {
            logError("NPC {$npcRow['id']}: Invalid expansion coordinates ({$coords['x']}, {$coords['y']})");
            return;
        }
        
        // Queue settler movement (attack_type = 7 for settling)
        $distance = self::calculateDistance($capitalId, $targetTileId);
        $travelTime = ceil($distance / 5) * 3600; // 5 tiles/hour
        
        $startTime = time() * 1000;
        $endTime = $startTime + ($travelTime * 1000);
        
        $db->query("
            INSERT INTO movement (
                `from`, `to`, attack_type, troops, start_time, end_time
            ) VALUES (
                $capitalId, $targetTileId, 7, '0,0,0,0,0,0,0,0,3,0', $startTime, $endTime
            )
        ");
        
        // On arrival, village will be founded (handled by game engine)
        // After founding, call postFoundingSetup()
        
        logError("NPC {$npcRow['id']}: Settlers sent to ({$coords['x']}, {$coords['y']}) for village #{$coords['village_number']}");
    }
    
    /**
     * Post-founding setup - naming and role assignment
     * Call this after village is actually created
     * 
     * @param int $npcId NPC user ID
     * @param int $newVillageId Newly founded village ID
     */
    public static function postFoundingSetup($npcId, $newVillageId)
    {
        $db = DB::getInstance();
        
        // Get expansion plan
        $npc = $db->query("SELECT expansion_plan_json FROM users WHERE id = $npcId")->fetch_assoc();
        $plan = json_decode($npc['expansion_plan_json'] ?? '{}', true);
        
        $villageNumber = $plan['village_number'] ?? 2;
        $role = $plan['role'] ?? 'Support';
        
        // Name village
        $villageName = NpcNameService::getNextVillageName($npcId);
        
        $db->query("
            UPDATE vdata 
            SET name = '" . $db->real_escape_string($villageName) . "'
            WHERE kid = $newVillageId
        ");
        
        // Register in npc_villages table
        $db->query("
            INSERT INTO npc_villages (
                village_id, npc_player_id, village_role, village_number, founded_at
            ) VALUES (
                $newVillageId, $npcId, '$role', $villageNumber, NOW()
            )
        ");
        
        // If village 2 and frontier role, set as war village
        if ($villageNumber == 2 && $role === 'Frontier') {
            $db->query("UPDATE users SET war_village_id = $newVillageId WHERE id = $npcId");
            logError("NPC $npcId: Village $newVillageId set as war village");
        }
        
        // Clear expansion plan
        $db->query("UPDATE users SET expansion_plan_json = NULL WHERE id = $npcId");
        
        logError("NPC $npcId: Founded village $newVillageId named '$villageName' (Role: $role)");
    }
    
    /**
     * Accelerated development for new villages
     * 
     * @param int $villageId Village ID
     * @param string $role Village role
     */
    public static function developNewVillage($villageId, $role)
    {
        $db = DB::getInstance();
        
        // Check age
        $founded = $db->fetchScalar("
            SELECT founded_at FROM npc_villages WHERE village_id = $villageId
        ");
        
        if (!$founded) return;
        
        $ageHours = (time() - strtotime($founded)) / 3600;
        
        if ($ageHours > 48) {
            return; // No longer new
        }
        
        // Accelerated build order (priority queue)
        $buildPriorities = [
            'Warehouse',    // Storage first
            'Granary',      // Food storage
            'Cropland',     // Resource field
            'Woodcutter',   // Resource field
            'Clay Pit',     // Resource field
            'Iron Mine'     // Resource field
        ];
        
        foreach ($buildPriorities as $building) {
            // Use higher build rate for new villages
            $ownerId = (int)$db->fetchScalar("SELECT owner FROM vdata WHERE kid = $villageId");
            if ($ownerId) {
                // Building is now handled by AI.php integration in NpcScriptEngine
            }
        }
    }
    
    /**
     * Get tile ID from coordinates
     */
    private static function getTileId($x, $y)
    {
        $db = DB::getInstance();
        return $db->fetchScalar("SELECT id FROM wdata WHERE x = $x AND y = $y");
    }
    
    /**
     * Calculate distance between village and tile
     */
    private static function calculateDistance($villageId, $tileId)
    {
        $db = DB::getInstance();
        
        // Get village coordinates
        $vCoords = $db->query("
            SELECT w.x, w.y 
            FROM wdata w 
            JOIN vdata v ON w.id = v.kid 
            WHERE v.kid = $villageId
        ")->fetch_assoc();
        
        $tCoords = $db->query("SELECT x, y FROM wdata WHERE id = $tileId")->fetch_assoc();
        
        if (!$vCoords || !$tCoords) return 0;
        
        return max(
            abs($vCoords['x'] - $tCoords['x']),
            abs($vCoords['y'] - $tCoords['y'])
        );
    }
}
