-- Sprint 4: personal, actividades, costos detallados, transferencias y grupo histórico.
-- Ejecute una sola vez después de las migraciones 001, 002 y 003.

CREATE TABLE employees(id INT AUTO_INCREMENT PRIMARY KEY,employee_code VARCHAR(40) NOT NULL UNIQUE,name VARCHAR(160) NOT NULL,hourly_rate DECIMAL(14,2) NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE activities(id INT AUTO_INCREMENT PRIMARY KEY,activity_code VARCHAR(40) NOT NULL UNIQUE,name VARCHAR(160) NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
INSERT INTO activities(activity_code,name) VALUES ('ALIMENTACION','Alimentación'),('MANEJO','Manejo de ganado'),('SANIDAD','Sanidad y veterinaria'),('MANTENIMIENTO','Mantenimiento'),('ADMINISTRACION','Administración'),('OTRA','Otra actividad');

ALTER TABLE costs
  ADD COLUMN activity_id INT NULL AFTER group_id,
  ADD COLUMN unit VARCHAR(30) NOT NULL DEFAULT 'unidad' AFTER description,
  ADD CONSTRAINT fk_costs_activity FOREIGN KEY(activity_id) REFERENCES activities(id);

CREATE TABLE labor_entries(id BIGINT AUTO_INCREMENT PRIMARY KEY,employee_id INT NOT NULL,group_id INT NOT NULL,activity_id INT NOT NULL,work_date DATE NOT NULL,hours DECIMAL(8,2) NOT NULL,hourly_rate DECIMAL(14,2) NOT NULL,amount DECIMAL(14,2) NOT NULL,notes VARCHAR(500) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(group_id,work_date),FOREIGN KEY(employee_id) REFERENCES employees(id),FOREIGN KEY(group_id) REFERENCES cattle_groups(id),FOREIGN KEY(activity_id) REFERENCES activities(id));

ALTER TABLE weights ADD COLUMN group_id INT NULL AFTER animal_id;
UPDATE weights w JOIN animals a ON a.id=w.animal_id SET w.group_id=a.group_id;
ALTER TABLE weights MODIFY group_id INT NOT NULL,ADD INDEX idx_weights_group_date(group_id,weight_date),ADD CONSTRAINT fk_weights_group FOREIGN KEY(group_id) REFERENCES cattle_groups(id);

ALTER TABLE animal_events ADD COLUMN group_id INT NULL AFTER animal_id;
UPDATE animal_events e JOIN animals a ON a.id=e.animal_id SET e.group_id=a.group_id;
ALTER TABLE animal_events MODIFY group_id INT NOT NULL,ADD INDEX idx_events_group_date(group_id,event_date),ADD CONSTRAINT fk_events_group FOREIGN KEY(group_id) REFERENCES cattle_groups(id);

ALTER TABLE sales ADD COLUMN group_id INT NULL AFTER animal_id;
UPDATE sales s JOIN animals a ON a.id=s.animal_id SET s.group_id=a.group_id;
ALTER TABLE sales MODIFY group_id INT NOT NULL,ADD CONSTRAINT fk_sales_group FOREIGN KEY(group_id) REFERENCES cattle_groups(id);

ALTER TABLE transfers
  ADD COLUMN weight_method ENUM('ACTUAL','PROJECTED') NOT NULL DEFAULT 'PROJECTED' AFTER to_group_id,
  ADD COLUMN actual_weight_kg DECIMAL(10,2) NULL AFTER weight_method,
  ADD COLUMN projected_weight_kg DECIMAL(10,2) NULL AFTER actual_weight_kg,
  ADD COLUMN rule_set_id BIGINT NULL AFTER total_value,
  ADD COLUMN notes VARCHAR(500) NULL AFTER rule_set_id,
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER notes,
  ADD INDEX idx_transfer_animal_date(animal_id,transfer_date),
  ADD CONSTRAINT fk_transfers_rule_set FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id);
UPDATE transfers SET projected_weight_kg=weight_kg WHERE projected_weight_kg IS NULL;
ALTER TABLE transfers MODIFY projected_weight_kg DECIMAL(10,2) NOT NULL;

CREATE TABLE transfer_ledger_entries(id BIGINT AUTO_INCREMENT PRIMARY KEY,transfer_id BIGINT NOT NULL,group_id INT NOT NULL,entry_type ENUM('TRANSFER_OUT','TRANSFER_IN') NOT NULL,amount DECIMAL(14,2) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE(transfer_id,entry_type),FOREIGN KEY(transfer_id) REFERENCES transfers(id),FOREIGN KEY(group_id) REFERENCES cattle_groups(id));
INSERT INTO transfer_ledger_entries(transfer_id,group_id,entry_type,amount)
SELECT id,from_group_id,'TRANSFER_OUT',total_value FROM transfers;
INSERT INTO transfer_ledger_entries(transfer_id,group_id,entry_type,amount)
SELECT id,to_group_id,'TRANSFER_IN',total_value FROM transfers;

ALTER TABLE animal_history MODIFY action_type ENUM('BIRTH','PURCHASE','CREATED','UPDATED','DEATH','TRANSFER','STATUS_CHANGE') NOT NULL;
