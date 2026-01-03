-- Phase 5: Alliance Coordination & Event Processing
-- Adds memory tracking for retaliation and optimizes event processing

-- Add memory column to users table for NPC state tracking
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS npc_memory_json TEXT 
COMMENT 'Stores NPC memory: retaliation targets, defense cooldowns, etc.';

-- Add index for faster event processing
CREATE INDEX IF NOT EXISTS idx_events_processing 
ON npc_world_events(server_id, processed_at, created_at);

-- Add index for alliance lookups during coordination
CREATE INDEX IF NOT EXISTS idx_users_alliance 
ON users(aid, access);
