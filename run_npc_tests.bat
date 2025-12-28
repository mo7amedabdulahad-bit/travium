@echo off
REM NPC System Test Runner for Windows
REM Runs all tests in sequence and shows results

echo =========================================================================
echo                    NPC SYSTEM TEST SUITE
echo =========================================================================
echo.

cd /d "%~dp0"

echo Step 1: Checking database schema...
php -r "require_once 'src/bootstrap.php'; $db = Core\Database\DB::getInstance(); $result = $db->query('SHOW COLUMNS FROM users LIKE \"npc_%%\"'); if ($result && $result->num_rows >= 4) { echo \"OK NPC columns exist\n\"; } else { echo \"ERROR NPC columns missing!\n\"; exit(1); }"

if errorlevel 1 (
    echo.
    echo ERROR: Database migration not applied!
    echo Run: mysql -u maindb -pmaindb maindb ^< migrations/002_add_npc_columns.sql
    pause
    exit /b 1
)

echo.
echo Step 2: Updating existing fake users...
php update_existing_fake_users.php

echo.
pause

echo.
echo Step 3: Testing NPC Configuration System...
php test_npc_config.php

echo.
pause

echo.
echo Step 4: Testing Personality AI...
php test_personality_ai.php

echo.
pause

echo.
echo Step 5: Testing Raid AI...
php test_raid_ai.php

echo.
pause

echo.
echo Step 6: Running comprehensive debugger...
php debug_npc_system.php

echo.
echo =========================================================================
echo                    TEST SUITE COMPLETE
echo =========================================================================
echo.
echo All tests complete! Review the output above for any issues.
echo.
pause
