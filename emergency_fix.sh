#!/bin/bash

# Emergency fix for corrupted PHP ini and comprehensive NPC scheduler diagnostics

echo "=== Step 1: Fix Corrupted PHP INI ==="

# Find and remove the broken line
sudo sed -i '/date.timezone = "Asia\/Dubai"imagick.so/d' /etc/php/8.4/cli/php.ini
sudo sed -i '/date.timezone = "Asia\/Dubai"imagick.so/d' /etc/php/8.4/fpm/php.ini

# Remove any duplicate timezone entries
sudo sed -i '/^date.timezone =/d' /etc/php/8.4/cli/php.ini
sudo sed -i '/^date.timezone =/d' /etc/php/8.4/fpm/php.ini

# Add timezone correctly (at end of file, not inline)
echo "" | sudo tee -a /etc/php/8.4/cli/php.ini
echo "; Timezone for Dubai" | sudo tee -a /etc/php/8.4/cli/php.ini
echo "date.timezone = Asia/Dubai" | sudo tee -a /etc/php/8.4/cli/php.ini

echo "" | sudo tee -a /etc/php/8.4/fpm/php.ini
echo "; Timezone for Dubai" | sudo tee -a /etc/php/8.4/fpm/php.ini
echo "date.timezone = Asia/Dubai" | sudo tee -a /etc/php/8.4/fpm/php.ini

echo "✅ PHP INI files fixed"

echo ""
echo "=== Step 2: Verify PHP Works ==="
php8.4 -r "echo 'PHP OK: ' . date_default_timezone_get() . PHP_EOL;"

echo ""
echo "=== Step 3: Check Automation Processes ==="
echo "Running engine processes:"
ps aux | grep engine.php | grep -v grep | wc -l
echo "(Should be 9)"

echo ""
echo "=== Step 4: Check for Zombie/Stuck Processes ==="
ps aux | grep engine.php | grep -v grep

echo ""
echo "=== Step 5: Full Restart ==="
sudo systemctl restart php8.4-fpm
sudo systemctl stop travium@s2.service
sleep 2
sudo pkill -9 -f engine.php
sleep 1
sudo systemctl start travium@s2.service

echo ""
echo "=== Step 6: Reset All NPCs to Process NOW ==="
mysql -u travium1 -p9663264507 travium1 -e "UPDATE users SET next_tick_at = NOW() WHERE access=3;"

echo ""
echo "✅ Complete! Now monitor logs for 5 minutes:"
echo "   tail -f /home/travium/htdocs/servers/s2/include/error_log.log | grep -E 'DEBUG.*NpcScheduler|NpcScheduler:'"
