# Quickstart con Docker

Esta distribución ejecuta la aplicación en PHP 8.2 con Apache y MariaDB 10.11. La base no se publica hacia el host, los datos permanecen en un volumen y `schema.sql` se importa automáticamente solamente cuando el volumen está vacío.

## Requisitos

- Docker Engine 24+ con Docker Compose v2, o Docker Desktop actualizado.
- Puertos disponibles: `8080` por defecto.
- Aproximadamente 1 GB de memoria libre para los dos contenedores.

## Instalación nueva

1. Copie el archivo de variables:

   PowerShell:

   ```powershell
   Copy-Item .env.docker.example .env
   ```

   Linux/macOS:

   ```bash
   cp .env.docker.example .env
   ```

2. Edite `.env`. Cambie obligatoriamente `DB_PASSWORD` y `DB_ROOT_PASSWORD`. Si va a publicar la aplicación, cambie también `APP_BASE_URL` por su URL HTTPS.

3. Construya e inicie los servicios:

   ```bash
   docker compose up -d --build
   ```

4. Compruebe el estado:

   ```bash
   docker compose ps
   docker compose logs --tail=100 web
   ```

5. Abra `http://localhost:8080/setup.php`, cree el primer administrador y luego ingrese en `login.php`.

Si cambió `APP_PORT`, utilice ese puerto. `setup.php` se deshabilita automáticamente después de crear el primer usuario.

## Operación habitual

```bash
# Ver estado
docker compose ps

# Ver registros recientes
docker compose logs --tail=100 web db

# Seguir los registros
docker compose logs -f web

# Reiniciar sin perder datos
docker compose restart

# Detener conservando la base
docker compose down

# Volver a iniciar
docker compose up -d
```

No ejecute `docker compose down -v` en una instalación con información real: la opción `-v` elimina el volumen de la base de datos.

## Chequeo de salud

```bash
docker compose exec web php scripts/healthcheck.php
```

Debe mostrar `database: ok`, `active_rule: ok` y `users: ok`. Antes de crear el administrador, el estado `users` será `setup_required`.

Docker también ejecuta healthchecks automáticos. `docker compose ps` debe mostrar ambos servicios como `healthy` una vez concluido el arranque.

## Actualizar el código

1. Genere primero un respaldo.
2. Sustituya los archivos de la aplicación sin reemplazar su `.env`.
3. Reconstruya el contenedor web:

   ```bash
   docker compose up -d --build web
   ```

4. Ejecute únicamente las migraciones pendientes. Por ejemplo, para aplicar Sprint 6:

   PowerShell:

   ```powershell
   Get-Content sql/migrations/006_security.sql -Raw | docker compose exec -T db sh -lc 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"'
   ```

   Linux/macOS:

   ```bash
   docker compose exec -T db sh -lc 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < sql/migrations/006_security.sql
   ```

   Para actualizar el nombre del modelo a la identidad actual, aplique después `sql/migrations/007_branding.sql` con el mismo procedimiento.

5. Ejecute el chequeo de salud y pruebe el flujo afectado.

La carpeta `/docker-entrypoint-initdb.d` no aplica migraciones a volúmenes existentes; funciona únicamente durante la creación inicial de la base.

Para habilitar el panel de ubicaciones y grupos en una instalación existente,
aplique también `sql/migrations/008_editable_catalogs.sql`.

Para incorporar la gestión de bodega en un volumen existente, aplique después
`sql/migrations/009_warehouse_management.sql` con el mismo procedimiento.

## Crear un respaldo

Linux/macOS:

```bash
mkdir -p backups
docker compose exec -T db sh -lc 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --single-transaction --routines --triggers "$MARIADB_DATABASE"' > backups/ganaderia-$(date +%Y%m%d-%H%M%S).sql
```

En PowerShell, para evitar cambios de codificación al redirigir SQL, se recomienda ejecutar `mariadb-dump` desde WSL/Git Bash o usar la función de respaldo del sistema donde se aloje Docker.

Compruebe que el archivo no esté vacío y pruebe periódicamente su restauración en un volumen o proyecto aislado. No guarde respaldos dentro de la imagen ni los publique en el repositorio.

## Restaurar en una instalación vacía

1. Inicie los servicios para que se cree la base.
2. Importe el archivo sobre esa base:

   ```bash
   docker compose exec -T db sh -lc 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < backups/RESPALDO.sql
   ```

3. Ejecute `docker compose exec web php scripts/healthcheck.php`.

Una restauración reemplaza o combina información según el contenido del SQL. Hágala únicamente sobre la base destino correcta y conserve un respaldo previo.

## Publicación con HTTPS

El contenedor expone HTTP. Para producción colóquelo detrás de un proxy inverso con TLS, como Traefik, Caddy, Nginx Proxy Manager o el balanceador de su plataforma:

- Publique solamente el servicio `web`.
- No publique el puerto 3306 de `db`.
- Configure `APP_BASE_URL=https://ganaderia.sudominio.com`.
- Envíe al contenedor los encabezados `Host`, `X-Forwarded-For` y `X-Forwarded-Proto`.
- Redirija HTTP hacia HTTPS en el proxy.
- Mantenga `.env` fuera del repositorio y limite su lectura al administrador del servidor.

## Solución rápida de problemas

| Problema | Revisión |
|---|---|
| Compose solicita `DB_PASSWORD` | Falta `.env` o la variable quedó vacía. Copie el archivo de ejemplo y configúrela. |
| `db` no llega a `healthy` | Revise `docker compose logs db`, espacio de disco y contraseñas. |
| La web muestra error de conexión | Confirme que el host sea `db` dentro de Compose y que las credenciales coincidan. |
| `setup.php` responde 404 | La base ya contiene un usuario. Ingrese por `login.php`. |
| Cambié `schema.sql` pero no pasó nada | Los scripts de inicialización no se repiten sobre un volumen existente; use una migración. |
| El puerto 8080 está ocupado | Cambie `APP_PORT` en `.env` y vuelva a ejecutar `docker compose up -d`. |

## Desinstalación

Para retirar contenedores y conservar la base:

```bash
docker compose down
```

La eliminación del volumen es irreversible y debe hacerse solamente después de verificar un respaldo:

```bash
docker compose down -v
```
