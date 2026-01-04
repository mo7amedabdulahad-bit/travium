-- Phase 7: Village Expansion & Naming
-- Adds tracking for multi-village NPC expansion

-- Add expansion plan storage to users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS expansion_plan_json TEXT
COMMENT 'Stores village expansion plan: target coords, timing, resource requirements';

-- Add village sequence number to npc_villages
ALTER TABLE npc_villages
ADD COLUMN IF NOT EXISTS village_number TINYINT UNSIGNED DEFAULT 1
COMMENT 'Village sequence number (1=capital, 2-4=expansion villages)';

-- Add village age for accelerated development logic
ALTER TABLE npc_villages
ADD COLUMN IF NOT EXISTS founded_at DATETIME DEFAULT CURRENT_TIMESTAMP
COMMENT 'When this village was founded';

-- Index for efficient expansion queries
CREATE INDEX IF NOT EXISTS idx_npc_villages_expansion 
ON npc_villages(npc_player_id, village_number);

-- Index for finding new villages needing accelerated development
CREATE INDEX IF NOT EXISTS idx_npc_villages_age 
ON npc_villages(npc_player_id, founded_at);
