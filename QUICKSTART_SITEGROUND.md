# Quickstart de instalación en SiteGround

**Versión de esta distribución:** 1.1.0

Este procedimiento instala el Sistema de Gestión y Control de Ganado como una aplicación PHP independiente. Se recomienda usar un dominio o subdominio exclusivo, por ejemplo `ganaderia.sudominio.com`, y no mezclar sus archivos con una instalación de WordPress.

Tiempo estimado: 20–30 minutos.

## 1. Preparar el paquete

La entrega oficial ya incluye el archivo
`sistema-gestion-ganado-siteground-v1.1.0.zip`. Puede validar que no fue
alterado comparando su SHA-256 con el archivo `.sha256` adjunto.

Comprima el contenido de `ganaderia_el_viejo_web`, no solamente la carpeta exterior. El ZIP debe contener `index.php`, `.htaccess`, `assets/`, `includes/` y `sql/` en su primer nivel.

No incluya estos elementos:

- `config.php` de otra instalación.
- `.git/`, `node_modules/`, `test-results/` o `playwright-report/`.
- Respaldos SQL o archivos `.env` con credenciales reales.

## 2. Crear el sitio o subdominio

Desde el Área de Cliente de SiteGround abra **Websites → Site Tools** del sitio elegido. Si usará un subdominio, créelo antes de continuar y confirme cuál es su carpeta raíz.

En **Devs → PHP Manager → PHP Setup**, deje **Managed PHP** o seleccione PHP 8.2 o posterior. La aplicación requiere PHP 8.0 como mínimo y fue validada con PHP 8.2.

## 3. Crear la base MySQL

Abra **Site Tools → Site → MySQL**:

1. En **Databases**, seleccione **Create Database** y copie el nombre generado.
2. En **Users**, seleccione **Create User** y guarde el usuario y la contraseña generados.
3. En **Manage Users**, agregue el usuario a la nueva base de datos con todos los privilegios necesarios.

SiteGround genera los nombres de base y usuario. Deben copiarse completos, incluido cualquier prefijo.

## 4. Importar el esquema

1. Abra **Site → MySQL → phpMyAdmin → Access phpMyAdmin**.
2. Seleccione la base recién creada en el panel izquierdo.
3. Abra la pestaña **Import**.
4. Seleccione `sql/schema.sql`, use UTF-8 y ejecute la importación.
5. Confirme que aparezcan, entre otras, las tablas `animals`, `rule_sets`, `monthly_closings`, `warehouse_products`, `warehouse_movements`, `users` y `user_activity_log`.

`schema.sql` ya contiene todas las migraciones hasta la versión 1.1.0. En una instalación nueva no ejecute además los archivos de `sql/migrations/`.

## 5. Subir los archivos

1. Abra **Site Tools → Site → File Manager**.
2. Entre en la raíz pública del dominio o subdominio. Para el dominio principal normalmente es `public_html`.
3. Suba el ZIP, extráigalo y verifique que `index.php` quede directamente en esa raíz, no dentro de una carpeta adicional.
4. Confirme que `.htaccess` fue extraído. El archivo impide el acceso web a configuración, scripts, SQL, documentación y pruebas.

SiteGround usa normalmente permisos `755` para carpetas y `644` para archivos. No use permisos `777`.

## 6. Configurar la conexión

En File Manager, copie `config.sample.php` como `config.php`. Reemplace su contenido con esta plantilla y complete los valores reales:

```php
<?php
return [
  'environment' => 'production',
  'db_host' => 'localhost',
  'db_port' => 3306,
  'db_name' => 'NOMBRE_GENERADO_POR_SITEGROUND',
  'db_user' => 'USUARIO_GENERADO_POR_SITEGROUND',
  'db_pass' => 'CONTRASENA_GENERADA_POR_SITEGROUND',
  'base_url' => 'https://ganaderia.sudominio.com',
  'app_name' => 'Sistema de Gestión y Control de Ganado',
  'integration_api_key' => '',
];
```

No active `integration_api_key` hasta que vaya a conectar formalmente el sistema externo de pesajes. Si la activa, use una clave aleatoria larga y entréguela mediante un canal seguro.

Después de guardar, compruebe desde una ventana privada que visitar `/config.php` responde **403 Forbidden** y nunca muestra las credenciales.

## 7. Activar HTTPS

1. En **Security → SSL Manager**, confirme que el dominio tenga un certificado válido.
2. Abra **Security → HTTPS Enforce**.
3. Active HTTPS para el dominio o subdominio de la aplicación.
4. Visite la aplicación con `https://` y confirme que no existan advertencias de contenido mixto.

## 8. Crear el administrador inicial

Abra:

```text
https://ganaderia.sudominio.com/setup.php
```

Cree una cuenta administradora con una contraseña única de al menos 12 caracteres. Al finalizar, `setup.php` se deshabilita automáticamente y debe responder 404.

Ingrese en `login.php` y cree cuentas individuales desde **Usuarios**:

- **Administrador:** usuarios, configuración y operación completa.
- **Finanzas:** reglas financieras y cierres.
- **Operación:** animales, pesajes, eventos, bodega, costos, mano de obra, transferencias y ventas.
- **Consulta:** acceso de solo lectura.

## 9. Verificación rápida

Compruebe lo siguiente antes de entregar el sistema:

- El dashboard abre sin errores y muestra la versión activa de reglas.
- Puede registrar un animal de prueba y un pesaje.
- Puede crear un producto de bodega, registrar una entrada y consumir una cantidad desde Costos.
- Los reportes descargan correctamente archivos Excel y PDF.
- Finanzas puede consultar reglas y cierres.
- Consulta recibe acceso denegado al intentar guardar información.
- El menú abre y cierra correctamente desde un teléfono.
- Las tablas se desplazan horizontalmente dentro de su tarjeta, sin mover toda la página.
- Cerrar sesión regresa a `login.php`.

Si dispone de SSH, ejecute desde la carpeta de la aplicación:

```bash
php scripts/healthcheck.php
```

El resultado debe indicar `database: ok`, `active_rule: ok` y `users: ok`.

## 10. Respaldos y monitoreo

SiteGround mantiene respaldos automáticos según el plan. Antes de cada actualización abra **Security → Backups** y cree un respaldo manual cuando su plan lo permita.

Opcionalmente, en **Devs → Cron Jobs**, programe el chequeo cada cinco minutos. Sustituya `DOMINIO` por la ruta real mostrada en File Manager:

```bash
php /home/customer/www/DOMINIO/public_html/scripts/healthcheck.php >/dev/null || echo "Ganadería: healthcheck falló"
```

Configure un correo en el cron para recibir avisos cuando el comando falle. Pruebe también una restauración de base de datos en un entorno aislado al menos una vez al mes.

## Actualizar una instalación existente

No vuelva a importar `schema.sql` sobre una instalación con datos.

1. Cree un respaldo de archivos y base de datos en **Security → Backups**.
2. Conserve una copia del `config.php` actual.
3. Suba los archivos nuevos sin reemplazar `config.php`.
4. En phpMyAdmin seleccione la base existente e importe solamente las migraciones pendientes, en orden. Para pasar del sprint 5 al 6 ejecute `sql/migrations/006_security.sql`; después aplique `007_branding.sql`, `008_editable_catalogs.sql` y `009_warehouse_management.sql`. La última migración debe ejecutarse antes de subir los archivos PHP de la versión 1.1.0.
5. Abra `setup.php` para crear el primer administrador si todavía no existe ningún usuario.
6. Ejecute el chequeo de salud y complete la verificación rápida anterior.

## Diagnóstico breve

| Problema | Revisión |
|---|---|
| “No fue posible conectar con la base de datos” | Revise nombre completo de base, usuario, contraseña, asignación del usuario y `db_host=localhost`. |
| Error 500 | Consulte **Statistics → Error Log** y confirme la versión de PHP. No active `display_errors` públicamente. |
| 403 en toda la aplicación | Confirme que los archivos estén en la raíz correcta y que `.htaccess` no haya sido modificado. |
| `setup.php` responde 404 | Ya existe al menos un usuario; ingrese por `login.php`. |
| Formularios indican sesión vencida | Borre cookies del dominio, confirme HTTPS y vuelva a iniciar sesión. |
| La importación SQL falla | Confirme que seleccionó la base antes de importar y que usa el `schema.sql` de esta versión. |

## Referencias oficiales de SiteGround

- [Crear base de datos y usuario MySQL](https://www.siteground.com/tutorials/php-mysql/create-user-database/)
- [Acceder a phpMyAdmin](https://www.siteground.com/kb/access-mysql-database/)
- [Administrar y subir archivos](https://www.siteground.com/kb/manage-files-file-manager/)
- [Forzar HTTPS](https://www.siteground.com/kb/how-do-i-enforce-https/)
- [Administrar respaldos](https://www.siteground.com/kb/backup-service/)
- [Crear tareas cron](https://www.siteground.com/kb/manage-cron-jobs/)
