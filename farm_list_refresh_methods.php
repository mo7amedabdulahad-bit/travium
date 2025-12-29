
    /**
     * Phase 3: Auto-refresh NPC farm-lists daily
     * Removes failed targets and adds better ones
     */
    public function refreshNpcFarmLists()
    {
        $db = DB::getInstance();
        
        \Core\AI\NpcLogger::log(0, 'SYSTEM', 'Starting daily farm-list refresh', []);
        
        // Get all NPC farm-lists
        $lists = $db->query("SELECT DISTINCT f.id, f.kid, f.owner, u.npc_personality, u.race
                             FROM farmlist f
                             JOIN users u ON f.owner = u.id
                             WHERE u.access = 3
                             LIMIT 100"); // Process 100 per run
        
        if (!$lists) {
            \Core\AI\NpcLogger::log(0, 'ERROR', 'Farm-list refresh query failed: ' . $db->error, []);
            return;
        }
        
        $refreshed = 0;
        while ($list = $lists->fetch_assoc()) {
            try {
                $this->refreshSingleFarmList(
                    $list['id'], 
                    $list['owner'], 
                    $list['kid'],
                    $list['npc_personality'],
                    $list['race']
                );
                $refreshed++;
            } catch (\Exception $e) {
                \Core\AI\NpcLogger::log($list['owner'], 'REFRESH_ERROR', 
                    'Exception refreshing farm-list: ' . $e->getMessage(), [
                        'list_id' => $list['id']
                    ]);
            }
        }
        
        \Core\AI\NpcLogger::log(0, 'SYSTEM', "Farm-list refresh complete", [
            'refreshed' => $refreshed
        ]);
    }
    
    /**
     * Refresh a single NPC farm-list
     */
    private function refreshSingleFarmList($listId, $uid, $fromKid, $personality, $race)
    {
        $db = DB::getInstance();
        
        // Step 1: Analyze current targets
        $currentTargets = $db->query("SELECT kid FROM raidlist WHERE lid=$listId");
        $targetKids = [];
        while ($row = $currentTargets->fetch_assoc()) {
            $targetKids[] = $row['kid'];
        }
        
        if (empty($targetKids)) {
            \Core\AI\NpcLogger::log($uid, 'REFRESH_EMPTY', "Farm-list is empty - populating", [
                'list_id' => $listId
            ]);
            // Populate from scratch
            $this->populateFarmList($listId, $uid, $fromKid, $personality, $race);
            return;
        }
        
        \Core\AI\NpcLogger::log($uid, 'FARMLIST_REFRESH', "Analyzing targets", [
            'list_id' => $listId,
            'current_targets' => count($targetKids)
        ]);
        
        // Step 2: Check raid history (last 24 hours)
        $targetList = implode(',', $targetKids);
        $yesterday = time() - 86400;
        
        $history = $db->query("SELECT to_kid, 
                                      COUNT(*) as total_raids,
                                      SUM(IF(wood_gain > 0 OR clay_gain > 0 OR iron_gain > 0 OR crop_gain > 0, 1, 0)) as successful_raids,
                                      SUM(wood_gain + clay_gain + iron_gain + crop_gain) as total_loot
                               FROM movement_log
                               WHERE kid=$fromKid 
                                 AND to_kid IN ($targetList)
                                 AND attack_type=3
                                 AND end_time > $yesterday
                               GROUP BY to_kid");
        
        $performance = [];
        while ($row = $history->fetch_assoc()) {
            $performance[$row['to_kid']] = [
                'raids' => (int)$row['total_raids'],
                'success' => (int)$row['successful_raids'],
                'loot' => (int)$row['total_loot'],
                'rate' => $row['total_raids'] > 0 ? ($row['successful_raids'] / $row['total_raids']) : 0
            ];
        }
        
        // Step 3: Remove underperformers (0% success rate with 3+ raids)
        $removed = 0;
        foreach ($performance as $targetKid => $stats) {
            if ($stats['raids'] >= 3 && $stats['success'] == 0) {
                $db->query("DELETE FROM raidlist WHERE lid=$listId AND kid=$targetKid");
                $removed++;
                
                \Core\AI\NpcLogger::log($uid, 'FARMLIST_REMOVED', "Removed failed target", [
                    'target' => $targetKid,
                    'raids' => $stats['raids'],
                    'success_rate' => '0%'
                ]);
            }
        }
        
        // Step 4: Remove deleted/conquered villages
        $validTargets = $db->query("SELECT kid FROM vdata WHERE kid IN ($targetList)");
        $stillExists = [];
        while ($row = $validTargets->fetch_assoc()) {
            $stillExists[] = $row['kid'];
        }
        
        $deleted = array_diff($targetKids, $stillExists);
        foreach ($deleted as $deletedKid) {
            $db->query("DELETE FROM raidlist WHERE lid=$listId AND kid=$deletedKid");
            $removed++;
            
            \Core\AI\NpcLogger::log($uid, 'FARMLIST_REMOVED', "Removed deleted village", [
                'target' => $deletedKid
            ]);
        }
        
        // Step 5: Add new targets if below 10
        $currentCount = $db->fetchScalar("SELECT COUNT(*) FROM raidlist WHERE lid=$listId");
        
        if ($currentCount < 10) {
            $needed = 10 - $currentCount;
            $added = $this->addNewFarmTargets($listId, $uid, $fromKid, $personality, $race, $needed);
            
            \Core\AI\NpcLogger::log($uid, 'FARMLIST_OPTIMIZED', "Farm-list refresh complete", [
                'list_id' => $listId,
                'removed' => $removed,
                'added' => $added,
                'final_count' => $currentCount - $removed + $added
            ]);
        } else {
            \Core\AI\NpcLogger::log($uid, 'FARMLIST_OK', "Farm-list is healthy", [
                'list_id' => $listId,
                'removed' => $removed
            ]);
        }
    }
    
    /**
     * Add new farm targets to a list
     */
    private function addNewFarmTargets($listId, $uid, $fromKid, $personality, $race, $count)
    {
        $db = DB::getInstance();
        
        // Get existing targets to avoid duplicates
        $existing = $db->query("SELECT kid FROM raidlist WHERE lid=$listId");
        $excludeKids = [];
        while ($row = $existing->fetch_assoc()) {
            $excludeKids[] = $row['kid'];
        }
        $excludeList = empty($excludeKids) ? '0' : implode(',', $excludeKids);
        
        // Find new targets using the same logic as initial creation
        $newTargets = \Core\NpcConfig::findInitialFarmTargets($uid, $fromKid, $count, $excludeList);
        
        $added = 0;
        foreach ($newTargets as $target) {
            $result = $db->query("INSERT INTO raidlist (lid, kid, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10) 
                                  VALUES ($listId, {$target['kid']}, 0,0,0,0,0,0,0,0,0,0)");
            if ($result) {
                $added++;
                \Core\AI\NpcLogger::log($uid, 'FARMLIST_ADDED', "Added new target", [
                    'target' => $target['kid'],
                    'distance' => $target['distance']
                ]);
            }
        }
        
        return $added;
    }
    
    /**
     * Populate an empty farm-list from scratch
     */
    private function populateFarmList($listId, $uid, $fromKid, $personality, $race)
    {
        $targets = \Core\NpcConfig::findInitialFarmTargets($uid, $fromKid, 10);
        
        foreach ($targets as $target) {
            $db = DB::getInstance();
            $db->query("INSERT INTO raidlist (lid, kid, t1, t2, t3, t4, t5, t6, t7, t8, t9, t10) 
                        VALUES ($listId, {$target['kid']}, 0,0,0,0,0,0,0,0,0,0)");
        }
        
        \Core\AI\NpcLogger::log($uid, 'FARMLIST_POPULATED', "Populated empty farm-list", [
            'list_id' => $listId,
            'targets' => count($targets)
        ]);
    }
