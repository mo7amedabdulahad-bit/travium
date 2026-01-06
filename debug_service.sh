#!/bin/bash

# Debug script for Travium service startup failure
echo "=== Checking systemd journal for actual error ==="
sudo journalctl -u travium@s1.service -n 50 --no-pager

echo ""
echo "=== Trying to run engine.php directly to see error ==="
cd /home/travium/htdocs/servers/s1/include
sudo -u travium /usr/bin/php8.4 engine.php 2>&1 | head -50

echo ""
echo "=== Checking if servers/s1 directory exists ==="
ls -la /home/travium/htdocs/servers/

echo ""
echo "=== Checking config files ==="
ls -la /home/travium/htdocs/servers/s1/include/ 2>&1 || echo "s1 directory doesn't exist!"
