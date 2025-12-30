-- =====================================================
-- Skirmish Mode Database Migration
-- Phase 1: Installation Infrastructure
-- =====================================================

-- Add game events table for Artifacts and Wonder of the World scheduling
CREATE TABLE IF NOT EXISTS `game_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_type` ENUM('artifact_release', 'ww_release') NOT NULL,
    `scheduled_day` INT UNSIGNED NOT NULL COMMENT 'Days since server start',
    `executed` TINYINT(1) DEFAULT 0,
    `executed_at` TIMESTAMP NULL DEFAULT NULL,
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_executed` (`executed`),
    INDEX `idx_scheduled_day` (`scheduled_day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add skirmish mode flag to config table
ALTER TABLE `config` 
ADD COLUMN IF NOT EXISTS `skirmish_mode` TINYINT(1) DEFAULT 0 
COMMENT 'Is this server a skirmish game (1) or multiplayer (0)' 
AFTER `installed`;

-- Add player quadrant to config for reference
ALTER TABLE `config`
ADD COLUMN IF NOT EXISTS `player_quadrant` ENUM('NW', 'NE', 'SW', 'SE') NULL DEFAULT NULL
COMMENT 'Player starting quadrant in skirmish mode'
AFTER `skirmish_mode`;

-- Add NPC count to config for reference
ALTER TABLE `config`
ADD COLUMN IF NOT EXISTS `npc_count` INT UNSIGNED DEFAULT 0
COMMENT 'Number of NPCs in skirmish mode'
AFTER `player_quadrant`;

-- Note: The following columns will be added in Phase 2
-- ALTER TABLE `users` ADD COLUMN `npc_difficulty` ENUM('easy', 'medium', 'hard') NULL DEFAULT NULL;
-- ALTER TABLE `users` ADD COLUMN `npc_personality` ENUM('defensive', 'aggressive', 'economic') NULL DEFAULT NULL;
