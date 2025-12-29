-- Safe Migration: Add NPC columns to users table (checks for existing columns)
-- Date: 2025-12-29
-- Purpose: Add NPC columns only if they don't already exist

-- Add npc_personality if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'npc_personality';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
  'SELECT "npc_personality column already exists" AS Info',
  CONCAT('ALTER TABLE `users` ADD COLUMN `npc_personality` ',
         'ENUM(''aggressive'',''economic'',''balanced'',''diplomat'',''assassin'') NULL DEFAULT NULL ',
         'COMMENT ''NPC personality type determining behavior patterns'' ',
         'AFTER `goldclub`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add npc_difficulty if it doesn't exist
SET @columnname = 'npc_difficulty';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
  'SELECT "npc_difficulty column already exists" AS Info',
  CONCAT('ALTER TABLE `users` ADD COLUMN `npc_difficulty` ',
         'ENUM(''beginner'',''intermediate'',''advanced'',''expert'') NULL DEFAULT NULL ',
         'COMMENT ''NPC difficulty level determining action frequency'' ',
         'AFTER `npc_personality`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add npc_info if it doesn't exist
SET @columnname = 'npc_info';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
  'SELECT "npc_info column already exists" AS Info',
  CONCAT('ALTER TABLE `users` ADD COLUMN `npc_info` ',
         'JSON NULL DEFAULT NULL ',
         'COMMENT ''NPC extended configuration and state data'' ',
         'AFTER `npc_difficulty`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add last_npc_action if it doesn't exist
SET @columnname = 'last_npc_action';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
  'SELECT "last_npc_action column already exists" AS Info',
  CONCAT('ALTER TABLE `users` ADD COLUMN `last_npc_action` ',
         'INT(11) UNSIGNED NULL DEFAULT NULL ',
         'COMMENT ''Unix timestamp of last NPC processing'' ',
         'AFTER `npc_info`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add indexes (will fail silently if already exist)
ALTER TABLE `users` ADD INDEX `npc_processing` (`access`, `last_npc_action`);
ALTER TABLE `users` ADD INDEX `npc_personality` (`npc_personality`);
ALTER TABLE `users` ADD INDEX `npc_difficulty` (`npc_difficulty`);

-- Update existing fake users (access=3) with random personalities and difficulties
UPDATE `users` 
SET 
    `npc_personality` = ELT(FLOOR(1 + RAND() * 5), 'aggressive', 'economic', 'balanced', 'diplomat', 'assassin'),
    `npc_difficulty` = ELT(FLOOR(1 + RAND() * 4), 'beginner', 'intermediate', 'advanced', 'expert'),
    `npc_info` = JSON_OBJECT(
        'created_at', UNIX_TIMESTAMP(),
        'version', '1.0',
        'raids_sent', 0,
        'raids_received', 0,
        'total_buildings_built', 0,
        'total_troops_trained', 0
    ),
    `last_npc_action` = NULL
WHERE `access` = 3 AND `npc_personality` IS NULL;

SELECT 'Migration completed successfully!' AS Status;
