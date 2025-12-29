#!/bin/bash
# Emergency fix for corrupted AI.php on server

echo "Fixing corrupted for loop in AI.php..."

# Backup first
cp /home/travium/htdocs/src/Core/AI.php /home/travium/htdocs/src/Core/AI.php.backup

# Find and fix the corrupted line - sed command to replace the broken line
sed -i '495s/.*/        for ($i = 1; $i <= $count; ++$i) {/' /home/travium/htdocs/src/Core/AI.php

echo "Fixed! Verifying..."
sed -n '495p' /home/travium/htdocs/src/Core/AI.php

echo ""
echo "Now restart service:"
echo "sudo systemctl restart travium@s1.service"
