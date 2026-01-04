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
        
        include __DIR__ . '/../views/npcDetail.tpl.php';
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
            LEFT JOIN npc_villages nv ON v.kid = nv.kid
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
