#!/bin/bash

# Comprehensive fix for timezone and NPC scheduling issues

echo "=== Fix 1: Set PHP Default Timezone ==="
PHP_INI="/etc/php/8.4/cli/php.ini"
PHP_FPM_INI="/etc/php/8.4/fpm/php.ini"

# Update CLI php.ini
if grep -q ";date.timezone" "$PHP_INI"; then
    sudo sed -i 's/;date.timezone.*/date.timezone = "Asia\/Dubai"/' "$PHP_INI"
    echo "✅ Updated CLI php.ini"
else
    echo "date.timezone = \"Asia/Dubai\"" | sudo tee -a "$PHP_INI"
    echo "✅ Added timezone to CLI php.ini"
fi

# Update FPM php.ini
if grep -q ";date.timezone" "$PHP_FPM_INI"; then
   sudo sed -i 's/;date.timezone.*/date.timezone = "Asia\/Dubai"/' "$PHP_FPM_INI"
    echo "✅ Updated FPM php.ini"
else
    echo "date.timezone = \"Asia/Dubai\"" | sudo tee -a "$PHP_FPM_INI"
    echo "✅ Added timezone to FPM php.ini"
fi

echo ""
echo "=== Fix 2: Restart Services ==="
sudo systemctl restart php8.4-fpm
sudo systemctl restart travium@s2.service

echo ""
echo "=== Fix 3: Verify Timezone ==="
echo "PHP CLI timezone:"
php8.4 -r "echo date_default_timezone_get() . PHP_EOL;"

echo ""
echo "MySQL timezone:"
mysql -u travium1 -p9663264507 -e "SELECT @@global.time_zone;"

echo ""
echo "System time:"
date "+%Y-%m-%d %H:%M:%S %Z"

echo ""
echo "✅ All fixes applied! Logs should now show correct time."
