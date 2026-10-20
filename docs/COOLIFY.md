# Despliegue en Coolify (PHP 8.3)

Migración de `app.mosley.digital` desde el VPS con ServerAvatar/Apache a Coolify.

## 1. Crear el recurso
1. Coolify → Project → **+ New → Application → Private Repository** (`Enmamosley/crm`, rama con estos cambios).
2. Build Pack: **Docker Compose**, archivo: `docker-compose.coolify.yml`.
3. En el servicio **nginx** asignar el dominio `https://app.mosley.digital` (puerto 80). Los demás servicios no llevan dominio.

## 2. Variables de entorno (pestaña Environment Variables)
Obligatorias (marcarlas solo como runtime, nunca build-time: Coolify las pasaría como build args y quedarían en la imagen):

| Variable | Valor |
|---|---|
| `APP_KEY` | **la misma** que en el `.env` de producción |
| `DB_DATABASE` | `crm-m` (o la que prefieras; el dump no fija el nombre) |
| `DB_USERNAME` / `DB_PASSWORD` | credenciales nuevas para el MySQL del contenedor |
| `DB_ROOT_PASSWORD` | contraseña root del contenedor MySQL |

Copiar también desde el `.env` de producción: `MAIL_*`, `DMCHAMP_*`, `MP_*`, `FACTURAPI_KEY`, `TWENTYI_API_KEY`, `SESSION_*`, `APP_LOCALE`.

`SCHEDULER_ENABLED` queda en `false`: hoy no hay cron para este CRM, y activarlo empezaría a enviar
recordatorios de pago, facturas recurrentes y avisos de cobranza. Cámbialo a `true` solo cuando se decida.

## 3. Primer despliegue e importación de datos
1. **Deploy**. El contenedor `app` ejecuta `migrate --force` sobre la BD vacía; no pasa nada, el dump lo sobrescribe.
2. Importar la base de datos (desde el host de Coolify):
   ```bash
   DB=$(docker ps -qf "name=db-") # el contenedor db del recurso
   gunzip < crm-m-db-AAAAMMDD-HHMM.sql.gz | docker exec -i $DB sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"'
   ```
3. Restaurar los archivos subidos (`storage/app`: logos, CFDI, CSD, clientes):
   ```bash
   APP=$(docker ps -qf "name=app-")
   docker cp crm-m-storage-AAAAMMDD-HHMM.tar.gz $APP:/tmp/s.tgz
   docker exec $APP sh -c 'tar xzf /tmp/s.tgz -C /var/www/html && chown -R www-data:www-data storage/app && rm /tmp/s.tgz'
   ```
4. **Restart** del recurso (vuelve a correr las migraciones, que ya están todas aplicadas, y regenera caches).

## 4. Verificación
- `https://<dominio>/up` → 200.
- `docker exec $APP php artisan migrate:status` → 60 migraciones `Ran`.
- Iniciar sesión con un usuario real y abrir clientes, facturas y un PDF.
- Generar/consultar un CFDI de prueba (usa `soap` y `xsl`, ahora incluidas en la imagen).

## 5. Cambio de DNS
Mientras el DNS apunte al VPS actual, el sitio sigue sirviéndose desde Apache. Para probar antes,
usa un dominio temporal en Coolify y pon `APP_URL` acorde. Al cambiar el registro A de
`app.mosley.digital`, haz un último dump/restore para no perder lo escrito entretanto.
