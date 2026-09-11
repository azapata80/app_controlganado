# Resumen de avance de la aplicación

**Proyecto:** Sistema de Gestión y Control de Ganado<br>
**Fecha de actualización:** 11 de septiembre de 2026<br>
**Estado actual:** versión funcional disponible en ambiente Docker local

## 1. Objetivo

La aplicación centraliza el control operativo y financiero del ganado. Permite
administrar animales, pesajes, eventos, costos, personal, traslados, ventas,
resultados y cierres mensuales desde computadoras, tabletas y teléfonos Android
o iOS.

El modelo productivo y financiero se diseñó mediante reglas configurables para
que Finanzas pueda modificar sus parámetros sin reprogramar los cálculos ni
perder la trazabilidad histórica.

## 2. Resumen ejecutivo

| Área | Estado | Resultado |
|---|---|---|
| Operación ganadera | Completada | Inventario, ciclo de vida, pesajes y eventos |
| Costos y personal | Completada | Materiales, actividades, empleados, tarifas y horas |
| Movimientos y ventas | Completada | Traslados entre grupos, valorización y ventas |
| Modelo financiero | Completada | Reglas versionadas, simulación y vigencias |
| Cierre mensual | Completada | Simulación, conciliación, cierre, reapertura y exportación |
| Seguridad | Completada | Usuarios, perfiles, sesiones, CSRF, bloqueo y auditoría |
| Catálogos | Completada | Ubicaciones y grupos editables con validación de referencias |
| Experiencia de usuario | Completada | Diseño responsive, lanzador de aplicaciones, ayuda y manual |
| Reportes exportables | Completada | Filtros por fecha, vista previa y descargas en Excel o PDF |
| Gestión de bodega | Completada | Productos, entradas, salidas, ajustes, mínimos y kardex |
| Despliegue | Disponible | Guías para Docker y SiteGround |
| Validación final de negocio | Pendiente | Pruebas de aceptación con usuarios y datos reales |

## 3. Avance acumulado por sprint

### Sprint 1 — Base funcional y experiencia responsive

- Estructura inicial de la aplicación en PHP y MySQL.
- Panel de control con indicadores y alertas.
- Interfaz adaptable a escritorio, tableta, Android e iPhone desde 320 px.
- Formularios adaptativos y tablas con desplazamiento horizontal controlado.
- Controles táctiles con altura mínima recomendada de 44 px.

### Sprint 2 — Ciclo de vida de los animales

- Registro y edición del inventario de animales.
- Ingreso por nacimiento, compra o transferencia.
- Registro automático del peso inicial según la operación.
- Manejo de muertes y bajas con valorización de salida.
- Historial de cambios por animal.

### Sprint 3 — Pesajes e integración

- Registro manual de pesajes.
- Proyección de peso usando la regla vigente.
- Importación de archivos CSV con vista previa.
- Validaciones de aretes, fechas, duplicados y estado del animal.
- API de integración protegida mediante clave y deshabilitada por defecto.

### Sprint 4 — Costos, personal y traslados

- Registro de insumos, materiales, cantidades y costos unitarios.
- Administración de empleados, actividades, tarifas y horas trabajadas.
- Conservación de la tarifa histórica aplicada a cada registro.
- Traslados entre grupos con peso real o proyectado.
- Partidas equivalentes de salida y entrada para conservar la trazabilidad.

### Sprint 5 — Resultados y cierres mensuales

- Estado de resultados y valorización por grupo.
- Simulación del cierre antes de confirmarlo.
- Conciliación de saldos y movimientos.
- Bloqueo de transacciones correspondientes a períodos cerrados.
- Reapertura controlada, con motivo y bitácora.
- Descarga CSV y vista imprimible para generar PDF.

### Sprint 6 — Seguridad y preparación para producción

- Inicio y cierre de sesión seguros.
- Perfiles: Administrador, Operación, Finanzas y Consulta.
- Autorización de operaciones según el perfil.
- Protección CSRF, endurecimiento de sesión y encabezados de seguridad.
- Bloqueo temporal después de intentos fallidos de acceso.
- Bitácora de actividad y contraseñas almacenadas con `password_hash`.
- Lista de verificación para producción y chequeo automatizado de salud.

## 4. Mejoras adicionales incorporadas

### Reglas configurables

- Versiones en borrador y activación por fecha de vigencia.
- Conservación de reglas anteriores para reproducir cálculos históricos.
- Simulación antes de activar una nueva versión.
- Parámetros iniciales para peso al nacimiento, crecimiento por tramos, precio
  por kilogramo, peso objetivo, carga financiera y cobertura de pesaje.

### Catálogos administrativos

- Panel para administrar ubicaciones y grupos ganaderos.
- Creación, cambio de nombre, orden, activación y desactivación.
- Eliminación permanente solamente cuando el elemento no tiene referencias.
- Detalle de referencias en animales, valorizaciones e historial.
- Presentación tipo tabla con acciones continuas y textos en español.

### Identidad visual y navegación

- Nombre actualizado a **Sistema de Gestión y Control de Ganado**.
- Uso del logotipo proporcionado para Control de Ganado.
- Paleta institucional azul, verde y lima.
- Pantalla inicial **Aplicaciones**, inspirada en un lanzador de módulos.
- Módulos agrupados por Gestión diaria, Operación y resultados, y
  Configuración y soporte.
- Accesos visibles según el perfil del usuario.
- Barra superior simplificada con Aplicaciones, Panel y Ayuda.

### Ayuda al usuario

- Centro de ayuda con búsqueda y accesos rápidos.
- Manual de usuario completo dentro de la aplicación.
- Manual preparado para impresión o almacenamiento como PDF.

### Reportes exportables

- Consulta de Animales, Pesajes, Eventos, Costos, Mano de obra, Traslados,
  Ventas y Cierres.
- Filtro por rango de fechas o selección de todos los registros.
- Vista previa de hasta 200 filas para conservar una navegación ágil.
- Descarga del resultado completo en Excel o PDF.
- Columnas y valores presentados en español, con nombres relacionados en lugar
  de identificadores internos.
- Registro de cada exportación en la bitácora de actividad.

### Gestión de bodega

- Catálogo editable de productos con código, categoría, unidad y existencia mínima.
- Entradas por compra y ajustes positivos o negativos.
- Existencias y valorización mediante costo promedio ponderado.
- Salidas por consumo integradas con los costos por grupo y actividad.
- Validación transaccional para impedir existencias negativas.
- Kardex con cantidades, costos, valores, referencias y responsables.
- Reportes de existencias y movimientos descargables en Excel o PDF.

### Datos demostrativos

- Cargador reproducible de información demo congruente.
- Escenario actual con 12 animales, 48 pesajes, eventos, costos, personal,
  traslados, una venta y un cierre mensual conciliado.
- Catálogos demo: Montezuma, La Flor y Otras.
- La carga demo conserva usuarios y reglas existentes.

## 5. Arquitectura y despliegue

- Aplicación web en PHP 8.2 con Apache.
- Base de datos MariaDB 10.11, compatible con MySQL.
- Contenedores Docker separados para aplicación y base de datos.
- Datos persistentes mediante volumen Docker.
- La base de datos no se publica directamente hacia el equipo anfitrión.
- Guía de instalación para Docker Desktop en `QUICKSTART_DOCKER.md`.
- Guía de instalación en hosting SiteGround en `QUICKSTART_SITEGROUND.md`.
- Ocho migraciones incrementales disponibles para instalaciones existentes.

La versión Docker se encuentra disponible localmente en:

```text
http://localhost:8080
```

## 6. Validaciones realizadas

- 20 pruebas automatizadas de reglas, fechas, proyecciones y cálculos superadas.
- Validación de sintaxis PHP en todos los archivos de la aplicación.
- Pruebas automatizadas de navegador en anchos de 320, 390, 768 y 1366 px.
- Validación del menú móvil, centro de ayuda, manual y mantenimiento de catálogos.
- Pruebas de ausencia de desbordamientos no controlados.
- Contenedores de aplicación y base de datos con estado saludable.
- Chequeo de conectividad, reglas activas y usuarios mediante `healthcheck.php`.

## 7. Documentación disponible

| Documento | Propósito |
|---|---|
| `README.md` | Descripción técnica y funcional general |
| `QUICKSTART_DOCKER.md` | Instalación y operación con Docker |
| `QUICKSTART_SITEGROUND.md` | Despliegue en SiteGround |
| `manual.php` | Manual para usuarios finales |
| `docs/weight-integration-api.md` | Integración externa de pesajes |
| `docs/production-checklist.md` | Preparación y control de producción |

## 8. Próximos pasos recomendados

1. Ejecutar pruebas de aceptación con Operación, Finanzas y Administración.
2. Confirmar las reglas iniciales con datos financieros y productivos reales.
3. Preparar y depurar los datos que se importarán al ambiente productivo.
4. Validar la aplicación en dispositivos físicos Android y iPhone utilizados en
   campo, especialmente bajo conexiones lentas.
5. Configurar dominio, certificado HTTPS, respaldos automáticos y monitoreo.
6. Probar una restauración completa de la base de datos antes de salir a
   producción.
7. Definir si la integración de pesajes será manual por CSV, automática por API
   o mediante ambos mecanismos.

## 9. Consideraciones antes de producción

- No utilizar datos demostrativos en el ambiente productivo.
- Cambiar todas las contraseñas y secretos definidos durante la instalación.
- Habilitar la API solamente cuando exista un sistema externo autorizado.
- Mantener copias de seguridad fuera del servidor principal.
- Ejecutar las migraciones en orden y respaldar la base antes de cada
  actualización.
- Capacitar a cada usuario únicamente en los módulos disponibles para su perfil.
