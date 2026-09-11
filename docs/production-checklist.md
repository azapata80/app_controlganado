# Lista de salida a producción

## Despliegue

1. Tome un respaldo completo de archivos y base de datos.
2. Ejecute las migraciones pendientes, en orden. Para Sprint 6 corresponde `sql/migrations/006_security.sql`.
3. Publique el código y abra `setup.php` para crear el primer administrador. La ruta se deshabilita después de crear la cuenta.
4. Configure `GANADERIA_ENV=production`, las variables `GANADERIA_DB_*`, `GANADERIA_BASE_URL` y, solo si se usa integración, `GANADERIA_API_KEY`.
5. Obligue HTTPS desde el servidor web. Verifique que el certificado y la renovación automática estén activos.
6. Restrinja el acceso web a `config.php`, `sql/`, `tests/`, `scripts/` y `docs/`; idealmente manténgalos fuera de la raíz pública.

## Respaldo y recuperación

- Programe un respaldo diario de MySQL y semanal de archivos desde SiteGround/cPanel o el proveedor administrado.
- Mantenga al menos 30 respaldos diarios y 12 mensuales, cifrados y en una ubicación distinta al servidor.
- No escriba la contraseña MySQL en comandos, cron ni nombres de archivo. Use el almacén de secretos del proveedor.
- Una vez al mes restaure el respaldo más reciente en una base aislada y ejecute `php scripts/healthcheck.php`. Un respaldo no se considera válido hasta probar su restauración.
- Documente responsable, fecha, tamaño, resultado de restauración y tiempo de recuperación.

## Operación y monitoreo

- Ejecute `php scripts/healthcheck.php` cada cinco minutos desde el monitor del hosting. Código 0 significa saludable; código 1 requiere intervención.
- Active alertas por errores HTTP 5xx, espacio de disco, expiración TLS, fallo del cron de respaldo y base de datos sin respuesta.
- Revise semanalmente **Usuarios → Actividad reciente**, cuentas bloqueadas y usuarios inactivos.
- Cree cuentas individuales; no comparta la cuenta administradora. Finanzas administra reglas y cierres, Operación registra movimientos y Consulta solo lee.
- Cambie contraseñas temporales al entregarlas por un canal seguro y desactive de inmediato las cuentas que ya no se usen.

## Validación posterior

- Ingrese con cada perfil y confirme sus permisos.
- Pruebe dashboard, animal, pesaje, costo, traslado, venta, cierre y exportación CSV.
- Revise a 320, 390, 768 y 1366 px, además de Android e iOS reales cuando estén disponibles.
- Confirme que no hay contenido mixto, recursos CDN ni secretos expuestos en respuestas o registros.
