#!/bin/bash
# WW Plan Test Script - Simplified version
# Tests WWPlanReleased event without needing alliance table lookups

echo "========================================="
echo "World Wonder Plan Release Test"
echo "========================================="
echo ""

# 1. Insert WWPlanReleased event
echo "Step 1: Triggering WW Plan Release Event..."
mariadb -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb << EOF
INSERT INTO npc_world_events (server_id, event_type, created_at) 
VALUES (1, 'WWPlanReleased', NOW());
EOF

if [ $? -eq 0 ]; then
    echo "✅ Event created successfully"
else
    echo "❌ Failed to create event"
    exit 1
fi

echo ""
echo "Step 2: Showing NPC counts by alliance BEFORE processing..."
mariadb -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb << EOF
SELECT 
    u.aid as AllianceID,
    COUNT(u.id) as NPCCount,
    SUM(v.pop) as TotalPop
FROM users u
LEFT JOIN vdata v ON u.id = v.owner
WHERE u.access = 3 AND u.aid > 0
GROUP BY u.aid
ORDER BY TotalPop DESC
LIMIT 10;
EOF

echo ""
echo "Step 3: Processing the event (running NPC scheduler)..."
cd /home/travium/htdocs

# Use proper PHP script that defines constants
php << 'PHPCODE'
<?php
define('ROOT_PATH', __DIR__ . '/');
define('GLOBAL_CONFIG_FILE', ROOT_PATH . 'config.php');
define('CONNECTION_FILE', ROOT_PATH . 'servers/s1/include/connection.php');

require ROOT_PATH . 'src/bootstrap.php';

echo "Running NPC Scheduler...\n";
\Core\NpcScheduler::processDueNpcs(1, 50);
echo "✅ Scheduler processed\n";
?>
PHPCODE

echo ""
echo "Step 4: Showing WW roles AFTER processing..."
mariadb -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb << EOF
SELECT 
    u.aid as AllianceID,
    u.ww_alliance_role as Role,
    u.ww_operation_state as State,
    COUNT(*) as NPCCount
FROM users u
WHERE u.access = 3 AND u.ww_alliance_role != 'Neutral'
GROUP BY u.aid, u.ww_alliance_role, u.ww_operation_state
ORDER BY u.ww_alliance_role, u.aid;
EOF

echo ""
echo "Step 5: Detailed NPC Status (first 20)..."
mariadb -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb << EOF
SELECT 
    u.id as NPC_ID,
    u.name as Name,
    u.aid as AllianceID,
    u.ww_alliance_role as WWRole,
    u.ww_operation_state as State
FROM users u
WHERE u.access = 3 
  AND u.ww_alliance_role != 'Neutral'
ORDER BY u.ww_alliance_role, u.aid
LIMIT 20;
EOF

echo ""
echo "========================================="
echo "Test Complete!"
echo "========================================="
echo ""
echo "Expected Results:"
echo "  - Top alliance by population = Contender role"
echo "  - All other alliances = Spoiler role"
echo "  - All NPCs should be in 'PlanHunting' state"
echo ""
