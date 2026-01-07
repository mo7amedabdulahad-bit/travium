#!/bin/bash

# Fix MySQL Timezone Mismatch
# This script ensures MySQL uses the same timezone as the system

echo "=== Fixing MySQL Timezone ==="

# Get system timezone
SYSTEM_TZ=$(timedatectl | grep "Time zone" | awk '{print $3}')
echo "System timezone: $SYSTEM_TZ"

# Get current MySQL timezone
MYSQL_TZ=$(mysql -u travium1 -p9663264507 -e "SELECT @@global.time_zone;" -s -N 2>/dev/null || echo "SYSTEM")
echo "Current MySQL timezone: $MYSQL_TZ"

# Fix MySQL configuration
echo "Updating /etc/mysql/mariadb.conf.d/50-server.cnf..."
sudo bash -c 'cat >> /etc/mysql/mariadb.conf.d/50-server.cnf << EOF

# Fix timezone to match system
[mysqld]
default-time-zone = "+04:00"
EOF'

# Restart MariaDB
echo "Restarting MariaDB..."
sudo systemctl restart mariadb

# Verify fix
echo "Verifying timezone..."
NEW_TZ=$(mysql -u travium1 -p9663264507 -e "SELECT NOW();" -s -N)
SYS_TIME=$(date "+%Y-%m-%d %H:%M:%S")
echo "MySQL NOW(): $NEW_TZ"
echo "System time: $SYS_TIME"

echo ""
echo "✅ MySQL timezone fixed! Times should now match."
