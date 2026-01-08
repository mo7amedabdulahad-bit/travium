#!/bin/bash

# Comprehensive diagnostic for stuck automation

echo "=== STUCK PROCESS DIAGNOSTIC ==="

echo "1. Memory usage of engine processes:"
ps aux | grep engine.php | grep -v grep | awk '{print "PID " $2 ": " $6/1024 " MB, CPU: " $3 "%"}'

echo ""
echo "2. Check for zombie/defunct processes:"
ps aux | grep 'defunct\|Z' | grep -v grep

echo ""
echo "3. Open file descriptors:"
lsof -u travium 2>/dev/null | wc -l
echo "(Should be < 1000)"

echo ""
echo "4. Database connections:"
mysql -u travium1 -p9663264507 -e "SHOW PROCESSLIST;" 2>/dev/null | wc -l
echo "(Active MySQL connections)"

echo ""
echo "5. Check last log timestamp:"
tail -1 /home/travium/htdocs/servers/s2/include/error_log.log

echo ""
echo "6. System memory:"
free -h

echo ""
echo "7. CPU load:"
uptime

echo ""
echo "=== RECOMMENDED ACTIONS ==="
echo "If processes show:"
echo "  - High memory (>100MB each): Memory leak"
echo "  - High CPU (>20%) but no logs: Infinite loop"
echo "  - Many file descriptors: FD leak"
echo ""
echo "Run: sudo systemctl restart travium@s2.service"
echo "Then monitor for 15 minutes"
