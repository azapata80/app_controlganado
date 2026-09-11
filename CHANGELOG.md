# Historial de versiones

## 1.1.0 — 11 de septiembre de 2026

### Incorporado

- Lanzador de aplicaciones responsive con accesos según el perfil.
- Gestión de bodega con productos, entradas, ajustes, existencias mínimas,
  costo promedio ponderado y kardex.
- Salidas de bodega integradas con costos por grupo y actividad.
- Reportes de diez conjuntos de datos con filtros por fecha y exportación a
  Excel o PDF.
- Centro de ayuda y manual de usuario integrado.
- Administración editable de ubicaciones y grupos ganaderos.
- Identidad visual de Sistema de Gestión y Control de Ganado.
- Datos demostrativos coherentes y cargador independiente para bodega.
- Archivo `sql/demo_data.sql` para cargar el escenario completo desde
  phpMyAdmin sin requerir acceso SSH.
- Documento de especificaciones funcionales, limitaciones, preparación
  comercial y evolución recomendada del producto.

### Seguridad y calidad

- Validación transaccional para impedir inventario negativo.
- Conservación de unidad, costo y existencia resultante en cada movimiento.
- Pruebas responsive en 320, 390, 768 y 1366 px.
- Verificación automatizada de exportaciones, catálogos y ciclo de bodega.

### Base de datos

- Nueva migración `009_warehouse_management.sql` para instalaciones existentes.
- `schema.sql` actualizado para instalaciones nuevas.
