<?php

namespace Core;

use Core\Database\DB;

class NpcPassiveVillage
{
    public static function doPassiveAction($kid)
    {
        $db = DB::getInstance();
        
        // Very cheap logic: Just ensure resources don't overflow
        // If > 90% full, delete 10% (burn/consume simulation) or trade if we had Alliance logic
        
        $res = $db->query("SELECT wood, clay, iron, crop, maxstore, maxcrop FROM vdata WHERE kid=$kid")->fetch_assoc();
        
        $overflow = false;
        foreach (['wood', 'clay', 'iron'] as $r) {
            if ($res[$r] > ($res['maxstore'] * 0.9)) {
                $db->query("UPDATE vdata SET $r = $r * 0.9 WHERE kid=$kid");
                $overflow = true;
            }
        }
        if ($res['crop'] > ($res['maxcrop'] * 0.9)) {
             $db->query("UPDATE vdata SET crop = crop * 0.9 WHERE kid=$kid");
        }
        
        // Random chance to upgrade Cranny if valid
        if (mt_rand(1, 100) <= 5) {
            // NpcBuildingManager::executeBuilds($kid, ['Cranny']);
        }
    }
}
