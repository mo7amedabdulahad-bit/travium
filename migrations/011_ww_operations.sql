-- Phase 6: World Wonder Endgame System
-- Adds WW operation state tracking and alliance role designation

-- Add WW operation state to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS ww_operation_state ENUM(
    'Idle',
    'PlanHunting',
    'PlanSecured',
    'WWBuilding',
    'WWDefending',
    'OperationFailed'
) DEFAULT 'Idle' 
COMMENT 'Current World Wonder operation phase for this NPC';

-- Add WW alliance role designation
ALTER TABLE users
ADD COLUMN IF NOT EXISTS ww_alliance_role ENUM('Contender', 'Spoiler', 'Neutral') 
DEFAULT 'Neutral' 
COMMENT 'Alliance role in WW race: Contender (build WW), Spoiler (disrupt), or Neutral';

-- Index for efficient WW operation queries
CREATE INDEX IF NOT EXISTS idx_ww_operations 
ON users(ww_operation_state, ww_alliance_role, access);

-- Extend event types in npc_world_events for WW-related events
-- Note: This modifies existing enum, ensure compatibility
ALTER TABLE npc_world_events
MODIFY COLUMN event_type ENUM(
    'AllianceAttacked',
    'WWPlanReleased',
    'WWUnderAttack',
    'NPCAttacked',
    'AllyLost',
    'WWLevelUp',
    'WWDefeated'
) NOT NULL;

-- Add WW-specific event data fields for better performance
ALTER TABLE npc_world_events
ADD COLUMN IF NOT EXISTS ww_village_id INT NULL COMMENT 'WW village ID if applicable',
ADD COLUMN IF NOT EXISTS ww_level TINYINT UNSIGNED NULL COMMENT 'WW level at time of event';

-- Index for WW event lookups
CREATE INDEX IF NOT EXISTS idx_ww_events 
ON npc_world_events(event_type, ww_village_id, processed_at);
