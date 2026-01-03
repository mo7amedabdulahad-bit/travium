<?php

namespace Core;

use Core\Database\DB;

class NpcWarVillageManager
{
    /**
     * Updates the designated War Village for an NPC.
     * Strategies:
     * 1. Frontier: Closest to map center or target area (if implemented)
     * 2. Headquarters: The capital
     * 3. Newest: Most recently settled
     * 
     * @param int $npcUid
     * @return int|null The selected village ID
     */
    public static function updateWarVillage($npcUid)
    {
        $db = DB::getInstance();
        $npcUid = (int)$npcUid;

        // Fetch all villages with metadata
        $sql = "SELECT v.kid, v.capital, v.created, nv.village_role 
                FROM vdata v 
                LEFT JOIN npc_villages nv ON v.kid = nv.village_id
                WHERE v.owner = $npcUid 
                ORDER BY v.created DESC";
        
        $result = $db->query($sql);
        $villages = [];
        while ($row = $result->fetch_assoc()) {
            $villages[] = $row;
        }

        if (empty($villages)) {
            return null;
        }

        $selectedKid = null;

        // Strategy 1: Look for explicit 'Frontier' or 'War' role
        foreach ($villages as $v) {
            if ($v['village_role'] === 'Frontier' || $v['village_role'] === 'War') {
                $selectedKid = $v['kid'];
                break;
            }
        }

        // Strategy 2: Fallback to Capital (Headquarters)
        if (!$selectedKid) {
            foreach ($villages as $v) {
                if ($v['capital'] == 1) {
                    $selectedKid = $v['kid'];
                    break;
                }
            }
        }

        // Strategy 3: Fallback to newest village
        if (!$selectedKid) {
            $selectedKid = $villages[0]['kid'];
        }

        // Update User State
        if ($selectedKid) {
            $db->query("UPDATE users SET war_village_id = $selectedKid WHERE id = $npcUid");
        }

        return $selectedKid;
    }
}
