-- Gestión de bodega: productos, existencias, costo promedio y kardex.
CREATE TABLE warehouse_products(
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  cost_type ENUM('INSUMO','MATERIAL','VETERINARIO','ALIMENTACION','OTRO') NOT NULL,
  unit VARCHAR(30) NOT NULL,
  minimum_stock DECIMAL(12,2) NOT NULL DEFAULT 0,
  current_stock DECIMAL(12,2) NOT NULL DEFAULT 0,
  average_unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE costs
  ADD COLUMN warehouse_product_id INT NULL,
  ADD CONSTRAINT fk_costs_warehouse_product FOREIGN KEY(warehouse_product_id) REFERENCES warehouse_products(id);

CREATE TABLE warehouse_movements(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  movement_date DATE NOT NULL,
  movement_type ENUM('ENTRY','ISSUE','ADJUSTMENT_IN','ADJUSTMENT_OUT') NOT NULL,
  quantity DECIMAL(12,2) NOT NULL,
  unit VARCHAR(30) NOT NULL,
  unit_cost DECIMAL(14,2) NOT NULL,
  total_value DECIMAL(16,2) NOT NULL,
  stock_after DECIMAL(12,2) NOT NULL,
  group_id INT NULL,
  activity_id INT NULL,
  cost_id BIGINT NULL,
  reference VARCHAR(120) NULL,
  notes VARCHAR(500) NULL,
  created_by VARCHAR(120) NOT NULL DEFAULT 'system',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX(product_id,movement_date),
  INDEX(cost_id),
  FOREIGN KEY(product_id) REFERENCES warehouse_products(id),
  FOREIGN KEY(group_id) REFERENCES cattle_groups(id),
  FOREIGN KEY(activity_id) REFERENCES activities(id),
  FOREIGN KEY(cost_id) REFERENCES costs(id)
);
