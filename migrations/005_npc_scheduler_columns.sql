-- Add scheduler columns to users table
ALTER TABLE users 
ADD COLUMN next_tick_at DATETIME NULL,
ADD COLUMN tick_interval_seconds INT DEFAULT 300,
ADD COLUMN war_village_id INT NULL;

-- Add indexes for efficient scheduling and lookups
CREATE INDEX idx_users_next_tick ON users (access, next_tick_at);
CREATE INDEX idx_users_war_village ON users (war_village_id);

-- Initialize next_tick_at for existing NPCs (spread over next 5 minutes to avoid thundering herd)
UPDATE users 
SET next_tick_at = DATE_ADD(NOW(), INTERVAL FLOOR(RAND() * 300) SECOND),
    tick_interval_seconds = 300
WHERE access = 3;
