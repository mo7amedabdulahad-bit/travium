#!/bin/bash

# Comprehensive diagnostic script for NPC scheduler issues

echo "=== MySQL Timezone Diagnostic ==="
echo "1. Current MySQL timezone:"
mysql -u travium1 -p9663264507 -e "SELECT @@global.time_zone, @@session.time_zone, NOW() as db_time;" 2>/dev/null

echo ""
echo "2. System time:"
date "+%Y-%m-%d %H:%M:%S"

echo ""
echo "3. System timezone:"
cat /etc/timezone 2>/dev/null || timedatectl | grep "Time zone"

echo ""
echo "4. Check if MariaDB config was updated:"
grep -i "time-zone" /etc/mysql/mariadb.conf.d/50-server.cnf 2>/dev/null || echo "NOT FOUND in config"

echo ""
echo "5. Check all MariaDB config files:"
grep -r "time-zone" /etc/mysql/ 2>/dev/null || echo "NOT FOUND anywhere"

echo ""
echo "=== NPC Scheduler Diagnostic ==="
echo "6. Count NPCs and their next_tick times:"
mysql -u travium1 -p9663264507 travium1 -e "
SELECT 
    COUNT(*) as total_npcs,
    SUM(CASE WHEN next_tick_at <= NOW() THEN 1 ELSE 0 END) as due_now,
    MIN(next_tick_at) as earliest_tick,
    MAX(next_tick_at) as latest_tick,
    NOW() as current_db_time
FROM users WHERE access=3;
" 2>/dev/null

echo ""
echo "7. Last 10 log entries (non-debug):"
tail -20 /home/travium/htdocs/servers/s2/include/error_log.log | grep -v DEBUG | tail -10

echo ""
echo "8. Check if automation is still running:"
ps aux | grep engine.php | grep -v grep | wc -l
echo "(Should be 8-9 processes)"

echo ""
echo "=== Recommended Fixes ==="
echo "If timezone is wrong, run:"
echo "  sudo sed -i '/\[mysqld\]/a default_time_zone=\"+04:00\"' /etc/mysql/mariadb.conf.d/50-server.cnf"
echo "  sudo systemctl restart mariadb"
