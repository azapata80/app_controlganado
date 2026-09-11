-- Sprint 5: cierres mensuales inmutables, valorizaciones y auditoría.
-- Ejecute una sola vez después de las migraciones 001 a 004.

CREATE TABLE monthly_closings(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,month_end DATE NOT NULL UNIQUE,status ENUM('CLOSED','REOPENED') NOT NULL,
  rule_set_id BIGINT NULL,rules_snapshot LONGTEXT NOT NULL,rules_hash CHAR(64) NOT NULL,
  opening_asset DECIMAL(16,2) NOT NULL,closing_asset DECIMAL(16,2) NOT NULL,biological_result DECIMAL(16,2) NOT NULL,
  operating_result DECIMAL(16,2) NOT NULL,financial_charge DECIMAL(16,2) NOT NULL,adjusted_result DECIMAL(16,2) NOT NULL,
  closed_by VARCHAR(120) NOT NULL,closed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,reopened_by VARCHAR(120) NULL,
  reopened_at TIMESTAMP NULL,reopen_reason VARCHAR(500) NULL,FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id)
);
CREATE TABLE monthly_closing_groups(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,closing_id BIGINT NOT NULL,group_id INT NOT NULL,group_name VARCHAR(120) NOT NULL,
  opening_asset DECIMAL(16,2) NOT NULL,closing_asset DECIMAL(16,2) NOT NULL,purchases DECIMAL(16,2) NOT NULL,births DECIMAL(16,2) NOT NULL,
  deaths DECIMAL(16,2) NOT NULL,external_sales DECIMAL(16,2) NOT NULL,projected_sales DECIMAL(16,2) NOT NULL,sale_variance DECIMAL(16,2) NOT NULL,
  transfer_in DECIMAL(16,2) NOT NULL,transfer_out DECIMAL(16,2) NOT NULL,valuation_change DECIMAL(16,2) NOT NULL,
  supplies_cost DECIMAL(16,2) NOT NULL,labor_cost DECIMAL(16,2) NOT NULL,biological_result DECIMAL(16,2) NOT NULL,
  operating_result DECIMAL(16,2) NOT NULL,financial_charge DECIMAL(16,2) NOT NULL,adjusted_result DECIMAL(16,2) NOT NULL,
  reconciliation_difference DECIMAL(16,2) NOT NULL DEFAULT 0,UNIQUE(closing_id,group_id),
  FOREIGN KEY(closing_id) REFERENCES monthly_closings(id),FOREIGN KEY(group_id) REFERENCES cattle_groups(id)
);

-- La tabla anterior se conserva para recuperación; el MVP previo no generaba cierres.
RENAME TABLE monthly_valuations TO monthly_valuations_legacy;
CREATE TABLE monthly_valuations(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,closing_id BIGINT NOT NULL,animal_id INT NOT NULL,group_id INT NOT NULL,location_id INT NULL,
  animal_tag VARCHAR(80) NOT NULL,group_name VARCHAR(120) NOT NULL,location_name VARCHAR(120) NULL,
  month_end DATE NOT NULL,actual_weight_kg DECIMAL(10,2) NULL,actual_weight_date DATE NULL,projected_weight_kg DECIMAL(10,2) NOT NULL,
  valuation_method ENUM('ACTUAL','PROJECTED_FROM_WEIGHT','PROJECTED_FROM_BASE') NOT NULL,price_per_kg DECIMAL(14,2) NOT NULL,
  value_crc DECIMAL(16,2) NOT NULL,rule_set_id BIGINT NULL,UNIQUE(animal_id,month_end),
  FOREIGN KEY(closing_id) REFERENCES monthly_closings(id),FOREIGN KEY(animal_id) REFERENCES animals(id),
  FOREIGN KEY(group_id) REFERENCES cattle_groups(id),FOREIGN KEY(location_id) REFERENCES locations(id),FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id)
);

CREATE TABLE closing_movements(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,closing_id BIGINT NOT NULL,group_id INT NOT NULL,animal_id INT NULL,
  movement_type ENUM('PURCHASE','BIRTH','DEATH','SALE','TRANSFER_IN','TRANSFER_OUT','SUPPLY_COST','LABOR_COST') NOT NULL,
  movement_date DATE NOT NULL,source_id BIGINT NOT NULL,amount DECIMAL(16,2) NOT NULL,details VARCHAR(500) NULL,
  INDEX(closing_id,group_id,movement_type),FOREIGN KEY(closing_id) REFERENCES monthly_closings(id),
  FOREIGN KEY(group_id) REFERENCES cattle_groups(id),FOREIGN KEY(animal_id) REFERENCES animals(id)
);
CREATE TABLE closing_audit(id BIGINT AUTO_INCREMENT PRIMARY KEY,closing_id BIGINT NOT NULL,action_type ENUM('CLOSED','REOPENED') NOT NULL,action_by VARCHAR(120) NOT NULL,reason VARCHAR(500) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(closing_id) REFERENCES monthly_closings(id));
