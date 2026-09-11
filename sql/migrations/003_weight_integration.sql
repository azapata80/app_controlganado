-- Sprint 3: lotes de importación, trazabilidad y prevención de duplicados.
-- Antes de ejecutarla, revise posibles duplicados con:
-- SELECT animal_id,weight_date,source,COUNT(*) cantidad FROM weights
-- GROUP BY animal_id,weight_date,source HAVING COUNT(*)>1;

CREATE TABLE weight_import_batches(
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  channel ENUM('CSV','API') NOT NULL,
  source_name VARCHAR(255) NOT NULL,
  status ENUM('IMPORTED','REJECTED') NOT NULL,
  total_rows INT NOT NULL,
  imported_rows INT NOT NULL DEFAULT 0,
  rejected_rows INT NOT NULL DEFAULT 0,
  created_by VARCHAR(120) NOT NULL DEFAULT 'system',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE weights
  ADD COLUMN external_reference VARCHAR(120) NULL AFTER source,
  ADD COLUMN import_batch_id BIGINT NULL AFTER external_reference,
  ADD CONSTRAINT fk_weights_import_batch FOREIGN KEY(import_batch_id) REFERENCES weight_import_batches(id),
  ADD UNIQUE INDEX uq_weight_animal_date_source(animal_id,weight_date,source);
