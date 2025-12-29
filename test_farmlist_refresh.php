#!/usr/bin/env php
<?php
require_once __DIR__ . '/servers/s1/include/env.php';
require_once SRC_PATH_PROD . '/bootstrap.php';

echo "===== Manual Farm-List Refresh Test =====\n\n";

$farmListModel = new \Model\FarmListModel();

echo "Running refreshNpcFarmLists()...\n";
$farmListModel->refreshNpcFarmLists();

echo "\n✓ Complete! Check npc_activity.log for details.\n";
echo "\n===== Done =====\n";
