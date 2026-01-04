#!/bin/bash
# Quick diagnostic for WW event processing

echo "=== Checking WW Event Status ==="
echo ""

echo "1. Events in table:"
mariadb -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb -e "
SELECT id, event_type, created_at, processed_at 
FROM npc_world_events 
WHERE event_type = 'WWPlanReleased'
ORDER BY id DESC LIMIT 5;"

echo ""
echo "2. Current NPC WW states:"
mariadb -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb -e "
SELECT 
    ww_alliance_role,
    ww_operation_state,
    COUNT(*) as count
FROM users 
WHERE access = 3
GROUP BY ww_alliance_role, ww_operation_state;"

echo ""
echo "3. NPCs with alliances:"
mariadb -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb -e "
SELECT COUNT(*) as npc_count
FROM users
WHERE access = 3 AND aid > 0;"

echo ""
echo "4. Manual test - calling onPlansReleased directly:"
cd /home/travium/htdocs
php << 'PHPCODE'
<?php
define('ROOT_PATH', __DIR__ . '/');
define('GLOBAL_CONFIG_FILE', ROOT_PATH . 'config.php');
define('CONNECTION_FILE', ROOT_PATH . 'servers/s1/include/connection.php');

require ROOT_PATH . 'src/bootstrap.php';

echo "Calling NpcWWOperations::onPlansReleased(1)...\n";
\Core\NpcWWOperations::onPlansReleased(1);
echo "Done!\n";
?>
PHPCODE

echo ""
echo "5. Check results after direct call:"
mariadb -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb -e "
SELECT 
    ww_alliance_role,
    ww_operation_state,
    COUNT(*) as count
FROM users 
WHERE access = 3
GROUP BY ww_alliance_role, ww_operation_state;"
