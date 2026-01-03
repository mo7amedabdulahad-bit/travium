-- Fix npc_difficulty ENUM to match code expectations
-- Current: beginner, intermediate, advanced, expert
-- Needed: Easy, Medium, Hard

ALTER TABLE users 
MODIFY COLUMN npc_difficulty ENUM('Easy', 'Medium', 'Hard') DEFAULT 'Medium';

-- Set all existing NPCs to Medium difficulty
UPDATE users 
SET npc_difficulty = 'Medium' 
WHERE access = 3;

-- Verify
SELECT id, name, npc_difficulty 
FROM users 
WHERE access = 3 
LIMIT 5;
