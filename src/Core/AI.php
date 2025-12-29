<?php

namespace Core;

use function array_map;
use function array_merge;
use Controller\Build\TroopBuilding;
use Core\Database\DB;
use Game\Buildings\AutoUpgradeAI;
use Game\Formulas;
use Game\Hero\HeroHelper;
use function getGameSpeed;
use function method_exists;
use Model\ArtefactsModel;
use Model\TrainingModel;
use Model\VillageModel;
use function nrToUnitId;
use function shuffle;
use function unitIdToNr;

class AI_MAIN
{
    const RESOURCES_RATE = 2 / 3;
    const SKIP_RESEARCH = TRUE;
    const SKIP_WORKERS = TRUE;

    private $village             = [];
    private $user                = [];
    private $researches          = [];
    private $smithy              = [];
    private $resources           = [0, 0, 0, 0];
    private $hero_percents       = [0, 0];
    private $aiBuilder;
    private $buildings           = [];
    private $training_buildings  = [
        'barracks'      => [],
        'stable'        => [],
        'workshop'      => [],
        'horseDrinking' => 0,
        'smithyLevel'   => 0,
        'academyLevel'  => 0,
        'available'     => [],
    ];
    private $art_eff             = 1;
    private $smithyUpgradesCount = 0;


    public function __construct($kid)
    {
        $db = DB::getInstance();
        $village = $db->query("SELECT kid, wood, clay, iron, crop, owner, isWW, capital, created, maxstore, maxcrop, cropp, upkeep, isWW FROM vdata WHERE kid=$kid");
        if (!$village->num_rows) {
            return;
        }
        $this->village = $village->fetch_assoc();
        if ($this->village['isWW']) return;

        $this->user = $db->query("SELECT race, plus, fasterTraining FROM users WHERE id={$this->village['owner']}")->fetch_assoc();

        $this->calculateResources();

        $this->hero_percents = (new HeroHelper())->calcTrainEffect($db->fetchScalar("SELECT helmet FROM inventory WHERE uid={$this->village['owner']}"));
        $this->art_eff = ArtefactsModel::getArtifactEffectByType($this->village['owner'], $kid, ArtefactsModel::ARTIFACT_INCREASE_TRAINING_SPEED);

        $this->researches = $db->query("SELECT * FROM tdata WHERE kid=$kid")->fetch_assoc();
        $this->smithy = $db->query("SELECT * FROM smithy WHERE kid=$kid")->fetch_assoc();
        $this->smithyUpgradesCount = (int)$db->fetchScalar("SELECT COUNT(id) FROM research WHERE mode=0 AND kid=$kid");

        $this->resources = [
            $this->village['wood'],
            $this->village['clay'],
            $this->village['iron'],
            $this->village['crop'],
        ];
        
        // Apply dynamic resource spending rate for NPCs
        if (($this->user['access'] ?? 0) == 3) {
            $spendingRate = NpcConfig::getResourceSpendingRate($this->user['id'], $kid);
            
            // Adjust available resources based on spending rate
            for ($i = 0; $i < 4; $i++) {
                $this->resources[$i] = (int)($this->resources[$i] * $spendingRate);
            }
        }
        
        $this->buildings = (new VillageModel())->getBuildingsAssoc($kid);
        $this->populateTrainingBuildings();
        $this->aiBuilder   = new AIAutoUpgradeAI($this->village, $this->user, $this->resources, $this->slots);
        $this->unitBuilder = new UnitBuilder($kid, $this->user, $this->resources);
        $fakeUserInfo = [
            'id' => $this->user['id'],
            'race' => $this->user['race'],
            'kid' => $kid,
        ];
        $this->masterBuilder = new MasterBuilder($kid, $this->village,$fakeUserInfo);
        if (self::SKIP_WORKERS) {
            $this->aiBuilder->skipWorkers();
        }

    }

    private function calculateResources()
    {
        $this->resources = array_map(function ($x) {
            return $x * self::RESOURCES_RATE;
        },
            [$this->village['wood'], $this->village['clay'], $this->village['iron'], $this->village['crop']]);
    }

    private function populateTrainingBuildings()
    {
        for ($i = 19; $i <= 38; ++$i) {
            $build = $this->buildings[$i];
            if (!$build['item_id'] || $build['level'] <= 0) continue;
            if ($build['item_id'] == 19 || $build['item_id'] == 29) {
                $this->training_buildings['barracks'][] = $build;
            } else if ($build['item_id'] == 20 || $build['item_id'] == 30) {
                $this->training_buildings['stable'][] = $build;
            } else if ($build['item_id'] == 21) {
                $this->training_buildings['workshop'][] = $build;
            } else if ($build['item_id'] == 41) {
                $this->training_buildings['horseDrinking'] = $build['level'];
            } else if ($build['item_id'] == 13) {
                $this->training_buildings['smithyLevel'] = $build['level'];
            } else if ($build['item_id'] == 22) {
                $this->training_buildings['academyLevel'] = $build['level'];
            }
        }
        $available = [];
        if (sizeof($this->training_buildings['barracks'])) {
            $available = array_merge($available, TroopBuilding::_getTroopBuildingTroopsStatic($this->user['race'], 19));
        }
        if (sizeof($this->training_buildings['stable'])) {
            $available = array_merge($available, TroopBuilding::_getTroopBuildingTroopsStatic($this->user['race'], 20));
        }
        if (sizeof($this->training_buildings['workshop'])) {
            $available = array_merge($available, TroopBuilding::_getTroopBuildingTroopsStatic($this->user['race'], 21));
        }
        $this->training_buildings['available'] = array_map("unitIdToNr", $available);
    }

    public function trainUnits()
    {
        $isNpc = ($this->user['access'] ?? 0) == 3;
        $owner = $this->village['owner'] ?? 0;
        
        // Get available units based on training buildings
        $available = $this->training_buildings['available'];
        if (!sizeof($available)) {
            if ($isNpc) {
                \Core\AI\NpcLogger::log($owner, 'TRAIN_FAIL', 'No training buildings available', [
                    'barracks' => sizeof($this->training_buildings['barracks']),
                    'stable' => sizeof($this->training_buildings['stable']),
                    'workshop' => sizeof($this->training_buildings['workshop'])
                ]);
            }
            return 0;
        }
        
        if ($isNpc) {
            \Core\AI\NpcLogger::log($owner, 'TRAIN_ATTEMPT', 'Attempting to train units', [
                'available_unit_types' => $available,
                'resources' => $this->resources
            ]);
        }
        
        // For NPCs: use personality-based unit preferences
        if ($isNpc) {
            $config = NpcConfig::getNpcConfig($this->user['id']);
            if ($config) {
                $preferredUnits = NpcConfig::getPreferredUnits(
                    $config['npc_personality'], 
                    $this->user['race']
                );
                
                \Core\AI\NpcLogger::log($owner, 'TRAIN_PREFS', 'Checking preferred units', [
                    'preferred' => $preferredUnits,
                    'available' => $available
                ]);
                
                // Try to train preferred units in order
                foreach ($preferredUnits as $unitId) {
                    // Check if unit is available/researched
                    if (!in_array($unitId, $available)) {
                        \Core\AI\NpcLogger::log($owner, 'TRAIN_SKIP', "Unit $unitId not available/researched", []);
                        continue;
                    }
                    
                    // Check if we can afford it (FIXED: was maxUnitsOf, should be maxUnits)
                    $canTrain = $this->maxUnits($unitId);
                    if ($canTrain > 0) {
                        // Train between 1-5 units of this type
                        $count = min($canTrain, mt_rand(1, 5));
                        $this->unitBuilder->add($unitId, $count);
                        \Core\AI\NpcLogger::log($owner, 'TRAIN_SUCCESS', "Training $count x unit $unitId", [
                            'unit_id' => $unitId,
                            'count' => $count,
                            'max_possible' => $canTrain
                        ]);
                        return 1;
                    } else {
                        \Core\AI\NpcLogger::log($owner, 'TRAIN_NO_RES', "Not enough resources for unit $unitId", [
                            'unit_id' => $unitId,
                            'resources' => $this->resources
                        ]);
                    }
                }
                
                \Core\AI\NpcLogger::log($owner, 'TRAIN_FAIL', 'No preferred units affordable', [
                    'preferred_units' => $preferredUnits,
                    'resources' => $this->resources
                ]);
            } else {
                \Core\AI\NpcLogger::log($owner, 'TRAIN_FAIL', 'No NPC config found', []);
            }
        }
        
        // Fallback to random for non-NPCs or if no preferred units available
        $unitId = $available[mt_rand(0, sizeof($available) - 1)];
        $canTrain = $this->maxUnits($unitId);  // FIXED: was maxUnitsOf
        if ($canTrain <= 0) {
            if ($isNpc) {
                \Core\AI\NpcLogger::log($owner, 'TRAIN_FAIL', "Cannot afford random unit $unitId", [
                    'unit_id' => $unitId,
                    'resources' => $this->resources
                ]);
            }
            return 0;
        }
        
        $count = min($canTrain, mt_rand(1, 5));
        $this->unitBuilder->add($unitId, $count);
        if ($isNpc) {
            \Core\AI\NpcLogger::log($owner, 'TRAIN_SUCCESS', "Training $count x unit $unitId (fallback)", [
                'unit_id' => $unitId,
                'count' => $count
            ]);
        }
        return 1;
    }

    private function chooseUnit()
    {
        $race = $this->user['race'];
        $barracks = $this->training_buildings['barracks'];
        $stable = $this->training_buildings['stable'];
        $workshop = $this->training_buildings['workshop'];
        $arr = [];
        $barracksUnits = array_map("unitIdToNr", TroopBuilding::_getTroopBuildingTroopsStatic($race, 19));
        $stableUnits = array_map("unitIdToNr", TroopBuilding::_getTroopBuildingTroopsStatic($race, 20));
        $onlyGreatBarracks = sizeof($this->training_buildings['barracks']) == 1 && $this->training_buildings['barracks'][0]['item_id'] == 29;
        $onlyGreatStable = sizeof($stable) == 1 && $stable[0]['item_id'] == 30;
        foreach ($this->training_buildings['available'] as $i) {
            if ($i > 1 && !$this->researches['u' . $i] && !self::SKIP_RESEARCH) continue;
            $isGreat = false;
            $isBarracks = in_array($i, $barracksUnits);
            $isStable = in_array($i, $stableUnits);
            if ($isBarracks) {
                $isGreat = $onlyGreatBarracks;
            } else if ($isStable) {
                $isGreat = $onlyGreatStable;
            }
            $neededResources = Formulas::uTrainingCost(nrToUnitId($i, $race), $isGreat);
            if (!$this->isResourcesAvailable($neededResources)) {
                continue;
            }
            $arr[] = [
                'nr'         => $i,
                'costs'      => $neededResources,
                'isBarracks' => $isBarracks,
                'isStable'   => $isStable,
                'isGreat'    => $isGreat
            ];
        }
        if (!sizeof($arr)) return false;
        shuffle($arr);
        $selected = $arr[mt_rand(0, sizeof($arr) - 1)];
        if ($selected['isBarracks']) {
            $building = $barracks[0];
        } else if ($selected['isStable']) {
            $building = $stable[0];
        } else {
            $building = $workshop[0];
        }
        $percent = $this->user['fasterTraining'] > time() ? Config::getInstance()->extraSettings->generalOptions->fasterTraining->percent : 0;
        $training_time = Formulas::uTrainingTime(nrToUnitId($selected['nr'], $race),
            $building['level'],
            $this->training_buildings['horseDrinking'],
            $this->hero_percents,
            $this->art_eff,
            $percent);
        $costs = [0, 0, 0, 0];
        $count = $this->maxUnits($selected['nr'], $selected['isGreat']);
        for ($i = 0; $i < 4; ++$i) {
            $costs[$i] = $selected['costs'][$i] * $count;
        }
        return [
            'nr'            => $selected['nr'],
            'costs'         => $costs,
            'count'         => $count,
            'building'      => $building,
            'training_time' => $training_time
        ];
    }

    private function isResourcesAvailable($costs)
    {
        for ($i = 0; $i < 4; ++$i) {
            if ($this->resources[$i] < $costs[$i]) {
                return FALSE;
            }
        }
        return TRUE;
    }

    private function maxUnits($nr, $great = false)
    {
        $cost = Formulas::uTrainingCost(nrToUnitId($nr, $this->user['race']), $great);
        $can = [];
        foreach ($this->resources as $r => $v) {
            if ($cost[$r] == 0) {
                logError('Division by zero (maxUnits) AI for race ' . $this->user['race']);
                return 0;
            }
            $can[$r] = floor($v / $cost[$r]);
        }
        return min($can);
    }

    private function takeResources($resources)
    {
        for ($i = 0; $i < 4; ++$i) {
            $this->resources[$i] = max($this->resources[$i] - $resources[$i], 0);
        }
        DB::getInstance()->query("UPDATE vdata SET wood=IF(wood-{$resources[0]} > 0, wood-{$resources[0]}, 0), clay=IF(clay-{$resources[1]} > 0, clay-{$resources[1]}, 0), iron=IF(iron-{$resources[2]} > 0, iron-{$resources[2]}, 0), crop=IF(crop-{$resources[3]} > 0, crop-{$resources[3]}, 0) WHERE kid={$this->village['kid']}");
    }

    public function upgradeBuilding($count = 1)
    {
        $isNpc = ($this->user['access'] ?? 0) == 3;
        $owner = $this->village['owner'] ?? 0;
        
        $effects = 0;
        for ($i = 1; $i <= $count; ++$i) {
            if (method_exists($this->aiBuilder, 'upgrade')) {
                $result = $this->aiBuilder->upgrade();
                if ($result > 0) {
                    $effects += $result;
                } else if ($isNpc && $i == 1) {
                    // Only log first failure to avoid spam
                    \Core\AI\NpcLogger::log($owner, 'BUILD_FAIL', 'AI Builder upgrade returned 0', [
                        'resources' => $this->resources,
                        'aiBuilder_exists' => true
                    ]);
                }
            } else if ($isNpc && $i == 1) {
                \Core\AI\NpcLogger::log($owner, 'BUILD_FAIL', 'AI Builder has no upgrade method', []);
            }
        }
        return $effects;
    }

    public function researchUnit($kid)
    {
        if (!$this->training_buildings['academyLevel']) return false;
        $arr = [];
        for ($i = 2; $i <= 8; ++$i) {
            if ($this->researches['u' . $i]) continue;
            $unitId = nrToUnitId($i, $this->user['race']);
            $neededResources = Formulas::uResearchCost($unitId);
            if (!$this->isResourcesAvailable($neededResources)) continue;
            if (!$this->_canDoResearch(Formulas::uResearchPreRequests(Session::getInstance()->getRace(),
                $unitId))) continue;
            $arr[] = ['nr' => $i, 'costs' => $neededResources, 'duration' => Formulas::uResearchTime($unitId)];
        }
        if (!sizeof($arr)) {
            return false;
        }
        shuffle($arr);
        $selected = $arr[mt_rand(0, sizeof($arr) - 1)];
        $this->takeResources($selected['costs']);
        $db = DB::getInstance();
        $db->query("INSERT INTO research (`kid`, `mode`, `nr`, `end_time`) VALUES ($kid, 1, " . $selected['nr'] . ", " . (time() + $selected['duration']) . ")");
        return true;
    }

    private function _canDoResearch($breq)
    {
        foreach ($breq as $bid => $level) {
            if (max(self::getTypeLevel($bid)) < $level) {
                return FALSE;
            }
        }
        return TRUE;
    }

    private function getTypeLevel($gid)
    {
        $buildingsAssoc = $this->buildings;
        if ($gid == 16) {
            return [$buildingsAssoc[39]['level']];
        }
        $lvl = [];
        if ($gid <= 4) {
            for ($i = 1; $i <= 18; ++$i) {
                if ($buildingsAssoc[$i]['item_id'] == $gid) {
                    $lvl[] = $buildingsAssoc[$i]['level'];
                }
            }
            if (!sizeof($lvl)) {
                $lvl[] = 0;
            }
            return $lvl;
        }
        $multi = FALSE;
        if (isset(Formulas::$data['buildings'][$gid - 1]['req']) && isset(Formulas::$data['buildings'][$gid - 1]['req']['multi'])) {
            $multi = Formulas::$data['buildings'][$gid - 1]['req']['multi'] == 'true';
        }
        for ($i = 19; $i <= 40; ++$i) {
            if ($buildingsAssoc[$i]['item_id'] == $gid) {
                $lvl[] = $buildingsAssoc[$i]['level'];
                if (!$multi) {
                    break;
                }
            }
        }
        if (!sizeof($lvl)) {
            $lvl[] = 0;
        }
        return $lvl;
    }

    public function upgradeUnit($kid)
    {
        if ($this->training_buildings['smithyLevel'] <= 0) return false;
        $maxResearchCount = $this->user['plus'] > time() ? 2 : 1;
        if ($this->smithyUpgradesCount >= $maxResearchCount) return false;
        $researchUP = -1;
        $commence = time();
        $db = DB::getInstance();
        if ($this->smithyUpgradesCount == 1) {
            $lastUpgrade = $db->query("SELECT nr, end_time FROM research WHERE mode=0 AND kid=$kid")->fetch_assoc();
            $researchUP = $lastUpgrade['nr'];
            $commence = $lastUpgrade['end_time'];
        }
        $researches = $db->query("SELECT * FROM tdata WHERE kid=$kid")->fetch_assoc();
        $upgrades = $db->query("SELECT * FROM smithy WHERE kid=$kid")->fetch_assoc();
        $arr = [];
        for ($i = 1; $i <= 8; ++$i) {
            if ($i > 1 && !$researches['u' . $i] && !self::SKIP_RESEARCH) continue;
            $level = $upgrades['u' . $i] + ($researchUP == $i ? 1 : 0);
            if ($level >= 20) continue;
            $unitId = nrToUnitId($i, $this->user['race']);
            $neededResources = Formulas::uUpgradeCost($unitId, $level + 1);
            if (!$this->isResourcesAvailable($neededResources)) continue;
            $arr[] = [
                'nr'       => $i,
                'costs'    => $neededResources,
                'duration' => Formulas::uUpgradeTime($unitId,
                    $level + 1,
                    $this->training_buildings['smithyLevel'])
            ];
        }
        if (!sizeof($arr)) {
            return false;
        }
        shuffle($arr);
        $selected = $arr[mt_rand(0, sizeof($arr) - 1)];
        $this->takeResources($selected['costs']);
        $this->smithyUpgradesCount++;
        $db->query("INSERT INTO research (`kid`, `mode`, `nr`, `end_time`) VALUES ($kid, 0, " . $selected['nr'] . ", " . ($commence + $selected['duration']) . ")");
        return true;
    }
}

class AI
{
    public static function doSomethingRandom($kid, $count = 1)
    {
        $seconds_past = getGameElapsedSeconds();
        $db = DB::getInstance();
        
        // Check if this is an NPC
        $owner = $db->fetchScalar("SELECT owner FROM vdata WHERE kid=$kid");
        $access = $db->fetchScalar("SELECT access FROM users WHERE id=$owner");
        $isNpc = ($access == 3);
        
        // Log cycle start for NPCs
        if ($isNpc) {
            \Core\AI\NpcLogger::logCycleStart($owner, $count);
        }
        
        $ai = new AI_MAIN($kid);
        
        for ($i = 1; $i <= $count; ++$i) {
            // NPC decision loop - DETERMINISTIC build/train only
            // Raids and alliances are handled separately by interval-based checks in FakeUserModel
            if ($isNpc) {
                $roll = mt_rand(1, 100);
                
                if ($roll <= 60) {
                    // 60% chance: Building upgrade (increased from 40%)
                    $result = $ai->upgradeBuilding();
                    if ($result) {
                        // Get the building that was just queued
                        $lastBuild = DB::getInstance()->query("SELECT f.id, f.name, f.level 
                                                               FROM fdata f 
                                                               WHERE f.vref = $kid 
                                                               ORDER BY f.id DESC 
                                                               LIMIT 1")->fetch_assoc();
                        if ($lastBuild) {
                            \Core\AI\NpcLogger::log($owner, 'BUILD', "Upgrading {$lastBuild['name']} to level " . ($lastBuild['level'] + 1), [
                                'building' => $lastBuild['name'],
                                'current_level' => $lastBuild['level'],
                                'target_level' => $lastBuild['level'] + 1,
                                'field_id' => $lastBuild['id']
                            ]);
                        } else {
                            \Core\AI\NpcLogger::log($owner, 'BUILD', 'Building upgrade queued', ['success' => true]);
                        }
                    } else {
                        \Core\AI\NpcLogger::log($owner, 'BUILD_SKIP', 'No buildings can be upgraded (check BUILD_FAIL logs)', [
                            'iteration' => $i
                        ]);
                    }
                } else {
                    // 40% chance: Train units (increased from 30%)
                    $result = $ai->trainUnits();
                    if ($result) {
                        // Get the most recent training
                        $lastTrain = DB::getInstance()->query("SELECT u1, u2, u3, u4, u5, u6, u7, u8, u9, u10, u11 
                                                               FROM training 
                                                               WHERE vref = $kid 
                                                               ORDER BY id DESC 
                                                               LIMIT 1")->fetch_assoc();
                        if ($lastTrain) {
                            $trainedUnits = [];
                            $totalUnits = 0;
                            for ($u = 1; $u <= 11; $u++) {
                                if ($lastTrain["u$u"] > 0) {
                                    $trainedUnits["unit_$u"] = $lastTrain["u$u"];
                                    $totalUnits += $lastTrain["u$u"];
                                }
                            }
                            
                            if ($totalUnits > 0) {
                                \Core\AI\NpcLogger::log($owner, 'TRAIN', "Training $totalUnits units", [
                                    'total' => $totalUnits,
                                    'units' => $trainedUnits
                                ]);
                            } else {
                                \Core\AI\NpcLogger::log($owner, 'TRAIN', 'Units training started', ['success' => true]);
                            }
                        } else {
                            \Core\AI\NpcLogger::log($owner, 'TRAIN', 'Units training started', ['success' => true]);
                        }
                    } else {
                        \Core\AI\NpcLogger::log($owner, 'TRAIN_SKIP', 'No units can be trained (check TRAIN_FAIL logs)', [
                            'iteration' => $i
                        ]);
                    }
                }
            } else {
                // Original behavior for non-NPCs
                if (getGameSpeed() <= 10 && $seconds_past <= 1.5 * 86400) {
                    $rnd = mt_rand(1, 5);
                    if ($rnd <= 3) {
                        $ai->upgradeBuilding();
                    } else {
                        if (!$ai->upgradeBuilding()) {
                            $ai->upgradeBuilding();
                        }
                    }
                } else {
                    if ($ai->upgradeBuilding()) {
                        $ai->trainUnits();
                    }
                }
            }
        }
    }
}