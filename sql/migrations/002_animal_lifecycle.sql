-- Sprint 2: ciclo de vida, datos de adquisición e historial auditable.
-- Ejecute una sola vez después de seleccionar la base de la aplicación.

ALTER TABLE animals
  ADD COLUMN acquisition_date DATE NULL AFTER birth_date,
  ADD COLUMN initial_weight_kg DECIMAL(10,2) NULL AFTER acquisition_date,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE animals
   SET acquisition_date = COALESCE(birth_date, DATE(created_at)),
       initial_weight_kg = CASE WHEN origin='NACIMIENTO' THEN 25 ELSE COALESCE(initial_weight_kg,25) END
 WHERE acquisition_date IS NULL OR initial_weight_kg IS NULL;

ALTER TABLE animals
  MODIFY acquisition_date DATE NOT NULL,
  MODIFY initial_weight_kg DECIMAL(10,2) NOT NULL;

ALTER TABLE animal_events
  ADD COLUMN evidence_reference VARCHAR(500) NULL AFTER notes,
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER evidence_reference,
  ADD INDEX idx_animal_event_date(animal_id,event_type,event_date);

CREATE TABLE animal_history(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  action_type ENUM('BIRTH','PURCHASE','CREATED','UPDATED','DEATH','STATUS_CHANGE') NOT NULL,
  action_date DATE NOT NULL,
  group_id INT NULL,
  location_id INT NULL,
  status VARCHAR(20) NOT NULL,
  weight_kg DECIMAL(10,2) NULL,
  value_crc DECIMAL(14,2) NULL,
  rule_set_id BIGINT NULL,
  details TEXT NULL,
  created_by VARCHAR(120) NOT NULL DEFAULT 'system',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(animal_id,action_date),
  FOREIGN KEY(animal_id) REFERENCES animals(id),
  FOREIGN KEY(group_id) REFERENCES cattle_groups(id),
  FOREIGN KEY(location_id) REFERENCES locations(id),
  FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id)
);

INSERT INTO animal_history(animal_id,action_type,action_date,group_id,location_id,status,weight_kg,value_crc,details,created_by)
SELECT id,
       CASE origin WHEN 'NACIMIENTO' THEN 'BIRTH' WHEN 'COMPRA' THEN 'PURCHASE' ELSE 'CREATED' END,
       acquisition_date,group_id,location_id,status,initial_weight_kg,purchase_value,
       '{"migration":"002_animal_lifecycle"}','migration'
  FROM animals
 WHERE NOT EXISTS (SELECT 1 FROM animal_history h WHERE h.animal_id=animals.id);
