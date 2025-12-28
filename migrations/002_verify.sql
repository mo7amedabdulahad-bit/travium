-- Verification Script for NPC Migration 002
-- Run this after applying 002_add_npc_columns.sql
-- Purpose: Verify all NPC columns were added correctly

-- 1. Check column existence
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'users'
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME IN ('npc_personality', 'npc_difficulty', 'npc_info', 'last_npc_action', 'goldclub')
ORDER BY ORDINAL_POSITION;

-- Expected output: 5 rows showing all NPC columns

-- 2. Check indexes
SELECT 
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as COLUMNS
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_NAME = 'users'
AND TABLE_SCHEMA = DATABASE()
AND INDEX_NAME IN ('npc_processing', 'npc_personality', 'npc_difficulty')
GROUP BY INDEX_NAME
ORDER BY INDEX_NAME;

-- Expected output: 3 indexes

-- 3. Verify fake users have personalities assigned
SELECT 
    COUNT(*) as total_fake_users,
    SUM(CASE WHEN npc_personality IS NOT NULL THEN 1 ELSE 0 END) as with_personality,
    SUM(CASE WHEN npc_difficulty IS NOT NULL THEN 1 ELSE 0 END) as with_difficulty,
    SUM(CASE WHEN npc_info IS NOT NULL THEN 1 ELSE 0 END) as with_info
FROM users
WHERE access = 3;

-- Expected: All counts should be equal (all fake users have personalities)

-- 4. Sample fake users with NPC data
SELECT 
    id,
    name,
    access,
    npc_personality,
    npc_difficulty,
    goldclub,
    JSON_EXTRACT(npc_info, '$.raids_sent') as raids_sent,
    JSON_EXTRACT(npc_info, '$.version') as npc_version
FROM users
WHERE access = 3
LIMIT 10;

-- Expected: 10 rows with personalities, difficulties, and JSON data

-- 5. Distribution of personalities
SELECT 
    npc_personality,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM users WHERE access=3), 2) as percentage
FROM users
WHERE access = 3
GROUP BY npc_personality
ORDER BY count DESC;

-- Expected: Roughly even distribution across 5 personalities

-- 6. Distribution of difficulties
SELECT 
    npc_difficulty,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM users WHERE access=3), 2) as percentage
FROM users
WHERE access = 3
GROUP BY npc_difficulty
ORDER BY count DESC;

-- Expected: Roughly even distribution across 4 difficulty levels

-- 7. Verify non-fake users are not affected
SELECT 
    COUNT(*) as non_fake_users,
    SUM(CASE WHEN npc_personality IS NOT NULL THEN 1 ELSE 0 END) as with_personality
FROM users
WHERE access != 3;

-- Expected: with_personality should be 0 (only fake users get personalities)

-- SUCCESS CRITERIA:
-- ✅ All 5 columns exist
-- ✅ All 3 indexes created
-- ✅ All fake users have personality, difficulty, and npc_info
-- ✅ Personalities and difficulties are roughly evenly distributed
-- ✅ Non-fake users (access != 3) have NULL for NPC columns
