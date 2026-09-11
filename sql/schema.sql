-- Seleccione o cree la base de datos antes de importar este archivo.
-- El script no crea una base con nombre fijo para ser compatible con SiteGround/cPanel.
CREATE TABLE cattle_groups(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL UNIQUE,active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0);
CREATE TABLE locations(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL UNIQUE,active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 0);
CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY,username VARCHAR(80) NOT NULL UNIQUE,display_name VARCHAR(160) NOT NULL,password_hash VARCHAR(255) NOT NULL,role ENUM('ADMIN','OPERATOR','FINANCE','VIEWER') NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,failed_login_count INT NOT NULL DEFAULT 0,locked_until DATETIME NULL,last_login_at DATETIME NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE user_activity_log(id BIGINT AUTO_INCREMENT PRIMARY KEY,user_id INT NULL,username VARCHAR(80) NULL,action VARCHAR(80) NOT NULL,route VARCHAR(160) NOT NULL,request_method VARCHAR(10) NOT NULL,ip_address VARCHAR(45) NULL,details TEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(user_id,created_at),FOREIGN KEY(user_id) REFERENCES users(id));
CREATE TABLE employees(id INT AUTO_INCREMENT PRIMARY KEY,employee_code VARCHAR(40) NOT NULL UNIQUE,name VARCHAR(160) NOT NULL,hourly_rate DECIMAL(14,2) NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE activities(id INT AUTO_INCREMENT PRIMARY KEY,activity_code VARCHAR(40) NOT NULL UNIQUE,name VARCHAR(160) NOT NULL,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE warehouse_products(id INT AUTO_INCREMENT PRIMARY KEY,product_code VARCHAR(40) NOT NULL UNIQUE,name VARCHAR(160) NOT NULL,cost_type ENUM('INSUMO','MATERIAL','VETERINARIO','ALIMENTACION','OTRO') NOT NULL,unit VARCHAR(30) NOT NULL,minimum_stock DECIMAL(12,2) NOT NULL DEFAULT 0,current_stock DECIMAL(12,2) NOT NULL DEFAULT 0,average_unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP);
CREATE TABLE animals(id INT AUTO_INCREMENT PRIMARY KEY,tag VARCHAR(80) NOT NULL UNIQUE,group_id INT NOT NULL,location_id INT NULL,sex ENUM('M','F') NOT NULL,birth_date DATE NULL,acquisition_date DATE NOT NULL,initial_weight_kg DECIMAL(10,2) NOT NULL,purchase_value DECIMAL(14,2) NULL,status ENUM('ACTIVO','VENDIDO','MUERTO','TRANSFERIDO') NOT NULL DEFAULT 'ACTIVO',origin ENUM('NACIMIENTO','COMPRA','TRANSFERENCIA') NOT NULL DEFAULT 'NACIMIENTO',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(group_id) REFERENCES cattle_groups(id),FOREIGN KEY(location_id) REFERENCES locations(id));
CREATE TABLE weight_import_batches(id BIGINT AUTO_INCREMENT PRIMARY KEY,channel ENUM('CSV','API') NOT NULL,source_name VARCHAR(255) NOT NULL,status ENUM('IMPORTED','REJECTED') NOT NULL,total_rows INT NOT NULL,imported_rows INT NOT NULL DEFAULT 0,rejected_rows INT NOT NULL DEFAULT 0,created_by VARCHAR(120) NOT NULL DEFAULT 'system',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE weights(id BIGINT AUTO_INCREMENT PRIMARY KEY,animal_id INT NOT NULL,group_id INT NOT NULL,weight_date DATE NOT NULL,weight_kg DECIMAL(10,2) NOT NULL,source ENUM('MANUAL','SISTEMA_EXISTENTE','IMPORTACION') DEFAULT 'MANUAL',external_reference VARCHAR(120) NULL,import_batch_id BIGINT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(animal_id,weight_date),INDEX(group_id,weight_date),UNIQUE(animal_id,weight_date,source),FOREIGN KEY(animal_id) REFERENCES animals(id),FOREIGN KEY(group_id) REFERENCES cattle_groups(id),FOREIGN KEY(import_batch_id) REFERENCES weight_import_batches(id));
CREATE TABLE animal_events(id BIGINT AUTO_INCREMENT PRIMARY KEY,animal_id INT NOT NULL,group_id INT NOT NULL,event_type ENUM('NACIMIENTO','MUERTE') NOT NULL,event_date DATE NOT NULL,weight_kg DECIMAL(10,2) NULL,value_crc DECIMAL(14,2) NULL,notes VARCHAR(500) NULL,evidence_reference VARCHAR(500) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(animal_id,event_type,event_date),INDEX(group_id,event_date),FOREIGN KEY(animal_id) REFERENCES animals(id),FOREIGN KEY(group_id) REFERENCES cattle_groups(id));
CREATE TABLE costs(id BIGINT AUTO_INCREMENT PRIMARY KEY,group_id INT NOT NULL,activity_id INT NULL,warehouse_product_id INT NULL,cost_date DATE NOT NULL,cost_type VARCHAR(40) NOT NULL,description VARCHAR(255) NOT NULL,unit VARCHAR(30) NOT NULL DEFAULT 'unidad',quantity DECIMAL(12,2) DEFAULT 1,unit_cost DECIMAL(14,2) NOT NULL,amount DECIMAL(14,2) NOT NULL,FOREIGN KEY(group_id) REFERENCES cattle_groups(id),FOREIGN KEY(activity_id) REFERENCES activities(id),FOREIGN KEY(warehouse_product_id) REFERENCES warehouse_products(id));
CREATE TABLE warehouse_movements(id BIGINT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,movement_date DATE NOT NULL,movement_type ENUM('ENTRY','ISSUE','ADJUSTMENT_IN','ADJUSTMENT_OUT') NOT NULL,quantity DECIMAL(12,2) NOT NULL,unit VARCHAR(30) NOT NULL,unit_cost DECIMAL(14,2) NOT NULL,total_value DECIMAL(16,2) NOT NULL,stock_after DECIMAL(12,2) NOT NULL,group_id INT NULL,activity_id INT NULL,cost_id BIGINT NULL,reference VARCHAR(120) NULL,notes VARCHAR(500) NULL,created_by VARCHAR(120) NOT NULL DEFAULT 'system',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(product_id,movement_date),INDEX(cost_id),FOREIGN KEY(product_id) REFERENCES warehouse_products(id),FOREIGN KEY(group_id) REFERENCES cattle_groups(id),FOREIGN KEY(activity_id) REFERENCES activities(id),FOREIGN KEY(cost_id) REFERENCES costs(id));
CREATE TABLE labor_entries(id BIGINT AUTO_INCREMENT PRIMARY KEY,employee_id INT NOT NULL,group_id INT NOT NULL,activity_id INT NOT NULL,work_date DATE NOT NULL,hours DECIMAL(8,2) NOT NULL,hourly_rate DECIMAL(14,2) NOT NULL,amount DECIMAL(14,2) NOT NULL,notes VARCHAR(500) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(group_id,work_date),FOREIGN KEY(employee_id) REFERENCES employees(id),FOREIGN KEY(group_id) REFERENCES cattle_groups(id),FOREIGN KEY(activity_id) REFERENCES activities(id));
CREATE TABLE transfers(id BIGINT AUTO_INCREMENT PRIMARY KEY,animal_id INT NOT NULL,transfer_date DATE NOT NULL,from_group_id INT NOT NULL,to_group_id INT NOT NULL,weight_method ENUM('ACTUAL','PROJECTED') NOT NULL,actual_weight_kg DECIMAL(10,2) NULL,projected_weight_kg DECIMAL(10,2) NOT NULL,weight_kg DECIMAL(10,2) NOT NULL,price_per_kg DECIMAL(14,2) NOT NULL,total_value DECIMAL(14,2) NOT NULL,rule_set_id BIGINT NULL,notes VARCHAR(500) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(animal_id,transfer_date),FOREIGN KEY(animal_id) REFERENCES animals(id),FOREIGN KEY(from_group_id) REFERENCES cattle_groups(id),FOREIGN KEY(to_group_id) REFERENCES cattle_groups(id));
CREATE TABLE transfer_ledger_entries(id BIGINT AUTO_INCREMENT PRIMARY KEY,transfer_id BIGINT NOT NULL,group_id INT NOT NULL,entry_type ENUM('TRANSFER_OUT','TRANSFER_IN') NOT NULL,amount DECIMAL(14,2) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE(transfer_id,entry_type),FOREIGN KEY(transfer_id) REFERENCES transfers(id),FOREIGN KEY(group_id) REFERENCES cattle_groups(id));
CREATE TABLE sales(id BIGINT AUTO_INCREMENT PRIMARY KEY,animal_id INT NOT NULL,group_id INT NOT NULL,sale_date DATE NOT NULL,weight_kg DECIMAL(10,2) NOT NULL,real_price_per_kg DECIMAL(14,2) NOT NULL,total_real DECIMAL(14,2) NOT NULL,projected_price_per_kg DECIMAL(14,2) NOT NULL,total_projected DECIMAL(14,2) NOT NULL,FOREIGN KEY(animal_id) REFERENCES animals(id),FOREIGN KEY(group_id) REFERENCES cattle_groups(id));
CREATE TABLE rule_sets(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  version VARCHAR(40) NOT NULL UNIQUE,
  status ENUM('DRAFT','APPROVED','ACTIVE','RETIRED') NOT NULL DEFAULT 'DRAFT',
  effective_from DATE NOT NULL,
  effective_to DATE NULL,
  change_reason VARCHAR(500) NOT NULL,
  created_by VARCHAR(120) NOT NULL DEFAULT 'system',
  approved_by VARCHAR(120) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at TIMESTAMP NULL,
  INDEX(status,effective_from,effective_to)
);
CREATE TABLE rule_parameters(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  rule_set_id BIGINT NOT NULL,
  rule_key VARCHAR(80) NOT NULL,
  rule_value VARCHAR(255) NOT NULL,
  value_type ENUM('DECIMAL','INTEGER','STRING','BOOLEAN') NOT NULL,
  unit VARCHAR(40) NULL,
  description VARCHAR(255) NOT NULL,
  UNIQUE(rule_set_id,rule_key),
  FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id)
);
CREATE TABLE monthly_closings(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  month_end DATE NOT NULL UNIQUE,
  status ENUM('CLOSED','REOPENED') NOT NULL,
  rule_set_id BIGINT NULL,
  rules_snapshot LONGTEXT NOT NULL,
  rules_hash CHAR(64) NOT NULL,
  opening_asset DECIMAL(16,2) NOT NULL,
  closing_asset DECIMAL(16,2) NOT NULL,
  biological_result DECIMAL(16,2) NOT NULL,
  operating_result DECIMAL(16,2) NOT NULL,
  financial_charge DECIMAL(16,2) NOT NULL,
  adjusted_result DECIMAL(16,2) NOT NULL,
  closed_by VARCHAR(120) NOT NULL,
  closed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reopened_by VARCHAR(120) NULL,
  reopened_at TIMESTAMP NULL,
  reopen_reason VARCHAR(500) NULL,
  FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id)
);
CREATE TABLE monthly_closing_groups(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  closing_id BIGINT NOT NULL,
  group_id INT NOT NULL,
  group_name VARCHAR(120) NOT NULL,
  opening_asset DECIMAL(16,2) NOT NULL,
  closing_asset DECIMAL(16,2) NOT NULL,
  purchases DECIMAL(16,2) NOT NULL,
  births DECIMAL(16,2) NOT NULL,
  deaths DECIMAL(16,2) NOT NULL,
  external_sales DECIMAL(16,2) NOT NULL,
  projected_sales DECIMAL(16,2) NOT NULL,
  sale_variance DECIMAL(16,2) NOT NULL,
  transfer_in DECIMAL(16,2) NOT NULL,
  transfer_out DECIMAL(16,2) NOT NULL,
  valuation_change DECIMAL(16,2) NOT NULL,
  supplies_cost DECIMAL(16,2) NOT NULL,
  labor_cost DECIMAL(16,2) NOT NULL,
  biological_result DECIMAL(16,2) NOT NULL,
  operating_result DECIMAL(16,2) NOT NULL,
  financial_charge DECIMAL(16,2) NOT NULL,
  adjusted_result DECIMAL(16,2) NOT NULL,
  reconciliation_difference DECIMAL(16,2) NOT NULL DEFAULT 0,
  UNIQUE(closing_id,group_id),
  FOREIGN KEY(closing_id) REFERENCES monthly_closings(id),
  FOREIGN KEY(group_id) REFERENCES cattle_groups(id)
);
CREATE TABLE monthly_valuations(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  closing_id BIGINT NOT NULL,
  animal_id INT NOT NULL,
  group_id INT NOT NULL,
  location_id INT NULL,
  animal_tag VARCHAR(80) NOT NULL,
  group_name VARCHAR(120) NOT NULL,
  location_name VARCHAR(120) NULL,
  month_end DATE NOT NULL,
  actual_weight_kg DECIMAL(10,2) NULL,
  actual_weight_date DATE NULL,
  projected_weight_kg DECIMAL(10,2) NOT NULL,
  valuation_method ENUM('ACTUAL','PROJECTED_FROM_WEIGHT','PROJECTED_FROM_BASE') NOT NULL,
  price_per_kg DECIMAL(14,2) NOT NULL,
  value_crc DECIMAL(16,2) NOT NULL,
  rule_set_id BIGINT NULL,
  UNIQUE(animal_id,month_end),
  FOREIGN KEY(closing_id) REFERENCES monthly_closings(id),
  FOREIGN KEY(animal_id) REFERENCES animals(id),
  FOREIGN KEY(group_id) REFERENCES cattle_groups(id),
  FOREIGN KEY(location_id) REFERENCES locations(id),
  FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id)
);
CREATE TABLE closing_movements(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  closing_id BIGINT NOT NULL,
  group_id INT NOT NULL,
  animal_id INT NULL,
  movement_type ENUM('PURCHASE','BIRTH','DEATH','SALE','TRANSFER_IN','TRANSFER_OUT','SUPPLY_COST','LABOR_COST') NOT NULL,
  movement_date DATE NOT NULL,
  source_id BIGINT NOT NULL,
  amount DECIMAL(16,2) NOT NULL,
  details VARCHAR(500) NULL,
  INDEX(closing_id,group_id,movement_type),
  FOREIGN KEY(closing_id) REFERENCES monthly_closings(id),
  FOREIGN KEY(group_id) REFERENCES cattle_groups(id),
  FOREIGN KEY(animal_id) REFERENCES animals(id)
);
CREATE TABLE closing_audit(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  closing_id BIGINT NOT NULL,
  action_type ENUM('CLOSED','REOPENED') NOT NULL,
  action_by VARCHAR(120) NOT NULL,
  reason VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(closing_id) REFERENCES monthly_closings(id)
);
CREATE TABLE animal_history(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  action_type ENUM('BIRTH','PURCHASE','CREATED','UPDATED','DEATH','TRANSFER','STATUS_CHANGE') NOT NULL,
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
ALTER TABLE transfers ADD CONSTRAINT fk_transfers_rule_set FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id);
INSERT IGNORE INTO cattle_groups(name,sort_order) VALUES ('Ganado de Engorde (Montezuma)',10),('Ganado de Cría y Desarrollo',20);
INSERT IGNORE INTO locations(name,sort_order) VALUES ('Montezuma',10),('El Viejo',20),('La Flor',30),('Otras',40);
INSERT IGNORE INTO activities(activity_code,name) VALUES ('ALIMENTACION','Alimentación'),('MANEJO','Manejo de ganado'),('SANIDAD','Sanidad y veterinaria'),('MANTENIMIENTO','Mantenimiento'),('ADMINISTRACION','Administración'),('OTRA','Otra actividad');
INSERT IGNORE INTO animals(tag,group_id,location_id,sex,birth_date,acquisition_date,initial_weight_kg,origin,purchase_value) VALUES
('EV-26001',2,2,'F','2026-03-01','2026-03-01',25,'NACIMIENTO',NULL),('EV-26002',2,3,'M','2026-04-10','2026-04-10',25,'NACIMIENTO',NULL),('MZ-25031',1,1,'M','2025-09-15','2025-09-15',25,'TRANSFERENCIA',NULL),('MZ-25032',1,1,'M','2025-10-03','2025-10-03',25,'TRANSFERENCIA',NULL);
INSERT INTO weights(animal_id,group_id,weight_date,weight_kg,source) VALUES (1,2,'2026-09-01',248,'MANUAL'),(2,2,'2026-09-01',205,'MANUAL'),(3,1,'2026-09-01',532,'MANUAL'),(4,1,'2026-09-01',515,'MANUAL');
INSERT INTO rule_sets(name,version,status,effective_from,change_reason,created_by,approved_by,approved_at)
VALUES ('Modelo de control de ganado','1.0.0','ACTIVE','2026-01-01','Modelo inicial basado en los requerimientos del 03-09-2026','system','Modelo inicial',CURRENT_TIMESTAMP);
SET @initial_rule_set_id = LAST_INSERT_ID();
INSERT INTO rule_parameters(rule_set_id,rule_key,rule_value,value_type,unit,description) VALUES
(@initial_rule_set_id,'projection_method','STAGED_GROWTH','STRING',NULL,'Método de proyección de peso'),
(@initial_rule_set_id,'birth_weight_kg','25','DECIMAL','kg','Peso estimado al nacimiento'),
(@initial_rule_set_id,'weaning_age_days','183','INTEGER','días','Duración del primer tramo de crecimiento'),
(@initial_rule_set_id,'gain_0_6_kg_day','1.25','DECIMAL','kg/día','Ganancia diaria en el primer tramo'),
(@initial_rule_set_id,'target_weaning_kg','250','DECIMAL','kg','Meta gerencial de peso al destete'),
(@initial_rule_set_id,'gain_6_plus_kg_day','0.55','DECIMAL','kg/día','Ganancia diaria posterior al destete'),
(@initial_rule_set_id,'target_sale_kg','600','DECIMAL','kg','Peso objetivo para venta'),
(@initial_rule_set_id,'price_per_kg','1500','DECIMAL','CRC/kg','Precio proyectado por kilogramo'),
(@initial_rule_set_id,'max_weight_age_days','60','INTEGER','días','Antigüedad máxima deseada del pesaje'),
(@initial_rule_set_id,'minimum_monthly_weight_coverage','50','DECIMAL','%','Cobertura mínima mensual de pesaje'),
(@initial_rule_set_id,'financial_rate_annual','0.08','DECIMAL','proporción','Tasa financiera anual'),
(@initial_rule_set_id,'financial_base_method','AVERAGE_ASSET','STRING',NULL,'Base para calcular la carga financiera');
INSERT INTO animal_history(animal_id,action_type,action_date,group_id,location_id,status,weight_kg,value_crc,rule_set_id,details,created_by)
SELECT id,CASE origin WHEN 'NACIMIENTO' THEN 'BIRTH' WHEN 'COMPRA' THEN 'PURCHASE' ELSE 'CREATED' END,
       acquisition_date,group_id,location_id,status,initial_weight_kg,purchase_value,@initial_rule_set_id,
       '{"seed":"schema.sql"}','system'
  FROM animals;
CREATE INDEX idx_animals_status_group ON animals(status,group_id);
