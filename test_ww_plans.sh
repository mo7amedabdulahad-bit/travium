#!/bin/bash
# WW Plan Test Script
# This will test the WWPlanReleased event and show alliance role assignments

echo "========================================="
echo "World Wonder Plan Release Test"
echo "========================================="
echo ""

# 1. Insert WWPlanReleased event
echo "Step 1: Triggering WW Plan Release Event..."
mysql -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb << EOF
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
echo "Step 2: Showing NPC alliances BEFORE processing..."
mysql -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb << EOF
SELECT 
    a.id as AllianceID,
    a.name as AllianceName,
    COUNT(u.id) as NPCCount,
    SUM(v.pop) as TotalPop
FROM alianz a
JOIN users u ON a.id = u.aid
LEFT JOIN vdata v ON u.id = v.owner
WHERE u.access = 3
GROUP BY a.id, a.name
ORDER BY TotalPop DESC
LIMIT 10;
EOF

echo ""
echo "Step 3: Processing the event (running NPC scheduler)..."
cd /home/travium/htdocs
php -r "
require 'src/bootstrap.php';
\Core\NpcScheduler::processDueNpcs(1, 50);
echo \"Scheduler processed\n\";
"

echo ""
echo "Step 4: Showing alliance roles AFTER processing..."
mysql -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb << EOF
SELECT 
    a.name as Alliance,
    u.ww_alliance_role as Role,
    u.ww_operation_state as State,
    COUNT(*) as NPCCount
FROM users u
JOIN alianz a ON u.aid = a.id
WHERE u.access = 3 AND u.ww_alliance_role != 'Neutral'
GROUP BY a.name, u.ww_alliance_role, u.ww_operation_state
ORDER BY u.ww_alliance_role, a.name;
EOF

echo ""
echo "Step 5: Detailed NPC Status..."
mysql -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb << EOF
SELECT 
    u.id as NPC_ID,
    u.name as Name,
    a.name as Alliance,
    u.ww_alliance_role as WWRole,
    u.ww_operation_state as State
FROM users u
JOIN alianz a ON u.aid = a.id
WHERE u.access = 3 
  AND u.ww_alliance_role != 'Neutral'
ORDER BY u.ww_alliance_role, a.name
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
