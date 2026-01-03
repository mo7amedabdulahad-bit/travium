-- Set war villages for all NPCs
-- Each NPC's war village should be their first village
-- This runs on existing servers to fix NPCs without war villages

UPDATE users u 
SET war_village_id = (
    SELECT MIN(kid)
    FROM vdata v 
    WHERE v.owner = u.id
)
WHERE u.access = 3;

-- Verify the update
SELECT u.id, u.name, u.war_village_id, v.name as war_village_name
FROM users u
LEFT JOIN vdata v ON u.war_village_id = v.kid
WHERE u.access = 3
ORDER BY u.id;
