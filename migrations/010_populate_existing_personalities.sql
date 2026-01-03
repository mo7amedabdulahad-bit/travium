-- Populate personality templates for existing NPC personalities
-- (aggressive, economic, diplomat)

INSERT IGNORE INTO npc_personality_templates 
(personality, phase, build_priorities_json, troop_template_json, behavior_params_json) VALUES

-- aggressive personality (offensive/raider style)
('aggressive', 'Early', 
 '["Barracks","Warehouse","Granary","Marketplace"]',
 '{"1":20,"2":30,"3":0,"4":0,"5":0,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.8,"scout_rate":0.3,"attack_preference":"raid"}'),

('aggressive', 'Mid',
 '["Stable","Smithy","Academy","Rally Point"]',
 '{"1":10,"2":20,"3":0,"4":30,"5":20,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.9,"scout_rate":0.4,"attack_preference":"raid"}'),

('aggressive', 'Late',
 '["Workshop","Siege Workshop","Hero Mansion"]',
 '{"1":5,"2":15,"3":0,"4":25,"5":30,"6":15,"7":0,"8":10,"9":0,"10":0}',
 '{"raid_frequency":1.0,"scout_rate":0.5,"attack_preference":"mixed"}'),

-- economic personality (balanced/supplier style)
('economic', 'Early',
 '["Woodcutter","Clay Pit","Iron Mine","Cropland","Warehouse","Granary"]',
 '{"1":20,"2":20,"3":0,"4":0,"5":0,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.3,"scout_rate":0.2,"attack_preference":"raid"}'),

('economic', 'Mid',
 '["Marketplace","Barracks","Stable","Rally Point"]',
 '{"1":15,"2":15,"3":0,"4":20,"5":15,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.4,"scout_rate":0.3,"attack_preference":"raid"}'),

('economic', 'Late',
 '["Trade Office","Smithy","Workshop","Hero Mansion"]',
 '{"1":10,"2":15,"3":0,"4":20,"5":20,"6":15,"7":0,"8":10,"9":0,"10":0}',
 '{"raid_frequency":0.5,"scout_rate":0.4,"attack_preference":"mixed"}'),

-- diplomat personality (balanced)
('diplomat', 'Early',
 '["Barracks","Warehouse","Granary"]',
 '{"1":25,"2":25,"3":0,"4":0,"5":0,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.5,"scout_rate":0.3,"attack_preference":"raid"}'),

('diplomat', 'Mid',
 '["Stable","Smithy","Rally Point","Embassy"]',
 '{"1":15,"2":20,"3":0,"4":25,"5":15,"6":0,"7":0,"8":0,"9":0,"10":0}',
 '{"raid_frequency":0.6,"scout_rate":0.4,"attack_preference":"raid"}'),

('diplomat', 'Late',
 '["Workshop","Hero Mansion","Residence"]',
 '{"1":10,"2":15,"3":0,"4":20,"5":20,"6":15,"7":0,"8":10,"9":0,"10":0}',
 '{"raid_frequency":0.7,"scout_rate":0.5,"attack_preference":"mixed"}');

-- Verify insertion
SELECT personality, phase, 'OK' as status
FROM npc_personality_templates
WHERE personality IN ('aggressive', 'economic', 'diplomat')
ORDER BY personality, phase;
