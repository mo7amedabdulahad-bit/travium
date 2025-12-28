#!/bin/bash

# NPC System Test Runner
# Runs all tests in sequence and shows results

echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo "║                    NPC SYSTEM TEST SUITE                                   ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""

# Change to Travium directory
cd "$(dirname "$0")"

echo "Step 1: Checking database schema..."
php -r "
require_once 'src/bootstrap.php';
\$db = Core\Database\DB::getInstance();
\$result = \$db->query(\"SHOW COLUMNS FROM users LIKE 'npc_%'\");
if (\$result && \$result->num_rows >= 4) {
    echo \"✅ NPC columns exist\\n\";
} else {
    echo \"❌ NPC columns missing! Run migrations first!\\n\";
    exit(1);
}
"

if [ $? -ne 0 ]; then
    echo ""
    echo "ERROR: Database migration not applied!"
    echo "Run: mysql -u maindb -pmaindb maindb < migrations/002_add_npc_columns.sql"
    exit 1
fi

echo ""
echo "Step 2: Updating existing fake users..."
php update_existing_fake_users.php

echo ""
read -p "Press Enter to continue with tests..."

echo ""
echo "Step 3: Testing NPC Configuration System..."
php test_npc_config.php

echo ""
read -p "Press Enter to continue..."

echo ""
echo "Step 4: Testing Personality AI..."
php test_personality_ai.php

echo ""
read -p "Press Enter to continue..."

echo ""
echo "Step 5: Testing Raid AI..."
php test_raid_ai.php

echo ""
read -p "Press Enter for comprehensive debug report..."

echo ""
echo "Step 6: Running comprehensive debugger..."
php debug_npc_system.php

echo ""
echo "╔════════════════════════════════════════════════════════════════════════════╗"
echo "║                    TEST SUITE COMPLETE                                     ║"
echo "╚════════════════════════════════════════════════════════════════════════════╝"
echo ""
echo "All tests complete! Review the output above for any issues."
echo ""
