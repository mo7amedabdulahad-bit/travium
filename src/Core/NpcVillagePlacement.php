<?php

namespace Core;

use Core\Database\DB;
use function logError;

/**
 * NPC Village Placement System
 * Strategic location selection for village expansion
 */
class NpcVillagePlacement
{
    /**
     * Select expansion site for new village
     * 
     * @param array $npcRow NPC user row
     * @param int $villageNumber Village sequence number (2, 3, or 4)
     * @return array|null Coordinates and role ['x', 'y', 'role', 'village_number']
     */
    public static function selectExpansionSite($npcRow, $villageNumber)
    {
        $db = DB::getInstance();
        
        // Determine purpose based on village number
        $purpose = self::determinePurpose($villageNumber, $npcRow);
        
        // Get alliance quadrant
        $quadrant = self::getAllianceQuadrant($npcRow['aid']);
        
        // Search for suitable sites
        $candidates = self::findCandidateSites($quadrant, $purpose, $npcRow);
        
        if (empty($candidates)) {
            logError("NPC {$npcRow['id']}: No candidate sites found for $purpose");
            return null;
        }
        
        // Pick best candidate (weighted random from top 10)
        $topCandidates = array_slice($candidates, 0, min(10, count($candidates)));
        $selected = $topCandidates[array_rand($topCandidates)];
        
        return [
            'x' => $selected['x'],
            'y' => $selected['y'],
            'role' => $selected['role'],
            'village_number' => $villageNumber
        ];
    }
    
    /**
     * Determine village purpose based on number and personality
     */
    private static function determinePurpose($villageNumber, $npcRow)
    {
        $personality = $npcRow['npc_personality'] ?? 'Balanced';
        
        switch ($villageNumber) {
            case 2:
                // Second village often becomes war/frontier village
                return in_array($personality, ['Aggressive', 'Raider', 'Assassin']) 
                    ? 'Frontier' 
                    : 'Support';
                
            case 3:
                // Third village is resource-focused
                return 'Resource';
                
            case 4:
                // Fourth village is defensive outpost
                return 'Defensive';
                
            default:
                return 'Support';
        }
    }
    
    /**
     * Get alliance quadrant bounds
     */
    private static function getAllianceQuadrant($allianceId)
    {
        $db = DB::getInstance();
        
        // Get average position of alliance villages
        $result = $db->query("
            SELECT AVG(w.x) as avg_x, AVG(w.y) as avg_y
            FROM vdata v
            JOIN wdata w ON w.id = v.kid
            JOIN users u ON v.owner = u.id
            WHERE u.aid = $allianceId
        ")->fetch_assoc();
        
        if (!$result) {
            return ['min_x' => -50, 'max_x' => 50, 'min_y' => -50, 'max_y' => 50];
        }
        
        $centerX = (int)$result['avg_x'];
        $centerY = (int)$result['avg_y'];
        
        // Define quadrant as 50-tile radius around alliance center
        return [
            'min_x' => $centerX - 50,
            'max_x' => $centerX + 50,
            'min_y' => $centerY - 50,
            'max_y' => $centerY + 50
        ];
    }
    
    /**
     * Find candidate settlement sites
     */
    private static function findCandidateSites($quadrant, $purpose, $npcRow)
    {
        $db = DB::getInstance();
        
        $candidates = [];
        
        switch ($purpose) {
            case 'Frontier':
                // Close to map center or enemy territory
                $candidates = self::findFrontierSites($quadrant);
                break;
                
            case 'Resource':
                // Near oases or high crop bonus
                $candidates = self::findResourceSites($quadrant);
                break;
                
            case 'Defensive':
                // Near quadrant border
                $candidates = self::findDefensiveSites($quadrant);
                break;
                
            case 'Support':
            default:
                // General good locations
                $candidates = self::findSupportSites($quadrant);
                break;
        }
        
        return $candidates;
    }
    
    /**
     * Find frontier sites (close to center)
     */
    private static function findFrontierSites($quadrant)
    {
        $db = DB::getInstance();
        
        $result = $db->query("
            SELECT id, x, y,
                   SQRT(POW(x, 2) + POW(y, 2)) as distance_to_center
            FROM wdata
            WHERE occupied = 0
              AND oasistype = 0
              AND x BETWEEN {$quadrant['min_x']} AND {$quadrant['max_x']}
              AND y BETWEEN {$quadrant['min_y']} AND {$quadrant['max_y']}
              AND SQRT(POW(x, 2) + POW(y, 2)) < 20
            ORDER BY distance_to_center ASC
            LIMIT 30
        ");
        
        $sites = [];
        while ($row = $result->fetch_assoc()) {
            $sites[] = [
                'x' => (int)$row['x'],
                'y' => (int)$row['y'],
                'role' => 'Frontier',
                'score' => 100 - (int)$row['distance_to_center']
            ];
        }
        
        return $sites;
    }
    
    /**
     * Find resource sites (near oases)
     */
    private static function findResourceSites($quadrant)
    {
        $db = DB::getInstance();
        
        // Look for tiles near oases
        $result = $db->query("
            SELECT w.id, w.x, w.y, w.fieldtype
            FROM wdata w
            WHERE w.occupied = 0
              AND w.oasistype = 0
              AND w.x BETWEEN {$quadrant['min_x']} AND {$quadrant['max_x']}
              AND w.y BETWEEN {$quadrant['min_y']} AND {$quadrant['max_y']}
              AND w.fieldtype IN (1, 2, 3, 4, 11, 12, 13)
            ORDER BY w.fieldtype DESC
            LIMIT 30
        ");
        
        $sites = [];
        while ($row = $result->fetch_assoc()) {
            $sites[] = [
                'x' => (int)$row['x'],
                'y' => (int)$row['y'],
                'role' => 'Resource',
                'score' => 80 + (int)$row['fieldtype']
            ];
        }
        
        return $sites;
    }
    
    /**
     * Find defensive sites (near borders)
     */
    private static function findDefensiveSites($quadrant)
    {
        $db = DB::getInstance();
        
        // Sites near quadrant edge
        $result = $db->query("
            SELECT id, x, y
            FROM wdata
            WHERE occupied = 0
              AND oasistype = 0
              AND (
                  (x BETWEEN {$quadrant['min_x']} AND {$quadrant['min_x']} + 10) OR
                  (x BETWEEN {$quadrant['max_x']} - 10 AND {$quadrant['max_x']}) OR
                  (y BETWEEN {$quadrant['min_y']} AND {$quadrant['min_y']} + 10) OR
                  (y BETWEEN {$quadrant['max_y']} - 10 AND {$quadrant['max_y']})
              )
            LIMIT 30
        ");
        
        $sites = [];
        while ($row = $result->fetch_assoc()) {
            $sites[] = [
                'x' => (int)$row['x'],
                'y' => (int)$row['y'],
                'role' => 'Defensive',
                'score' => 70
            ];
        }
        
        return $sites;
    }
    
    /**
     * Find general support sites
     */
    private static function findSupportSites($quadrant)
    {
        $db = DB::getInstance();
        
        $result = $db->query("
            SELECT id, x, y, fieldtype
            FROM wdata
            WHERE occupied = 0
              AND oasistype = 0
              AND x BETWEEN {$quadrant['min_x']} AND {$quadrant['max_x']}
              AND y BETWEEN {$quadrant['min_y']} AND {$quadrant['max_y']}
            ORDER BY fieldtype DESC
            LIMIT 30
        ");
        
        $sites = [];
        while ($row = $result->fetch_assoc()) {
            $sites[] = [
                'x' => (int)$row['x'],
                'y' => (int)$row['y'],
                'role' => 'Support',
                'score' => 60
            ];
        }
        
        return $sites;
    }
}
