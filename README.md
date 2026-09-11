# Sistema de Gestión y Control de Ganado

**Versión actual:** 1.1.0

La distribución lista para SiteGround se encuentra en
[`releases/sistema-gestion-ganado-siteground-v1.1.0.zip`](releases/sistema-gestion-ganado-siteground-v1.1.0.zip),
acompañada por su suma de verificación SHA-256.

MVP web para control gerencial de ganadería, diseñado para funcionar correctamente tanto en computadora como en celular y para desplegarse en hosting tradicional (SiteGround/cPanel) con PHP + MySQL.

Para un despliegue paso a paso en SiteGround consulte
[`QUICKSTART_SITEGROUND.md`](QUICKSTART_SITEGROUND.md).

Para ejecutar la aplicación con PHP/Apache y MariaDB en contenedores consulte
[`QUICKSTART_DOCKER.md`](QUICKSTART_DOCKER.md).

Para conocer el estado funcional, técnico y los próximos pasos consulte
[`RESUMEN_AVANCE.md`](RESUMEN_AVANCE.md).

Para revisar las especificaciones vigentes, limitaciones y propuesta de
evolución comercial consulte
[`ESPECIFICACIONES_Y_EVOLUCION_COMERCIAL.md`](ESPECIFICACIONES_Y_EVOLUCION_COMERCIAL.md).

## Requisitos
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Hosting con SSL recomendado

## Instalación rápida
1. Cree una base de datos MySQL desde SiteGround Site Tools o cPanel.
2. Seleccione la base creada e importe `sql/schema.sql`.
3. Copie `config.sample.php` como `config.php`.
4. Configure host, base, usuario y contraseña MySQL en `config.php`.
5. Suba la carpeta completa al directorio público del dominio o subdominio.
6. Abra `setup.php`, cree el administrador inicial e ingrese al sistema.

Para actualizar una instalación existente, seleccione primero la base de datos
en phpMyAdmin y ejecute, en orden, los archivos de `sql/migrations/`.

## Configuración por ambiente

`config.php` admite las siguientes variables de ambiente:

- `GANADERIA_ENV`
- `GANADERIA_DB_HOST`, `GANADERIA_DB_PORT`, `GANADERIA_DB_NAME`
- `GANADERIA_DB_USER`, `GANADERIA_DB_PASS`
- `GANADERIA_BASE_URL`, `GANADERIA_APP_NAME`
- `GANADERIA_API_KEY` para habilitar la API de pesajes

`config.php` y los archivos `.env` están excluidos del repositorio para evitar
publicar credenciales.

## Reglas configurables

La pantalla **Reglas** permite simular el modelo, guardar nuevas versiones en
borrador y activarlas con una fecha de vigencia. Una activación futura conserva
la versión anterior hasta el día previo y los cálculos históricos pueden cargar
la regla que correspondía a cualquier fecha.

El modelo inicial usa crecimiento por tramos: 25 kg al nacimiento, 1,25 kg/día
durante 183 días y 0,55 kg/día posteriormente. Los 250 kg son una meta gerencial,
no un reinicio de la curva.

## Ciclo de vida de animales

- Un nacimiento crea el animal, su pesaje inicial y el evento de nacimiento en
  una sola transacción. El peso inicial proviene de la regla vigente.
- Una compra registra fecha, peso y valor de adquisición, además del pesaje
  inicial correspondiente.
- Una muerte usa el último pesaje anterior al evento, proyecta el peso hasta esa
  fecha y calcula automáticamente el valor que sale del activo.
- Las modificaciones conservan una instantánea anterior y posterior en
  `animal_history`. Las bajas guardan la versión de reglas utilizada.

En una instalación actualizada ejecute `002_animal_lifecycle.sql` después de la
migración `001_configurable_rules.sql`.

## Integración de pesajes

La pantalla **Pesajes → Importar CSV** permite revisar un archivo antes de
guardarlo. Detecta aretes inexistentes, fechas inválidas, animales inactivos y
duplicados en el archivo o la base. La API para el sistema existente se documenta
en `docs/weight-integration-api.md` y permanece deshabilitada hasta configurar
`GANADERIA_API_KEY`.

Para actualizar una instalación, ejecute `003_weight_integration.sql` después de
las migraciones 001 y 002. Revise primero la consulta de duplicados incluida al
inicio del archivo.

## Costos y transferencias internas

- **Personal** administra colaboradores, tarifas y actividades. Cada registro
  de horas conserva la tarifa histórica con la que se calculó.
- **Costos** registra insumos y materiales por actividad, grupo, unidad,
  cantidad y costo unitario.
- **Bodega** administra productos, entradas, ajustes, existencias mínimas,
  costo promedio y kardex. Cada consumo descuenta inventario y crea el costo
  del grupo en una sola transacción; las compras directas continúan disponibles.
- **Transferencias** mueve un animal entre grupos usando el peso real de la
  fecha o el peso proyectado. El precio proviene de la regla vigente y se crean
  dos partidas iguales: salida del origen y entrada del destino.
- Pesajes, eventos y ventas guardan el grupo que correspondía al momento de la
  transacción, por lo que un traslado no modifica el historial.

Para actualizar una instalación ejecute `004_costs_labor_transfers.sql` después
de las migraciones 001, 002 y 003.

La gestión de bodega se incorpora mediante `009_warehouse_management.sql`; en
instalaciones existentes ejecútela después de las migraciones 001 a 008.

## Cierre mensual

La pantalla **Cierres** permite simular y cerrar un mes finalizado. El cierre
guarda reglas, huella SHA-256, saldos por grupo, movimientos y una valorización
individual con nombres, pesos, método y precio utilizados. Un período cerrado
rechaza nuevos pesajes, costos, ventas, compras, muertes, mano de obra y
transferencias.

Las reaperturas requieren un motivo y deben realizarse desde el cierre más
reciente hacia atrás. El historial queda en `closing_audit`. Los resultados se
pueden descargar como CSV o abrir en una vista preparada para imprimir o guardar
como PDF desde Android, iOS y navegadores de escritorio.

La migración `005_monthly_closing.sql` conserva la antigua tabla sin uso como
`monthly_valuations_legacy`; no elimina sus datos. Ejecútela después de las
migraciones 001 a 004.

## Pruebas

Ejecute las pruebas de reglas, fechas, proyecciones y carga financiera con:

```bash
php tests/run.php
```

`tests/integration.php` prueba el ciclo de vida completo y exige una base
desechable junto con `GANADERIA_TEST_DB=1` para impedir su ejecución accidental
contra datos reales.

La prueba responsive automatizada usa Chrome instalado y credenciales de una
base desechable. Instale una vez las dependencias de desarrollo con `npm install`
y luego ejecute:

```bash
GANADERIA_TEST_URL=http://127.0.0.1:8080 GANADERIA_TEST_USER=admin GANADERIA_TEST_PASSWORD=clave npm run test:browser
```

## Reportes exportables

El módulo **Reportes** permite consultar Animales, Pesajes, Eventos, Costos,
Mano de obra, Traslados, Ventas y Cierres por rango de fechas o en su totalidad.
La vista previa muestra hasta 200 filas y las descargas incluyen el resultado
completo en Excel (`.xls`) o PDF.

## Diseño responsive
La interfaz está optimizada para:
- Pantalla inicial **Aplicaciones** con accesos visuales agrupados y visibles según el perfil del usuario.
- Barra superior simplificada con accesos a Aplicaciones, Panel y Ayuda.
- Escritorio y monitores amplios.
- Tablets.
- Teléfonos Android/iPhone desde 320 px de ancho.
- Navegación móvil con menú colapsable.
- Formularios adaptativos de 4, 2 y 1 columna según el ancho disponible.
- Tablas con desplazamiento horizontal táctil cuando el contenido no cabe en la pantalla.
- Botones y controles con altura mínima de 44 px para interacción táctil.
- Indicadores y gráficos CSS responsivos, sin dependencia de CDN.

## Parámetros de negocio incluidos
- Precio proyectado: ₡1.500/kg.
- Peso nacimiento: 25 kg.
- Ganancia 0–6 meses: 1,25 kg/día.
- Ganancia >6 meses: 0,55 kg/día.
- Peso objetivo venta: 600 kg.
- Carga financiera de referencia: 8% anual.
- Cobertura mínima de pesaje mensual: 50%.

## Seguridad y producción

Sprint 6 incorpora autenticación, perfiles Administrador/Operación/Finanzas/Consulta,
bloqueo temporal por intentos fallidos, protección CSRF, bitácora de actividad y
encabezados de seguridad. Las contraseñas se almacenan mediante `password_hash`;
no existe una contraseña predeterminada.

Ejecute `006_security.sql` en instalaciones existentes y siga
`docs/production-checklist.md` para HTTPS, permisos, monitoreo, respaldo y pruebas
de restauración. El chequeo automatizable se ejecuta con
`php scripts/healthcheck.php`.

La identidad visual utiliza el isotipo `assets/logo-control-ganado.png` y
la paleta azul, verde y lima del proyecto. En instalaciones existentes, ejecute
`007_branding.sql` después de `006_security.sql` para actualizar el nombre del
modelo sin alterar las ubicaciones operativas.

Las ubicaciones y los grupos ganaderos se administran desde **Catálogos**. La
migración `008_editable_catalogs.sql` agrega orden y estado activo a ambas listas
sin eliminar referencias históricas.

## Datos demostrativos

Para reemplazar únicamente la información operativa por un escenario demo
coherente, conservando usuarios y reglas, ejecute dentro del contenedor web:

```bash
php scripts/load_demo.php --confirm
```

En SiteGround o cPanel también puede importar `sql/demo_data.sql` directamente
desde phpMyAdmin, después de haber instalado `sql/schema.sql`.

Este comando reinicia animales, pesajes, eventos, costos, personal, traslados,
ventas y cierres. También restablece los catálogos operativos a Montezuma, La Flor
y Otras; úselo solamente en ambientes de demostración.

Para agregar únicamente productos y existencias demo a una bodega vacía, sin
modificar el resto de la aplicación, ejecute:

```bash
php scripts/load_warehouse_demo.php --confirm
```
