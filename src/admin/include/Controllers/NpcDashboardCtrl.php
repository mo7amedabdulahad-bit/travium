<?php

namespace Controllers;

use Core\Config;
use Core\Database\DB;
use Core\NpcCache;
use Core\NpcPerformanceMonitor;

class NpcDashboardCtrl
{
    public function __construct()
    {
        $this->render();
    }

    private function render()
    {
        $data = $this->getData();
        include dirname(__DIR__) . '/views/npcDashboard.php';
    }

    private function getData()
    {
        $db = DB::getInstance();
        
        // Server Overview
        $overview = $this->getServerOverview($db);
        
        // NPC List
        $npcs = $this->getNpcList($db);
        
        // Performance Metrics
        $performance = $this->getPerformanceMetrics();
        
        // Recent Events
        $events = $this->getRecentEvents($db);
        
        // Cache Stats
        $cacheStats = NpcCache::getStats();
        
        return [
            'overview' => $overview,
            'npcs' => $npcs,
            'performance' => $performance,
            'events' => $events,
            'cacheStats' => $cacheStats
        ];
    }

    private function getServerOverview($db)
    {
        $config = Config::getInstance();
        
        // Game age
        $gameStart = $config->game->start_time;
        $gameAge = time() - $gameStart;
        $ageHours = floor($gameAge / 3600);
        $ageDays = floor($gameAge / 86400);
        
        // Game phase
        $phase = 'Early';
        if ($ageHours > 48) $phase = 'Mid';
        if ($ageHours > 168) $phase = 'Late';
        
        // NPC counts
        $totalNpcs = (int)$db->fetchScalar("SELECT COUNT(*) FROM users WHERE access = 3");
        $activeNpcs = (int)$db->fetchScalar("SELECT COUNT(*) FROM users WHERE access = 3 AND next_tick_at IS NOT NULL");
        
        // Alliance distribution
        $allianceCount = (int)$db->fetchScalar("SELECT COUNT(DISTINCT aid) FROM users WHERE access = 3 AND aid > 0");
        
        // WW Status
        $wwStatus = $this->getWWStatus($db);
        
        return [
            'gameAge' => $ageHours . ' hours (' . $ageDays . ' days)',
            'phase' => $phase,
            'totalNpcs' => $totalNpcs,
            'activeNpcs' => $activeNpcs,
            'allianceCount' => $allianceCount,
            'wwStatus' => $wwStatus
        ];
    }

    private function getWWStatus($db)
    {
        $contenders = (int)$db->fetchScalar("SELECT COUNT(*) FROM users WHERE access = 3 AND ww_alliance_role = 'Contender'");
        $spoilers = (int)$db->fetchScalar("SELECT COUNT(*) FROM users WHERE access = 3 AND ww_alliance_role = 'Spoiler'");
        
        $planHunting = (int)$db->fetchScalar("SELECT COUNT(*) FROM users WHERE access = 3 AND ww_operation_state = 'PlanHunting'");
        $wwBuilding = (int)$db->fetchScalar("SELECT COUNT(*) FROM users WHERE access = 3 AND ww_operation_state = 'WWBuilding'");
        
        return [
            'contenders' => $contenders,
            'spoilers' => $spoilers,
            'planHunting' => $planHunting,
            'wwBuilding' => $wwBuilding
        ];
    }

    private function getNpcList($db)
    {
        // Get filter parameters
        $filterAlliance = isset($_GET['alliance']) ? (int)$_GET['alliance'] : 0;
        $filterPersonality = isset($_GET['personality']) ? $_GET['personality'] : '';
        $filterWWRole = isset($_GET['ww_role']) ? $_GET['ww_role'] : '';
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        
        // Build query
        $where = ["u.access = 3"];
        
        if ($filterAlliance > 0) {
            $where[] = "u.aid = $filterAlliance";
        }
        
        if (!empty($filterPersonality)) {
            $personality = $db->real_escape_string($filterPersonality);
            $where[] = "u.npc_personality = '$personality'";
        }
        
        if (!empty($filterWWRole)) {
            $role = $db->real_escape_string($filterWWRole);
            $where[] = "u.ww_alliance_role = '$role'";
        }
        
        if (!empty($search)) {
            $searchEscaped = $db->real_escape_string($search);
            $where[] = "(u.name LIKE '%$searchEscaped%' OR u.id = '$searchEscaped')";
        }
        
        $whereClause = implode(' AND ', $where);
        
        $query = "
            SELECT 
                u.id,
                u.name,
                u.aid,
                a.tag as alliance_tag,
                u.npc_personality,
                u.npc_difficulty,
                u.ww_alliance_role,
                u.ww_operation_state,
                u.next_tick_at,
                COUNT(DISTINCT v.kid) as village_count
            FROM users u
            LEFT JOIN alidata a ON u.aid = a.id
            LEFT JOIN vdata v ON u.id = v.owner
            WHERE $whereClause
            GROUP BY u.id
            ORDER BY u.id ASC
            LIMIT 100
        ";
        
        $result = $db->query($query);
        $npcs = [];
        
        while ($row = $result->fetch_assoc()) {
            $npcs[] = $row;
        }
        
        return $npcs;
    }

    private function getPerformanceMetrics()
    {
        // Get aggregated stats from last hour
        $stats = NpcPerformanceMonitor::getAggregatedStats(1);
        
        return $stats;
    }

    private function getRecentEvents($db)
    {
        $query = "
            SELECT 
                id,
                event_type,
                created_at,
                processed_at,
                ww_village_id
            FROM npc_world_events
            ORDER BY created_at DESC
            LIMIT 50
        ";
        
        $result = $db->query($query);
        $events = [];
        
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        
        return $events;
    }
    
    /**
     * Get available filter options
     */
    public static function getFilterOptions()
    {
        $db = DB::getInstance();
        
        // Alliances
        $alliances = [];
        $result = $db->query("
            SELECT DISTINCT a.id, a.tag 
            FROM alidata a
            JOIN users u ON a.id = u.aid
            WHERE u.access = 3
            ORDER BY a.tag
        ");
        while ($row = $result->fetch_assoc()) {
            $alliances[] = $row;
        }
        
        // Personalities
        $personalities = ['Aggressive', 'Raider', 'Assassin', 'Balanced', 'Builder', 'Defensive'];
        
        // WW Roles
        $wwRoles = ['Neutral', 'Contender', 'Spoiler'];
        
        return [
            'alliances' => $alliances,
            'personalities' => $personalities,
            'wwRoles' => $wwRoles
        ];
    }
}
