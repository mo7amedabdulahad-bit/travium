CREATE TABLE IF NOT EXISTS npc_personality_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    personality ENUM('Raider','Guardian','Supplier','Diplomat','Assassin') NOT NULL,
    phase ENUM('Early','Mid','Late','Endgame') NOT NULL,
    build_priorities_json TEXT, -- ["Barracks","Stable","Warehouse"]
    troop_template_json TEXT, -- {"Phalanx":50, "Swordsman":30}
    behavior_params_json TEXT, -- raid frequency, scout rate, etc.
    UNIQUE KEY uk_personality_phase (personality, phase)
);

CREATE TABLE IF NOT EXISTS npc_difficulty_policies (
    difficulty ENUM('Easy','Medium','Hard') PRIMARY KEY,
    tick_interval_seconds INT NOT NULL,
    raid_cooldown_minutes INT NOT NULL,
    mistake_rate_percent INT NOT NULL, -- occasionally skip actions
    max_target_candidates INT NOT NULL, -- limit search scope
    action_budget_multiplier DECIMAL(3,2) NOT NULL, -- resource multiplier
    scout_success_rate_percent INT NOT NULL
);

-- Prepopulate difficulty policies
INSERT IGNORE INTO npc_difficulty_policies VALUES
    ('Easy', 600, 120, 15, 5, 0.8, 70),
    ('Medium', 300, 60, 5, 10, 1.0, 85),
    ('Hard', 180, 30, 0, 20, 1.2, 95);
