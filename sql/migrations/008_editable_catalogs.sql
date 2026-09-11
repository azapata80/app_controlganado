-- Catálogos configurables de ubicaciones y grupos ganaderos.
ALTER TABLE cattle_groups
  ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN sort_order INT NOT NULL DEFAULT 0;

ALTER TABLE locations
  ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN sort_order INT NOT NULL DEFAULT 0;

UPDATE cattle_groups SET sort_order=id*10 WHERE sort_order=0;
UPDATE locations SET sort_order=id*10 WHERE sort_order=0;
