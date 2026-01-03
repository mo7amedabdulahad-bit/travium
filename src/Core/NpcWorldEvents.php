<?php

namespace Core;

use Core\Database\DB;

class NpcWorldEvents
{
    /**
     * Record when an NPC's alliance is attacked
     * 
     * @param int $serverId Server/world ID
     * @param int $allianceId The alliance that was attacked
     * @param int $attackerId User ID of the attacker
     * @param int $targetVillageId Village that was attacked
     */
    public static function recordAllianceAttacked($serverId, $allianceId, $attackerId, $targetVillageId)
    {
        $db = DB::getInstance();
        
        $eventData = json_encode([
            'attacker_id' => $attackerId,
            'target_village_id' => $targetVillageId,
            'timestamp' => time()
        ]);
        
        $db->query("
            INSERT INTO npc_world_events 
            (server_id, event_type, target_alliance_id, attacker_id, target_village_id, event_data_json, created_at)
            VALUES ($serverId, 'AllianceAttacked', $allianceId, $attackerId, $targetVillageId, '$eventData', NOW())
        ");
    }
    
    /**
     * Record when WW plans are released (Phase 6 - stub)
     */
    public static function recordWWPlanReleased($serverId, $planVillageId)
    {
        $db = DB::getInstance();
        
        $eventData = json_encode([
            'plan_village_id' => $planVillageId,
            'timestamp' => time()
        ]);
        
        $db->query("
            INSERT INTO npc_world_events 
            (server_id, event_type, event_data_json, created_at)
            VALUES ($serverId, 'WWPlanReleased', '$eventData', NOW())
        ");
    }
    
    /**
     * Record when a WW village is under attack (Phase 6 - stub)
     */
    public static function recordWWUnderAttack($serverId, $wwVillageId, $attackerId)
    {
        $db = DB::getInstance();
        
        $eventData = json_encode([
            'ww_village_id' => $wwVillageId,
            'attacker_id' => $attackerId,
            'timestamp' => time()
        ]);
        
        $db->query("
            INSERT INTO npc_world_events 
            (server_id, event_type, target_village_id, attacker_id, event_data_json, created_at)
            VALUES ($serverId, 'WWUnderAttack', $wwVillageId, $attackerId, '$eventData', NOW())
        ");
    }
    
    /**
     * Record when an NPC is defeated (village lost/captured)
     */
    public static function recordNpcDefeated($serverId, $npcId, $defeatType)
    {
        $db = DB::getInstance();
        
        $eventData = json_encode([
            'npc_id' => $npcId,
            'defeat_type' => $defeatType, // 'village_lost', 'chief_captured'
            'timestamp' => time()
        ]);
        
        $db->query("
            INSERT INTO npc_world_events 
            (server_id, event_type, event_data_json, created_at)
            VALUES ($serverId, 'NpcDefeated', '$eventData', NOW())
        ");
    }
}
