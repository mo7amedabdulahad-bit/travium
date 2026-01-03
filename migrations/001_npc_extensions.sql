-- NPC village metadata extension
CREATE TABLE IF NOT EXISTS npc_villages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    village_id INT NOT NULL, -- links to existing vdata.kid
    npc_player_id INT NOT NULL, -- links to users.id
    village_role ENUM('Headquarters','War','Support','Frontier') NOT NULL,
    next_action_at DATETIME NULL,
    assigned_script_phase ENUM('Early','Mid','Late','Endgame') DEFAULT 'Early',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_village (village_id),
    INDEX idx_npc_action (npc_player_id, next_action_at),
    UNIQUE KEY uk_village (village_id)
);

-- World events inbox (triggers for NPCs)
CREATE TABLE IF NOT EXISTS npc_world_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    server_id INT NOT NULL DEFAULT 1,
    event_type ENUM('AllianceAttacked','WWPlanReleased','WWUnderAttack','NPCAttacked','AllyLost') NOT NULL,
    target_alliance_id INT NULL,
    target_npc_id INT NULL,
    event_data_json TEXT, -- JSON payload for event details
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    INDEX idx_server_unprocessed (server_id, processed_at, created_at)
);
