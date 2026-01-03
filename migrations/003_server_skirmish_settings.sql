CREATE TABLE IF NOT EXISTS server_settings (
    server_id INT PRIMARY KEY,
    npc_count INT DEFAULT 50,
    map_size INT DEFAULT 25, -- coordinate range ±25
    game_speed_multiplier DECIMAL(3,2) DEFAULT 1.0,
    ww_contender_count INT DEFAULT 2, -- 1 or 2 alliances build WW
    difficulty ENUM('Easy','Medium','Hard') DEFAULT 'Medium',
    frontier_village_distance INT DEFAULT 15, -- tiles from center
    frontier_village_spawn_hour INT DEFAULT 6, -- game hours after start
    attack_ww_on_level_up BOOLEAN DEFAULT TRUE,
    enable_npc_attacks BOOLEAN DEFAULT TRUE,
    enable_npc_debug_logs BOOLEAN DEFAULT FALSE,
    personality_weights_json TEXT, -- {"Raider":30, "Guardian":25, ...}
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
