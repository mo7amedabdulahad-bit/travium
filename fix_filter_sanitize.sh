#!/bin/bash

# Mass replace FILTER_SANITIZE_STRING with FILTER_SANITIZE_FULL_SPECIAL_CHARS
# PHP 8.1+ deprecated FILTER_SANITIZE_STRING

cd /home/travium/htdocs

echo "Replacing FILTER_SANITIZE_STRING in all PHP files..."

# Use sed to replace in all .php files
find src -name "*.php" -type f -exec sed -i 's/FILTER_SANITIZE_STRING/FILTER_SANITIZE_FULL_SPECIAL_CHARS/g' {} +

echo "✅ Done! All instances replaced."
echo ""
echo "Files changed:"
git status --short | grep "\.php$" | wc -l
echo ""
echo "Now commit and push:"
echo "git add -A"
echo "git commit -m 'FIX: Replace all FILTER_SANITIZE_STRING with FILTER_SANITIZE_FULL_SPECIAL_CHARS for PHP 8.4'"
echo "git push origin main"
