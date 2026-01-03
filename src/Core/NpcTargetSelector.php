<?php

namespace Core;

use Core\Database\DB;

class NpcTargetSelector
{
    /**
     * Select an attack target for a war village
     * 
     * @param int $warVillageId The attacking village ID
     * @param array $template Personality template (unused for now, aggressive to all)
     * @param array $policy Difficulty policy (unused for now)
     * @return int|null Target village ID or null if none found
     */
    public static function selectTarget($warVillageId, $template, $policy)
    {
        $db = DB::getInstance();
        
        // Get war village alliance ID
        $warVillageData = $db->query("SELECT owner FROM vdata WHERE kid=$warVillageId")->fetch_assoc();
        if (!$warVillageData) return null;
        
        $npcUserId = (int)$warVillageData['owner'];
        $npcAllianceId = (int)$db->fetchScalar("SELECT aid FROM users WHERE id=$npcUserId");
        
        // Get war village coordinates
        $coords = $db->query("SELECT x, y FROM wdata WHERE id=$warVillageId")->fetch_assoc();
        if (!$coords) return null;
        
        $x = (int)$coords['x'];
        $y = (int)$coords['y'];
        
        // Define max attack range (50 tiles in any direction - game default)
        $maxRange = 50;
        
        // Query all villages and oasis within range
        // Exclude: Own villages, own alliance, NAP partners
        $query = "
            SELECT w.id AS kid, w.occupied, w.oasistype, v.owner
            FROM wdata w
            LEFT JOIN vdata v ON w.id = v.kid
            WHERE w.x BETWEEN " . ($x - $maxRange) . " AND " . ($x + $maxRange) . "
              AND w.y BETWEEN " . ($y - $maxRange) . " AND " . ($y + $maxRange) . "
              AND w.id != $warVillageId
              AND (w.occupied = 1 OR w.oasistype > 0)
        ";
        
        $result = $db->query($query);
        $validTargets = [];
        
        while ($row = $result->fetch_assoc()) {
            $targetKid = (int)$row['kid'];
            $isOasis = (int)$row['oasistype'] > 0;
            
            if ($isOasis) {
                // Oasis are always valid targets
                $validTargets[] = $targetKid;
                continue;
            }
            
            // Village - check owner's alliance
            $ownerId = (int)$row['owner'];
            if ($ownerId == $npcUserId) continue; // Skip own villages
            
            $targetAllianceId = (int)$db->fetchScalar("SELECT aid FROM users WHERE id=$ownerId");
            
            // Skip if same alliance
            if ($npcAllianceId > 0 && $targetAllianceId == $npcAllianceId) continue;
            
            // Skip if NAP partner
            if ($npcAllianceId > 0 && $targetAllianceId > 0) {
                $napExists = $db->fetchScalar("
                    SELECT COUNT(id) FROM diplomacy 
                    WHERE accepted=1 AND type=2 
                      AND ((aid1=$npcAllianceId AND aid2=$targetAllianceId) 
                        OR (aid1=$targetAllianceId AND aid2=$npcAllianceId))
                ");
                if ($napExists > 0) continue; // Skip NAP partners
            }
            
            $validTargets[] = $targetKid;
        }
        
        // Return random target from valid list
        if (empty($validTargets)) return null;
        return $validTargets[array_rand($validTargets)];
    }
}
