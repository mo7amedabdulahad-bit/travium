CREATE TABLE IF NOT EXISTS npc_names (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    gender ENUM('Male','Female') DEFAULT 'Male',
    is_used BOOLEAN DEFAULT FALSE
);

-- Seed Initial Names (Samples)
INSERT IGNORE INTO npc_names (name, gender) VALUES
('Tiberius', 'Male'), ('Caius', 'Male'), ('Marcus', 'Male'), ('Julia', 'Female'), 
('Hermann', 'Male'), ('Arminius', 'Male'), ('Thusnelda', 'Female'), ('Sigrdrifa', 'Female'),
('Vercingetorix', 'Male'), ('Brennus', 'Male'), ('Boudicca', 'Female'), ('Cartimandua', 'Female'),
('Attila', 'Male'), ('Bleda', 'Male'), ('Ildico', 'Female'), ('Kreka', 'Female'),
('Imhotep', 'Male'), ('Ramesses', 'Male'), ('Nefertiti', 'Female'), ('Cleopatra', 'Female'),
('Scipio', 'Male'), ('Fabius', 'Male'), ('Cornelia', 'Female'), ('Livia', 'Female'),
('Spartacus', 'Male'), ('Crixus', 'Male'), ('Gannicus', 'Male'), ('Oenomaus', 'Male'),
('Hannibal', 'Male'), ('Hamilcar', 'Male'), ('Hasdrubal', 'Male'), ('Sophonisba', 'Female'),
('Zenobia', 'Female'), ('Odenathus', 'Male'), ('Vaballathus', 'Male'),
('Alaric', 'Male'), ('Theodoric', 'Male'), ('Galla Placidia', 'Female'),
('Clovis', 'Male'), ('Charlemagne', 'Male'), ('Hildegard', 'Female'),
('Ragnar', 'Male'), ('Lagertha', 'Female'), ('Bjorn', 'Male'), ('Ivar', 'Male');
-- (In a real scenario we'd add hundreds more, keeping it brief for this step)
