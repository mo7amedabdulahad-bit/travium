-- Populate NPC personality templates with default behavior patterns
-- This provides NPCs with build priorities, troop compositions, and behavior parameters

INSERT IGNORE INTO npc_personality_templates (personality, phase, build_priorities_json, troop_template_json, behavior_params_json) VALUES

-- Raider personality - aggressive, focuses on raids and fast troops
('Raider', 'Early', 
 '["Barracks","Warehouse","Granary","Marketplace"]',
 '{"1":20,"2":30,"3":0,"4":0,"5":0,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.8,"scout_rate":0.3,"attack_preference":"raid"}'),

('Raider', 'Mid',
 '["Stable","Smithy","Academy","Rally Point"]',
 '{"1":10,"2":20,"3":0,"4":30,"5":20,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.9,"scout_rate":0.4,"attack_preference":"raid"}'),

('Raider', 'Late',
 '["Workshop","Siege Workshop","Hero Mansion"]',
 '{"1":5,"2":15,"3":0,"4":25,"5":30,"6":15,"7":0,"8":10,"9":0,"10":0}',
 '{"raid_frequency":1.0,"scout_rate":0.5,"attack_preference":"mixed"}'),

-- Guardian personality - defensive, protects allies
('Guardian', 'Early',
 '["Barracks","Wall","Warehouse","Granary"]',
 '{"1":40,"2":10,"3":0,"4":0,"5":0,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.2,"scout_rate":0.2,"attack_preference":"none"}'),

('Guardian', 'Mid',
 '["City Wall","Smithy","Stable","Rally Point"]',
 '{"1":30,"2":10,"3":0,"4":20,"5":10,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.3,"scout_rate":0.3,"attack_preference":"defensive"}'),

('Guardian', 'Late',
 '["Stone Wall","Trapper","Hero Mansion"]',
 '{"1":25,"2":15,"3":0,"4":15,"5":15,"6":10,"7":0,"8":10,"9":0,"10":0}',
 '{"raid_frequency":0.4,"scout_rate":0.4,"attack_preference":"counter"}'),

-- Balanced personality (used for 'aggressive' and 'economic' NPCs)
('Supplier', 'Early',
 '["Woodcutter","Clay Pit","Iron Mine","Cropland","Warehouse","Granary"]',
 '{"1":20,"2":20,"3":0,"4":0,"5":0,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.5,"scout_rate":0.3,"attack_preference":"raid"}'),

('Supplier', 'Mid',
 '["Marketplace","Barracks","Stable","Rally Point"]',
 '{"1":15,"2":15,"3":0,"4":20,"5":15,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.6,"scout_rate":0.4,"attack_preference":"raid"}'),

('Supplier', 'Late',
 '["Trade Office","Smithy","Workshop","Hero Mansion"]',
 '{"1":10,"2":15,"3":0,"4":20,"5":20,"6":15,"7":0,"8":10,"9":0,"10":0}',
 '{"raid_frequency":0.7,"scout_rate":0.5,"attack_preference":"mixed"}');

-- Verify templates were inserted
SELECT personality, phase, 'Inserted' as status 
FROM npc_personality_templates 
ORDER BY personality, 
CASE phase 
  WHEN 'Early' THEN 1 
  WHEN 'Mid' THEN 2 
  WHEN 'Late' THEN 3 
  WHEN 'Endgame' THEN 4 
END;
