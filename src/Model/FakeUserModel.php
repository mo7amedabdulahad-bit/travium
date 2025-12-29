<?php

namespace Model;

use Core\AI;
use Core\Config;
use Core\Database\DB;
use function getGameElapsedSeconds;
use function getGameSpeed;
use function make_seed;


class FakeUserModel
{
    public function handleFakeUsers()
    {
        // CRITICAL DEBUG LOGGING
        error_log("[NPC_DEBUG] handleFakeUsers() called at " . date('Y-m-d H:i:s'));
        
        if (!$this->canRun()) {
            error_log("[NPC_DEBUG] canRun() returned FALSE - NPCs cannot process!");
            error_log("[NPC_DEBUG] getGameElapsedSeconds: " . getGameElapsedSeconds());
            error_log("[NPC_DEBUG] fakeAccountProcess: " . \Core\Config::getProperty("dynamic", "fakeAccountProcess"));
            return;
        }
        
        error_log("[NPC_DEBUG] canRun() passed - starting NPC processing");
        \Core\AI\NpcLogger::log(0, 'DEBUG', 'handleFakeUsers: canRun passed', []);
        
        $db = DB::getInstance();
        
        // === DO RAIDS/ALLIANCES FIRST (before slow AI cycles) ===
        
        // === INTERVAL-BASED RAID PROCESSING ===
        error_log("[NPC_DEBUG] Starting raid interval processing");
        \Core\AI\NpcLogger::log(0, 'SYSTEM', 'Starting raid interval processing', []);
        
        $raidResults = $db->query("SELECT v.kid, v.owner, u.name, u.npc_personality,
                                          JSON_EXTRACT(u.npc_info, '$.last_raid_time') as last_raid
                                   FROM vdata v
                                   JOIN users u ON v.owner = u.id
                                   WHERE u.access=3
                                   LIMIT 10");
        
        if (!$raidResults) {
            error_log("[NPC_DEBUG] Raid query FAILED: " . $db->error);
            \Core\AI\NpcLogger::log(0, 'ERROR', 'Raid query failed: ' . $db->error, []);
        } else {
            $raidCount = $raidResults->num_rows;
            error_log("[NPC_DEBUG] Found $raidCount NPC villages for raid check");
            \Core\AI\NpcLogger::log(0, 'SYSTEM', "Found $raidCount NPC villages for raid check", []);
            
            while ($row = $raidResults->fetch_assoc()) {
                error_log("[NPC_DEBUG] Processing NPC village kid={$row['kid']}, owner={$row['owner']}, name={$row['name']}");
                
                $lastRaid = $row['last_raid'] ?? 0;
                error_log("[NPC_DEBUG] Getting raid frequency for personality: {$row['npc_personality']}");
                
                try {
                    $raidFreq = \Core\NpcConfig::getRaidFrequency($row['npc_personality']);
                    error_log("[NPC_DEBUG] Raid frequency: min={$raidFreq['min']}s");
                } catch (\Exception $e) {
                    error_log("[NPC_DEBUG] ERROR getting raid frequency: " . $e->getMessage());
                    continue;
                }
                
                $timeSince = time() - $lastRaid;
                
                \Core\AI\NpcLogger::log($row['owner'], 'RAID_CHECK', "Interval check: {$timeSince}s since last raid (min: {$raidFreq['min']}s)", [
                    'kid' => $row['kid'],
                    'last_raid' => $lastRaid,
                    'time_since' => $timeSince,
                    'min_interval' => $raidFreq['min']
                ]);
                
                if ($timeSince >= $raidFreq['min']) {
                    error_log("[NPC_DEBUG] Raid interval ready - processing raid");
                    \Core\AI\NpcLogger::log($row['owner'], 'RAID_TRIGGER', "Interval ready - triggering raid for {$row['name']}", []);
                    
                    try {
                        $result = \Core\AI\RaidAI::processRaid($row['owner'], $row['kid']);
                        if ($result) {
                            \Core\AI\NpcLogger::log($row['owner'], 'RAID_SUCCESS', 'Raid processed successfully', []);
                        } else {
                            \Core\AI\NpcLogger::log($row['owner'], 'RAID_SKIP', 'Raid not sent (no targets/troops/config)', []);
                        }
                    } catch (\Exception $e) {
                        error_log("[NPC_DEBUG] EXCEPTION during raid: " . $e->getMessage());
                        \Core\AI\NpcLogger::log($row['owner'], 'RAID_ERROR', 'Exception during raid: ' . $e->getMessage(), []);
                    }
                } else {
                    error_log("[NPC_DEBUG] Raid on cooldown - skipping");
                }
            }
            
            error_log("[NPC_DEBUG] Finished raid processing loop");
        }
        
        // === INTERVAL-BASED ALLIANCE PROCESSING ===
        \Core\AI\NpcLogger::log(0, 'SYSTEM', 'Starting alliance interval processing', []);
        
        $allianceResults = $db->query("SELECT u.id, u.name, u.npc_personality,
                                              JSON_EXTRACT(u.npc_info, '$.last_alliance_check') as last_check
                                       FROM users u
                                       WHERE u.access=3
                                         AND u.aid = 0
                                       LIMIT 5");
        
        if (!$allianceResults) {
            \Core\AI\NpcLogger::log(0, 'ERROR', 'Alliance query failed: ' . $db->error, []);
        } else {
            $allianceCount = $allianceResults->num_rows;
            \Core\AI\NpcLogger::log(0, 'SYSTEM', "Found $allianceCount NPCs without alliances for alliance check", []);
            
            while ($row = $allianceResults->fetch_assoc()) {
                $lastCheck = $row['last_check'] ?? 0;
                $cooldown = mt_rand(86400, 172800);
                $timeSince = time() - $lastCheck;
                
                \Core\AI\NpcLogger::log($row['id'], 'ALLIANCE_CHECK', "Cooldown check: {$timeSince}s since last check", [
                    'last_check' => $lastCheck,
                    'cooldown' => $cooldown,
                    'time_since' => $timeSince
                ]);
                
                if ($timeSince >= $cooldown) {
                    $config = \Core\NpcConfig::getNpcConfig($row['id']);
                    $tendency = $config['personality_stats']['alliance_tendency'] ?? 50;
                    $roll = mt_rand(1, 100);
                    
                    \Core\AI\NpcLogger::log($row['id'], 'ALLIANCE_ROLL', "Personality roll: $roll vs tendency: $tendency", [
                        'roll' => $roll,
                        'tendency' => $tendency
                    ]);
                    
                    if ($roll <= $tendency) {
                        \Core\AI\NpcLogger::log($row['id'], 'ALLIANCE_ATTEMPT', "Attempting to create/join alliance", []);
                        try {
                            \Core\AI\AllianceAI::processAlliance($row['id']);
                        } catch (\Exception $e) {
                            \Core\AI\NpcLogger::log($row['id'], 'ALLIANCE_ERROR', 'Exception: ' . $e->getMessage(), []);
                        }
                    } else {
                        \Core\AI\NpcLogger::log($row['id'], 'ALLIANCE_SKIP', "Personality roll failed - staying solo", []);
                    }
                    
                    $db->query("UPDATE users SET npc_info=JSON_SET(COALESCE(npc_info, '{}'), '$.last_alliance_check', " . time() . ") WHERE id={$row['id']}");
                } else {
                    $waitHours = number_format(($cooldown - $timeSince) / 3600, 1);
                    \Core\AI\NpcLogger::log($row['id'], 'ALLIANCE_WAIT', "Cooldown active - wait {$waitHours} hours more", []);
                }
            }
        }
        
        // === NOW DO THE SLOW AI CYCLES ===
        \Core\AI\NpcLogger::log(0, 'DEBUG', 'Raid/Alliance done, starting AI cycles', []);
        
        $interval = mt_rand(3600, 10800);
        $stmt = $db->query("SELECT id FROM users WHERE access=3 AND lastHeroExpCheck <= " . (time() - $interval) . " LIMIT 10");
        while ($row = $stmt->fetch_assoc()) {
            $db->query("UPDATE users SET lastHeroExpCheck=" . time() . " WHERE id={$row['id']}");
            $exp = mt_rand(10, 50) * ceil(getGameSpeed() / 100);
            $db->query("UPDATE hero SET exp=exp+$exp WHERE uid={$row['id']}");
        }
        
        if (getGameSpeed() <= 10) {
            $interval = mt_rand(600, 3600);
        } else if (getGameSpeed() <= 100) {
            $interval = mt_rand(200, 400);
        } else if (getGameSpeed() <= 1000) {
            $interval = mt_rand(100, 250);
        } else {
            $interval = mt_rand(50, 150);
        }
        $limit = 10;
        $checkInterval = 200;
        $time = time() - $checkInterval;
        $now = time();
        $results = $db->query("SELECT v.kid, v.lastVillageCheck, v.owner, u.npc_difficulty 
                               FROM vdata v 
                               JOIN users u ON v.owner = u.id 
                               WHERE v.lastVillageCheck < $time 
                                 AND u.access=3 
                               LIMIT $limit");
        
        while ($row = $results->fetch_assoc()) {
            $db->query("UPDATE vdata SET lastVillageCheck=$now WHERE kid={$row['kid']}");
            if ($row['lastVillageCheck'] <= 10) continue;
            
            $difficulty = $row['npc_difficulty'] ?? 'beginner';
            $count = \Core\NpcConfig::getRandomizedIterations($difficulty);
            
            AI::doSomethingRandom($row['kid'], $count);
        }
        
        \Core\AI\NpcLogger::log(0, 'DEBUG', 'handleFakeUsers: Complete', []);
    }

    private function canRun()
    {
        if (getGameElapsedSeconds() <= 0 || !Config::getProperty("dynamic", "fakeAccountProcess")) return false;
        make_seed();
        return true;
    }

    public function handleFakeUserExpands()
    {
        if (!$this->canRun()) return;
        if (getGameSpeed() <= 10) {
            $elapsedSeconds = getGameElapsedSeconds();
            if ($elapsedSeconds <= 4 * 86400) {
                $interval = 3 * 86400;
            } else if ($elapsedSeconds <= 15 * 86400) {
                $interval = 7 * 86400;
            } else if ($elapsedSeconds <= 45 * 86400) {
                $interval = 5 * 86400;
            } else {
                $interval = 15 * 86400;
            }
        } else {
            $interval = (mt_rand(65, 165) / 100) * 86400;
        }
        $limit = 5;
        $time = time() - $interval;
        $db = DB::getInstance();
        $register = new RegisterModel();
        $result = $db->query("SELECT id, race, lastVillageExpand FROM users WHERE access=3 AND lastVillageExpand<=$time LIMIT $limit");
        while ($row = $result->fetch_assoc()) {
            $db->query("UPDATE users SET lastVillageExpand=" . ($time + $interval) . " WHERE id={$row['id']}");
            if ($row['lastVillageExpand'] == 0) {
                continue;
            }
            $count = ceil((time() - $row['lastVillageExpand']) / $interval);
            $find = $register->generateFakeUserVillage($count);
            if (!empty($find)) {
                $villages = explode(",", $find);
                $db->query("UPDATE available_villages SET occupied=1 WHERE kid IN($find)");
                foreach ($villages as $kid) {
                    $register->createNewVillage($row['id'], $row['race'], $kid);
                }
            }
        }
    }
}

