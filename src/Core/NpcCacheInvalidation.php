<?php

namespace Core;

use Core\Database\DB;
use function logError;

/**
 * NPC Cache Invalidation Hooks
 * Automatic cache clearing when game state changes
 */
class NpcCacheInvalidation
{
    /**
     * Called when a building completes construction/upgrade
     * 
     * @param int $villageId Village ID
     */
    public static function onBuildingComplete(int $villageId): void
    {
        $key = NpcCache::keyVillageBuildings($villageId);
        NpcCache::delete($key);
    }
    
    /**
     * Called when troop training completes
     * 
     * @param int $villageId Village ID
     */
    public static function onTroopTrainingComplete(int $villageId): void
    {
        $key = NpcCache::keyVillageTroops($villageId);
        NpcCache::delete($key);
    }
    
    /**
     * Called when an attack/reinforcement is sent
     * 
     * @param int $fromVillageId Source village ID
     */
    public static function onAttackSent(int $fromVillageId): void
    {
        $key = NpcCache::keyVillageTroops($fromVillageId);
        NpcCache::delete($key);
    }
    
    /**
     * Called when troops return from attack/reinforcement
     * 
     * @param int $toVillageId Destination village ID
     */
    public static function onTroopsReturn(int $toVillageId): void
    {
        $key = NpcCache::keyVillageTroops($toVillageId);
        NpcCache::delete($key);
    }
    
    /**
     * Called when a village is captured/conquered
     * 
     * @param int $villageId Village ID
     */
    public static function onVillageCaptured(int $villageId): void
    {
        // Delete all cached data for this village
        $pattern = "village:{$villageId}:*";
        NpcCache::deletePattern($pattern);
    }
    
    /**
     * Called when a player joins an alliance
     * 
     * @param int $userId User ID
     * @param int $allianceId Alliance ID
     */
    public static function onAllianceJoin(int $userId, int $allianceId): void
    {
        $key = NpcCache::keyAllianceMembers($allianceId);
        NpcCache::delete($key);
        
        // Also invalidate old alliance if player switched
        $db = DB::getInstance();
        $oldAllianceId = (int)$db->fetchScalar("
            SELECT aid FROM users WHERE id = $userId
        ");
        
        if ($oldAllianceId > 0 && $oldAllianceId !== $allianceId) {
            $oldKey = NpcCache::keyAllianceMembers($oldAllianceId);
            NpcCache::delete($oldKey);
        }
    }
    
    /**
     * Called when a player leaves an alliance
     * 
     * @param int $userId User ID
     * @param int $allianceId Alliance ID
     */
    public static function onAllianceLeave(int $userId, int $allianceId): void
    {
        $key = NpcCache::keyAllianceMembers($allianceId);
        NpcCache::delete($key);
    }
    
    /**
     * Called when NPC state is updated
     * 
     * @param int $npcId NPC user ID
     */
    public static function onNpcStateUpdate(int $npcId): void
    {
        $key = NpcCache::keyNpcState($npcId);
        NpcCache::delete($key);
    }
    
    /**
     * Called when server settings are changed
     * 
     * @param int $serverId Server ID
     */
    public static function onServerSettingsChange(int $serverId): void
    {
        $key = NpcCache::keyServerSettings($serverId);
        NpcCache::delete($key);
    }
    
    /**
     * Called when troops are lost in battle
     * 
     * @param int $villageId Village ID
     */
    public static function onTroopsLost(int $villageId): void
    {
        $key = NpcCache::keyVillageTroops($villageId);
        NpcCache::delete($key);
    }
    
    /**
     * Called when resources change significantly
     * (Usually not cached, but included for completeness)
     * 
     * @param int $villageId Village ID
     */
    public static function onResourcesChange(int $villageId): void
    {
        // Resources are typically read fresh, so no caching
        // But we invalidate village state if it includes resources
        // (Currently not implemented)
    }
    
    /**
     * Called when a scout report is generated
     * Old report should be invalidated
     * 
     * @param int $targetVillageId Target village that was scouted
     */
    public static function onScoutReportGenerated(int $targetVillageId): void
    {
        $key = NpcCache::keyScoutReport($targetVillageId);
        NpcCache::delete($key);
    }
    
    /**
     * Helper: Invalidate all village-related caches
     * 
     * @param int $villageId Village ID
     */
    public static function invalidateAllVillageCache(int $villageId): void
    {
        NpcCache::delete(NpcCache::keyVillageBuildings($villageId));
        NpcCache::delete(NpcCache::keyVillageTroops($villageId));
        NpcCache::delete(NpcCache::keyScoutReport($villageId));
    }
    
    /**
     * Helper: Invalidate all NPC-related caches
     * 
     * @param int $npcId NPC user ID
     */
    public static function invalidateAllNpcCache(int $npcId): void
    {
        NpcCache::delete(NpcCache::keyNpcState($npcId));
        
        // Get all villages owned by this NPC and invalidate them
        $db = DB::getInstance();
        $villages = $db->query("SELECT kid FROM vdata WHERE owner = $npcId");
        
        while ($row = $villages->fetch_assoc()) {
            self::invalidateAllVillageCache((int)$row['kid']);
        }
    }
}
