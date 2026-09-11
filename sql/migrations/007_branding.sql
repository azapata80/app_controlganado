-- Retira el nombre de la identidad anterior sin modificar ubicaciones operativas.
UPDATE rule_sets
SET name = 'Modelo de control de ganado'
WHERE name = 'Modelo Ganadería El Viejo';
