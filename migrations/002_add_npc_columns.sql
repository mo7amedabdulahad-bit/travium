-- Migration: Add NPC columns to users table
-- Date: 2025-12-28
-- Phase: NPC Foundation - Database Schema Extension
-- Purpose: Add personality and difficulty columns for intelligent NPC system

-- Add NPC personality column
ALTER TABLE `users` ADD COLUMN `npc_personality` 
    ENUM('aggressive','economic','balanced','diplomat','assassin') NULL DEFAULT NULL
    COMMENT 'NPC personality type determining behavior patterns'
    AFTER `access`;

-- Add NPC difficulty column
ALTER TABLE `users` ADD COLUMN `npc_difficulty` 
    ENUM('beginner','intermediate','advanced','expert') NULL DEFAULT NULL
    COMMENT 'NPC difficulty level determining action frequency'
    AFTER `npc_personality`;

-- Add NPC configuration JSON column
ALTER TABLE `users` ADD COLUMN `npc_info` JSON NULL DEFAULT NULL
    COMMENT 'NPC extended configuration and state data'
    AFTER `npc_difficulty`;

-- Add last NPC action timestamp
ALTER TABLE `users` ADD COLUMN `last_npc_action` INT(11) UNSIGNED NULL DEFAULT NULL
    COMMENT 'Unix timestamp of last NPC processing'
    AFTER `npc_info`;

-- Add goldclub status (for instant training/building)
ALTER TABLE `users` ADD COLUMN `goldclub` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Gold club membership for instant actions'
    AFTER `last_npc_action`;

-- Add indexes for NPC queries
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
    `last_npc_action` = NULL,
    `goldclub` = 0
WHERE `access` = 3;
