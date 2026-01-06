#!/bin/bash

# Diagnostic script to check NPC scheduler state
# Run this on the server to see what's happening

echo "=== NPC Tick Status ==="
mysql -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb -e "
SELECT 
    COUNT(*) as total_npcs,
    SUM(CASE WHEN next_tick_at <= NOW() THEN 1 ELSE 0 END) as due_now,
    SUM(CASE WHEN next_tick_at > NOW() THEN 1 ELSE 0 END) as scheduled_future,
    MIN(next_tick_at) as earliest_tick,
    MAX(next_tick_at) as latest_tick,
    NOW() as current_time
FROM users 
WHERE access = 3;
"

echo ""
echo "=== Next 10 NPCs Due ==="
mysql -u maindb -p7akPHoCSv6We@EVHMtsyNkc6 maindb -e "
SELECT id, name, next_tick_at, tick_interval_seconds,
    TIMESTAMPDIFF(SECOND, NOW(), next_tick_at) as seconds_until_due
FROM users 
WHERE access = 3 
ORDER BY next_tick_at ASC 
LIMIT 10;
"

echo ""
echo "=== Automation Process Status ==="
ps aux | grep engine.php | grep -v grep | head -5

echo ""
echo "=== Recent Log Entries ==="
tail -20 /home/travium/htdocs/servers/s1/include/error_log.log
