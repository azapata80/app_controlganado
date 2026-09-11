-- Sprint 1: motor de reglas versionadas para una base ya instalada.
-- Ejecute este archivo después de seleccionar la base configurada para la aplicación.

CREATE TABLE IF NOT EXISTS rule_sets(
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

CREATE TABLE IF NOT EXISTS rule_parameters(
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

INSERT INTO rule_sets(name,version,status,effective_from,change_reason,created_by,approved_by,approved_at)
SELECT 'Modelo de control de ganado','1.0.0','ACTIVE','2026-01-01',
       'Modelo inicial basado en los requerimientos del 03-09-2026','system','Modelo inicial',CURRENT_TIMESTAMP
WHERE NOT EXISTS (SELECT 1 FROM rule_sets WHERE version='1.0.0');

SET @initial_rule_set_id = (SELECT id FROM rule_sets WHERE version='1.0.0' LIMIT 1);
INSERT IGNORE INTO rule_parameters(rule_set_id,rule_key,rule_value,value_type,unit,description) VALUES
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
