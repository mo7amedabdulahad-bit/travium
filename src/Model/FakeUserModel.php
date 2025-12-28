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
        if (!$this->canRun()) return;
        $db = DB::getInstance();
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
            
            // Use difficulty-based iteration count instead of time-based calculation
            $difficulty = $row['npc_difficulty'] ?? 'beginner';
            $count = \Core\NpcConfig::getRandomizedIterations($difficulty);
            
            AI::doSomethingRandom($row['kid'], $count);
        }
        
        // === INTERVAL-BASED RAID PROCESSING ===
        // Process raids separately based on cooldown intervals, not probability
        $raidResults = $db->query("SELECT v.kid, v.owner, u.npc_personality,
                                          JSON_EXTRACT(u.npc_info, '$.last_raid_time') as last_raid
                                   FROM vdata v
                                   JOIN users u ON v.owner = u.id
                                   WHERE u.access=3
                                   LIMIT 10");
        
        while ($row = $raidResults->fetch_assoc()) {
            $lastRaid = $row['last_raid'] ?? 0;
            $raidFreq = \Core\NpcConfig::getRaidFrequency($row['npc_personality']);
            
            // Deterministic: only raid if interval has passed
            if (time() - $lastRaid >= $raidFreq['min']) {
                \Core\AI\RaidAI::processRaid($row['owner'], $row['kid']);
            }
        }
        
        // === INTERVAL-BASED ALLIANCE PROCESSING ===
        // Process alliance checks separately, pre-filtered to NPCs without alliances
        $allianceResults = $db->query("SELECT u.id, u.npc_personality,
                                              JSON_EXTRACT(u.npc_info, '$.last_alliance_check') as last_check
                                       FROM users u
                                       WHERE u.access=3
                                         AND u.aid = 0
                                       LIMIT 5");
        
        while ($row = $allianceResults->fetch_assoc()) {
            $lastCheck = $row['last_check'] ?? 0;
            $cooldown = mt_rand(86400, 172800); // 24-48 hours
            
            // Deterministic: only check if cooldown passed
            if (time() - $lastCheck >= $cooldown) {
                $config = \Core\NpcConfig::getNpcConfig($row['id']);
                $tendency = $config['personality_stats']['alliance_tendency'] ?? 50;
                
                // Roll personality dice ONCE per cooldown period
                if (mt_rand(1, 100) <= $tendency) {
                    \Core\AI\AllianceAI::processAlliance($row['id']);
                } else {
                    // Update cooldown even if decided not to join
                    \Core\NpcConfig::updateNpcInfo($row['id'], 'last_alliance_check', time());
                    \Core\AI\NpcLogger::log($row['id'], 'ALLIANCE', 'Decided not to join (personality roll failed)', ['tendency' => $tendency]);
                }
            }
        }
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

