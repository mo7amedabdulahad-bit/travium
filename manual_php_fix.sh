#!/bin/bash

# MANUAL PHP INI FIX - Direct file editing to fix corruption

echo "=== CRITICAL FIX: Manually Repairing PHP INI ==="

# Backup the corrupted file
sudo cp /etc/php/8.4/cli/php.ini /etc/php/8.4/cli/php.ini.corrupted.bak
sudo cp /etc/php/8.4/fpm/php.ini /etc/php/8.4/fpm/php.ini.corrupted.bak

echo "✅ Backups created"

# Find the corrupted imagick line and remove it
echo "Removing corrupted imagick.sodate.timezone lines..."
sudo sed -i '/imagick\.sodate\.timezone/d' /etc/php/8.4/cli/php.ini
sudo sed -i '/imagick\.sodate\.timezone/d' /etc/php/8.4/fpm/php.ini

# Remove ALL date.timezone lines (clean slate)
echo "Removing all date.timezone lines..."
sudo sed -i '/^date\.timezone/d' /etc/php/8.4/cli/php.ini
sudo sed -i '/^date\.timezone/d' /etc/php/8.4/fpm/php.ini
sudo sed -i '/^; Timezone/d' /etc/php/8.4/cli/php.ini
sudo sed -i '/^; Timezone/d' /etc/php/8.4/fpm/php.ini

# Add timezone at END of file (safest method)
echo "Adding timezone setting..."
printf '\n[Date]\ndate.timezone = Asia/Dubai\n' | sudo tee -a /etc/php/8.4/cli/php.ini > /dev/null
printf '\n[Date]\ndate.timezone = Asia/Dubai\n' | sudo tee -a /etc/php/8.4/fpm/php.ini > /dev/null

echo "✅ PHP INI repaired"

# Test PHP works
echo ""
echo "=== Testing PHP ==="
php8.4 -i | grep "date.timezone" | head -3

if [ $? -eq 0 ]; then
    echo "✅ PHP works!"
else
    echo "❌ PHP still broken - manual intervention needed"
    exit 1
fi

# Restart services
echo ""
echo "=== Restarting Services ==="
sudo systemctl restart php8.4-fpm
sudo systemctl stop travium@s2.service
sleep 2
sudo pkill -9 -f engine.php
sleep 1
sudo systemctl start travium@s2.service

echo "✅ Services restarted"

# Reset NPCs
echo ""
echo "=== Resetting NPCs ==="
mysql -u travium1 -p9663264507 travium1 -e "UPDATE users SET next_tick_at = NOW() WHERE access=3;" 2>/dev/null

echo ""
echo "✅ COMPLETE! Monitor for continuous processing:"
echo "   tail -f /home/travium/htdocs/servers/s2/include/error_log.log | grep -E 'NpcScheduler: Processed'"
