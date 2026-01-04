-- NPC System Query Optimization Audit
-- Verifies critical queries use proper indexes
-- Run this after migrations to ensure performance

-- ============================================
-- Part 1: Check Existing Indexes
-- ============================================

SELECT 'Checking indexes on users table...' as Status;
SHOW INDEX FROM users WHERE Key_name LIKE '%access%' OR Key_name LIKE '%tick%';

SELECT 'Checking indexes on vdata table...' as Status;
SHOW INDEX FROM vdata WHERE Key_name LIKE '%owner%';

SELECT 'Checking indexes on wdata table...' as Status;
SHOW INDEX FROM wdata WHERE Key_name LIKE '%coord%' OR Key_name LIKE '%x%' OR Key_name LIKE '%y%';

SELECT 'Checking indexes on movement table...' as Status;
SHOW INDEX FROM movement WHERE Key_name LIKE '%arrival%' OR Key_name LIKE '%from%' OR Key_name LIKE '%to%';

SELECT 'Checking indexes on npc_world_events table...' as Status;
SHOW INDEX FROM npc_world_events;

-- ============================================
-- Part 2: Add Missing Indexes (if needed)
-- ============================================

SELECT 'Creating missing indexes...' as Status;

-- Users table: For scheduler query
CREATE INDEX IF NOT EXISTS idx_users_npc_tick ON users(access, next_tick_at)
COMMENT 'For NPC scheduler queries';

-- Wdata table: For coordinate range queries
CREATE INDEX IF NOT EXISTS idx_wdata_coords ON wdata(x, y)
COMMENT 'For target selection and village placement';

-- Movement table: For arrival time queries  
CREATE INDEX IF NOT EXISTS idx_movement_arrival ON movement(end_time)
COMMENT 'For processing arriving movements';

-- Vdata table: For owner queries
CREATE INDEX IF NOT EXISTS idx_vdata_owner ON vdata(owner)
COMMENT 'For finding NPC villages';

-- Npc_world_events table: For unprocessed events
CREATE INDEX IF NOT EXISTS idx_npc_events_processed ON npc_world_events(processed_at, event_type)
COMMENT 'For finding unprocessed events';

-- Alliance data table: For member queries
CREATE INDEX IF NOT EXISTS idx_alidata_id ON alidata(id)
COMMENT 'For alliance lookups';

SELECT 'Indexes created successfully' as Status;

-- ============================================
-- Part 3: Query Performance Tests
-- ============================================

SELECT 'Testing NPC scheduler query...' as Status;
EXPLAIN SELECT * FROM users 
WHERE access = 3 AND (next_tick_at IS NULL OR next_tick_at <= NOW()) 
ORDER BY next_tick_at ASC LIMIT 10;
-- Expected: "Using index" in Extra column

SELECT 'Testing target selection query...' as Status;
EXPLAIN SELECT * FROM wdata
WHERE x BETWEEN -20 AND 20 AND y BETWEEN -20 AND 20
AND occupied = 1
LIMIT 10;
-- Expected: "range" access type

SELECT 'Testing village troops query...' as Status;
EXPLAIN SELECT * FROM units WHERE vref IN (
    SELECT kid FROM vdata WHERE owner = 1 LIMIT 5
);
-- Should use index on vref

SELECT 'Testing alliance members query...' as Status;
EXPLAIN SELECT u.id, u.name, v.kid
FROM users u
LEFT JOIN vdata v ON u.id = v.owner
WHERE u.aid = 1 AND u.access = 3;
-- Should use index on aid and access

SELECT 'Testing movement arrival query...' as Status;
EXPLAIN SELECT * FROM movement
WHERE endtime <= UNIX_TIMESTAMP()
AND attack_type IN (1,2,3,4,5)
ORDER BY endtime ASC
LIMIT 100;
-- Should use index on endtime

-- ============================================
-- Part 4: Table Statistics
-- ============================================

SELECT 'Gathering table statistics...' as Status;

SELECT 
    'users' as TableName,
    COUNT(*) as TotalRows,
    SUM(CASE WHEN access = 3 THEN 1 ELSE 0 END) as NPCs
FROM users;

SELECT 
    'vdata' as TableName,
    COUNT(*) as TotalVillages,
    COUNT(DISTINCT owner) as UniqueOwners
FROM vdata;

SELECT 
    'wdata' as TableName,
    COUNT(*) as TotalTiles,
    SUM(CASE WHEN occupied = 1 THEN 1 ELSE 0 END) as OccupiedTiles
FROM wdata;

SELECT 
    'movement' as TableName,
    COUNT(*) as TotalMovements,
    SUM(CASE WHEN endtime > UNIX_TIMESTAMP() THEN 1 ELSE 0 END) as ActiveMovements
FROM movement;

SELECT 
    'npc_world_events' as TableName,
    COUNT(*) as TotalEvents,
    SUM(CASE WHEN processed_at IS NULL THEN 1 ELSE 0 END) as UnprocessedEvents
FROM npc_world_events;

-- ============================================
-- Part 5: Slow Query Candidates
-- ============================================

SELECT 'Identifying potential slow queries...' as Status;

-- Check for tables without indexes on frequently joined columns
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    'Missing index?' as Warning
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('users', 'vdata', 'wdata', 'movement', 'units', 'fdata')
  AND COLUMN_NAME IN ('owner', 'vref', 'kid', 'aid', 'x', 'y', 'attack_type')
  AND NOT EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = COLUMNS.TABLE_NAME
        AND COLUMN_NAME = COLUMNS.COLUMN_NAME
  );

SELECT 'Audit complete!' as Status;
