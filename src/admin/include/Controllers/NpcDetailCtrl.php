<?php

use Core\Config;
use Core\Database\DB;
use Core\NpcCache;
use Core\NpcPerformanceMonitor;
use Core\NpcScheduler;

class NpcDetailCtrl
{
    private $npcId;
    private $npc;
    
    public function __construct()
    {
        $this->npcId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($this->npcId <= 0) {
            Dispatcher::getInstance()->appendContent("Invalid NPC ID");
            return;
        }
        
        // Handle manual actions
        if (isset($_POST['action'])) {
            $this->handleAction($_POST['action']);
        }
        
        $this->loadNpcData();
        $this->render();
    }

    private function loadNpcData()
    {
        $db = DB::getInstance();
        
        $query = "
            SELECT u.*, a.tag as alliance_tag, a.name as alliance_name
            FROM users u
            LEFT JOIN alidata a ON u.aid = a.id
            WHERE u.id = {$this->npcId} AND u.access = 3
        ";
        
        $result = $db->query($query);
        
        if ($result->num_rows == 0) {
            die("NPC not found or not an NPC");
        }
        
        $this->npc = $result->fetch_assoc();
    }

    private function handleAction($action)
    {
        switch ($action) {
            case 'force_tick':
                $this->forceTick();
                break;
            case 'reset_cooldowns':
                $this->resetCooldowns();
                break;
            case 'clear_cache':
                $this->clearCache();
                break;
        }
    }

    private function forceTick()
    {
        try {
            // Force immediate tick
            NpcScheduler::processSingleNpc($this->npcId);
            $message = "✅ Tick executed successfully";
        } catch (\Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
        }
        
        echo "<div class='success'>$message</div>";
    }

    private function resetCooldowns()
    {
        $db = DB::getInstance();
        $db->query("DELETE FROM npc_defense_cooldowns WHERE npc_id = {$this->npcId}");
        $db->query("DELETE FROM npc_raid_cooldowns WHERE target_village_id IN (SELECT kid FROM vdata WHERE owner = {$this->npcId})");
        
        echo "<div class='success'>✅ Cooldowns reset</div>";
    }

    private function clearCache()
    {
        // Clear all cache for this NPC
        \Core\NpcCacheInvalidation::invalidateAllNpcCache($this->npcId);
        
        echo "<div class='success'>✅ Cache cleared for NPC {$this->npcId}</div>";
    }

    private function render()
    {
        $npc = $this->npc;
        $villages = $this->getVillages();
        $recentActions = $this->getRecentActions();
        $memory = $this->getMemoryState();
        
        $html = '<h2>NPC Detail: ' . htmlspecialchars($npc['name']) . ' (#' . $npc['id'] . ')</h2>';
        
        // Basic Info
        $html .= '<h3>Basic Information</h3>';
        $html .= '<table class="table" border="1" cellpadding="5">';
        $html .= '<tr><th>Alliance</th><td>' . htmlspecialchars($npc['alliance_tag'] ?? 'None') . '</td></tr>';
        $html .= '<tr><th>Personality</th><td>' . htmlspecialchars($npc['npc_personality'] ?? 'N/A') . '</td></tr>';
        $html .= '<tr><th>Difficulty</th><td>' . htmlspecialchars($npc['npc_difficulty'] ?? 'N/A') . '</td></tr>';
        $html .= '<tr><th>WW Role</th><td>' . htmlspecialchars($npc['ww_alliance_role'] ?? 'Neutral') . '</td></tr>';
        $html .= '<tr><th>WW State</th><td>' . htmlspecialchars($npc['ww_operation_state'] ?? 'Idle') . '</td></tr>';
        $html .= '</table>';
        
        // Villages
        $html .= '<h3>Villages (' . count($villages) . ')</h3>';
        $html .= '<table class="table" border="1" cellpadding="5">';
        $html .= '<tr><th>Name</th><th>Coordinates</th><th>Population</th><th>Type</th><th>Role</th></tr>';
        
        foreach ($villages as $v) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($v['name']) . '</td>';
            $html .= '<td>(' . $v['x'] . '|' . $v['y'] . ')</td>';
            $html .= '<td>' . $v['pop'] . '</td>';
            $html .= '<td>' . ($v['fieldtype'] ?? 'Normal') . '</td>';
            $html .= '<td>' . htmlspecialchars($v['village_role'] ?? 'Main') . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        
        // Recent Actions
        if (!empty($recentActions)) {
            $html .= '<h3>Recent Performance (Last ' . count($recentActions) . ' ticks)</h3>';
            $html .= '<table class="table" border="1" cellpadding="5">';
            $html .= '<tr><th>Time</th><th>Duration (ms)</th><th>Queries</th><th>Cache Hit Rate</th><th>Actions</th></tr>';
            
            foreach ($recentActions as $action) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($action['timestamp']) . '</td>';
                $html .= '<td>' . $action['tick_duration_ms'] . '</td>';
                $html .= '<td>' . $action['queries_executed'] . '</td>';
                $html .= '<td>' . $action['cache_hit_rate'] . '%</td>';
                $html .= '<td>' . count($action['actions_taken']) . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</table>';
        }
        
        // Manual Controls
        $html .= '<h3>Manual Controls</h3>';
        $html .= '<form method="POST">';
        $html .= '<button type="submit" name="action" value="force_tick">Force Tick</button> ';
        $html .= '<button type="submit" name="action" value="reset_cooldowns">Reset Cooldowns</button> ';
        $html .= '<button type="submit" name="action" value="clear_cache">Clear Cache</button>';
        $html .= '</form>';
        
        $html .= '<br><a href="admin.php?action=npcDashboard">← Back to Dashboard</a>';
        
        Dispatcher::getInstance()->appendContent($html);
    }

    private function getVillages()
    {
        $db = DB::getInstance();
        
        $query = "
            SELECT 
                v.kid,
                v.name,
                v.pop,
                w.x,
                w.y,
                w.fieldtype,
                nv.village_role,
                nv.village_number,
                nv.founded_at
            FROM vdata v
            JOIN wdata w ON v.kid = w.id
            LEFT JOIN npc_villages nv ON v.kid = nv.village_id
            WHERE v.owner = {$this->npcId}
            ORDER BY nv.village_number ASC, v.kid ASC
        ";
        
        $result = $db->query($query);
        $villages = [];
        
        while ($row = $result->fetch_assoc()) {
            $villages[] = $row;
        }
        
        return $villages;
    }

    private function getRecentActions()
    {
        // Get from performance log
        $logFile = dirname(dirname(dirname(dirname(__DIR__)))) . '/logs/npc_performance.log';
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile);
        $actions = [];
        
        // Get last 10 entries for this NPC
        foreach (array_reverse($lines) as $line) {
            $data = @json_decode($line, true);
            if ($data && isset($data['npc_id']) && $data['npc_id'] == $this->npcId) {
                $actions[] = $data;
                if (count($actions) >= 10) break;
            }
        }
        
        return $actions;
    }

    private function getMemoryState()
    {
        if (empty($this->npc['npc_memory_json'])) {
            return [];
        }
        
        return @json_decode($this->npc['npc_memory_json'], true) ?: [];
    }
}
