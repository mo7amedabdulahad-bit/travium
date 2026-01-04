<?php

namespace Core;

use Core\Database\DB;

/**
 * NPC Village Naming Service
 * Generates sequential village names for NPCs
 */
class NpcNameService
{
    /**
     * Get next village name for NPC
     * Pattern: "{PlayerName}", "{PlayerName} 2", "{PlayerName} 3", etc.
     * 
     * @param int $userId NPC user ID
     * @return string Village name
     */
    public static function getNextVillageName($userId)
    {
        $db = DB::getInstance();
        
        // Get player name
        $playerName = self::getPlayerName($userId);
        
        if (!$playerName) {
            return "Village " . time(); // Fallback
        }
        
        // Count existing villages
        $villageCount = (int)$db->fetchScalar("
            SELECT COUNT(*) FROM vdata WHERE owner = $userId
        ");
        
        // First village has no suffix
        if ($villageCount <= 1) {
            return $playerName;
        }
        
        // Sequential numbering: 2, 3, 4, etc.
        return "$playerName $villageCount";
    }
    
    /**
     * Get player name from users table
     * 
     * @param int $userId User ID
     * @return string|null Player name
     */
    public static function getPlayerName($userId)
    {
        $db = DB::getInstance();
        
        $result = $db->query("SELECT name FROM users WHERE id = $userId");
        
        if (!$result || $result->num_rows === 0) {
            return null;
        }
        
        $row = $result->fetch_assoc();
        return $row['name'] ?? null;
    }
}
