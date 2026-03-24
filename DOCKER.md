# Docker — Guía de Implementación

Documentación completa de la infraestructura Docker del CRM, incluyendo arquitectura, configuración, despliegue y lecciones aprendidas.

---

## Arquitectura

```
                    ┌─────────────────────────────────────────────┐
                    │               Traefik (host)                │
                    │       SSL/Let's Encrypt + reverse proxy     │
                    └──────────────────┬──────────────────────────┘
                                       │ :443 → :80
                    ┌──────────────────▼──────────────────────────┐
                    │             nginx (web)                      │
                    │   Static assets + FastCGI proxy → app:9000  │
                    └──────────────────┬──────────────────────────┘
                                       │
          ┌────────────────────────────┼────────────────────────────┐
          │                            │                            │
┌─────────▼─────────┐   ┌─────────────▼──────────┐   ┌─────────────▼──────────┐
│    app (php-fpm)   │   │    queue (queue:work)   │   │  scheduler (cron)      │
│ Migrations + cache │   │  Procesa jobs async     │   │  Ejecuta schedule:run  │
└─────────┬──────────┘   └─────────────┬──────────┘   └─────────────┬──────────┘
          │                            │                            │
          └────────────────────────────┼────────────────────────────┘
                                       │
                    ┌──────────────────▼──────────────────────────┐
                    │           db (MySQL 8.0)                     │
                    │         Volumen: db_data                     │
                    └─────────────────────────────────────────────┘
```

**5 contenedores, 1 imagen de app:**

| Contenedor | Base | Rol |
|------------|------|-----|
| `crm_app` | `php:8.2-fpm-alpine` | PHP-FPM + migraciones + caché |
| `crm_nginx` | `nginx:1.27-alpine` | Web server + assets estáticos |
| `crm_db` | `mysql:8.0` | Base de datos |
| `crm_queue` | `crm_app` (reusa imagen) | Worker de cola |
| `crm_scheduler` | `crm_app` (reusa imagen) | Cron (schedule:run cada 60s) |

---

## Dockerfile — Multi-stage Build

El `Dockerfile` tiene 3 stages:

### Stage 1: Assets (Node)

```dockerfile
FROM node:20-alpine AS assets
COPY package*.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js resources/ public/
RUN npm run build
```

- Compila assets con Vite
- La imagen final **no incluye Node** — solo los archivos compilados en `public/build/`

### Stage 2: App (PHP-FPM)

```dockerfile
FROM php:8.2-fpm-alpine AS app
```

- Instala extensiones PHP necesarias para Laravel + MySQL
- Copia el código de la aplicación
- Copia los assets compilados del Stage 1
- Ejecuta `composer install --no-dev`
- El `ENTRYPOINT` es `docker/entrypoint.sh`

### Stage 3: Web (Nginx)

```dockerfile
FROM nginx:1.27-alpine AS web
COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
```

- Copia solo los archivos públicos (no tiene PHP)
- Proxy a `app:9000` vía FastCGI

---

## Entrypoint — Flujo de Arranque

El `docker/entrypoint.sh` controla la inicialización de TODOS los contenedores basados en `crm_app`:

```
┌─────────────────────────────────────────────────┐
│ 1. Cargar APP_KEY persistida (si existe)        │
├─────────────────────────────────────────────────┤
│ SI es php-fpm (contenedor app):                 │
│   2. Crear directorios de storage               │
│   3. Generar APP_KEY (si primera vez)           │
│   4. wait_for_db() — esperar MySQL con PDO      │
│   5. php artisan migrate --force                │
│   6. php artisan config:cache / route / view    │
│   7. php artisan storage:link                   │
├─────────────────────────────────────────────────┤
│ SI es queue:work o schedule:run:                │
│   2. wait_for_db() — esperar MySQL              │
├─────────────────────────────────────────────────┤
│ exec "$@" → arrancar el proceso principal       │
└─────────────────────────────────────────────────┘
```

### Decisiones críticas en el entrypoint

| Decisión | Por qué |
|----------|---------|
| **Sin `set -e`** | Un fallo en migraciones o caché no debe matar el contenedor |
| **`wait_for_db()` con PDO** | `mysqladmin ping` responde durante la inicialización temporal de MySQL. PDO verifica conexión real al port 3306 |
| **`mkdir -p` separados** | Alpine usa `/bin/sh` (busybox) que no soporta brace expansion `{a,b,c}` |
| **`|| true` en cache commands** | Si `view:cache` falla (ej: directorio vacío), el contenedor sigue |
| **APP_KEY en volumen** | Se genera una vez y persiste en `app_env` volume. Queue y scheduler la leen de ahí |

---

## Volúmenes

```yaml
volumes:
  db_data:       # Base de datos MySQL — NUNCA eliminar en producción
  storage_app:   # Archivos subidos por usuarios
  storage_logs:  # Logs de Laravel
  app_env:       # APP_KEY persistida
```

| Volumen | Montado en | Contenedores | Modo |
|---------|-----------|--------------|------|
| `db_data` | `/var/lib/mysql` | db | rw |
| `storage_app` | `/var/www/html/storage/app` | app, queue, scheduler, nginx | rw (nginx: ro) |
| `storage_logs` | `/var/www/html/storage/logs` | app, queue, scheduler | rw |
| `app_env` | `/var/www/html/storage/app/env` | app (rw), queue (ro), scheduler (ro) | mixto |

### Qué sobrevive un redeploy

- ✅ Base de datos (todo los datos)
- ✅ APP_KEY (no se regenera)
- ✅ Archivos subidos en storage
- ✅ Logs
- ❌ Caché de config/routes/views (se regenera al arrancar)
- ❌ Código de la app (se reemplaza con la nueva imagen)

---

## Redes

```yaml
networks:
  crm:                      # Red interna entre los 5 contenedores
    driver: bridge
  openclaw-ehpt_default:    # Red compartida con Traefik
    external: true
```

Solo `nginx` se conecta a `openclaw-ehpt_default` (expuesto a Traefik). Los demás contenedores solo existen en la red `crm` interna.

---

## Traefik + SSL

El contenedor `nginx` expone el CRM a internet vía labels de Traefik:

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.crm.rule=Host(`app.mosley.digital`)"
  - "traefik.http.routers.crm.entrypoints=websecure"
  - "traefik.http.routers.crm.tls=true"
  - "traefik.http.routers.crm.tls.certresolver=letsencrypt"
  - "traefik.http.services.crm.loadbalancer.server.port=80"
```

### Requisitos para que SSL funcione

1. **DNS** — `app.mosley.digital` debe apuntar (A record) a la IP del VPS
2. **Puerto 80 abierto** — Let's Encrypt usa HTTP-01 challenge
3. **Traefik configurado** — Con `--certificatesresolvers.letsencrypt.acme.*`
4. **Red compartida** — nginx debe estar en la misma red que Traefik

### Configuración de Traefik (referencia)

```yaml
# traefik/docker-compose.yml (ya desplegado en el VPS)
services:
  traefik:
    image: traefik:latest
    network_mode: host
    command:
      - --providers.docker=true
      - --providers.docker.exposedbydefault=false
      - --entrypoints.web.address=:80
      - --entrypoints.websecure.address=:443
      - --certificatesresolvers.letsencrypt.acme.httpchallenge=true
      - --certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web
      - --certificatesresolvers.letsencrypt.acme.email=${ACME_EMAIL}
      - --certificatesresolvers.letsencrypt.acme.storage=/letsencrypt/acme.json
      - --entrypoints.web.http.redirections.entrypoint.to=websecure
```

> No duplicar el redirect HTTP→HTTPS en los labels del CRM. Traefik ya lo hace globalmente.

---

## Variables de Entorno

Todas usan `${VAR:-default}` para Hostinger (no hay panel de .env):

| Variable | Default | Notas |
|----------|---------|-------|
| `APP_NAME` | CRM Mosley | — |
| `APP_URL` | https://app.mosley.digital | — |
| `APP_KEY` | *(vacío)* | Se autogenera en primer boot |
| `DB_DATABASE` | crm | — |
| `DB_USERNAME` | crm_user | — |
| `DB_PASSWORD` | changeme | **Cambiar en producción** |
| `DB_ROOT_PASSWORD` | changeme_root | **Cambiar en producción** |
| `MAIL_*` | *(vacío/log)* | Configurar para envío real |
| `MP_ACCESS_TOKEN` | *(vacío)* | MercadoPago |
| `FACTURAPI_KEY` | *(vacío)* | Facturación |
| `TWENTYI_API_KEY` | *(vacío)* | 20i hosting API |

Para cambiar variables en Hostinger, se configuran como variables de entorno del proyecto Docker en el panel.

---

## Migraciones — Principios

Todas las migraciones siguen estas reglas para evitar fallos en producción:

### 1. Idempotentes

Cada `Schema::create` tiene un `Schema::dropIfExists` antes:

```php
public function up(): void
{
    Schema::dropIfExists('my_table');
    Schema::create('my_table', function (Blueprint $table) {
        // ...
    });
}
```

Esto previene fallos por tablas huérfanas de deploys anteriores fallidos.

### 2. Timestamps únicos por dependencia

Si una tabla tiene FK hacia otra, su timestamp debe ser **posterior**:

```
2026_03_18_201005_create_clients_table.php        ← primero
2026_03_18_201006_create_client_documents_table.php ← después (FK → clients)
2026_03_18_201006_create_client_invoices_table.php  ← después (FK → clients)
```

Laravel ordena migraciones por timestamp + nombre. Con el **mismo timestamp**, el orden es **alfabético**, lo cual puede causar que tablas dependientes se creen antes que la tabla padre.

### 3. Nunca falla el contenedor

El entrypoint envuelve `migrate` en un `if !`:
```sh
if ! php artisan migrate --force; then
    echo "⚠ Migrations failed — container will start anyway"
fi
```

---

## .dockerignore

Reduce el tamaño de la imagen excluyendo archivos innecesarios:

```
vendor/          # composer install lo regenera
node_modules/    # npm ci lo regenera
.git/            # historial no necesario
.env*            # secretos nunca en la imagen
tests/           # no van a producción
docker-compose*  # no necesarios dentro del contenedor
```

El `Dockerfile` SÍ se necesita (no excluirlo). Los archivos en `docker/` también son necesarios (entrypoint.sh y nginx config).

---

## Nginx — Configuración

```
docker/nginx/default.conf
```

| Ruta | Comportamiento |
|------|---------------|
| `/*.{js,css,png,...}` | Servido directo con caché 1 año |
| `/storage/*` | Alias a `storage/app/public/` (archivos subidos) |
| `/` | `try_files` → `index.php` (Laravel) |
| `~\.php$` | FastCGI a `app:9000` |
| `~/\.` | **Bloqueado** (protege `.env`, `.git`) |

---

## Despliegue

### Desde Hostinger

1. En Docker Manager, crear proyecto con URL: `https://github.com/Enmamosley/crm`
2. Hacer clic en **Deploy**
3. Hostinger clona el repo, hace `docker compose up --build -d`

### Actualizar

```
git push → Hostinger Deploy → imagen nueva → migrate → listo
```

- Los volúmenes se conservan (datos persistentes)
- Las migraciones nuevas se aplican automáticamente
- Downtime: ~30-40 segundos durante rebuild

### Primer deploy (DB vacía)

El entrypoint corre `migrate --force` que crea todas las tablas. Para cargar datos iniciales (seeders):

1. Acceder al VPS por SSH
2. Ejecutar:
   ```bash
   docker exec -it crm_app php artisan db:seed
   ```
3. Guardar el token de API que aparece en la salida

---

## Orden de Arranque

```
1. db         → MySQL arranca, healthcheck espera "alive"
2. app        → Espera db healthy → wait_for_db PDO → migrate → php-fpm
3. nginx      → Espera app → sirve tráfico web
4. queue      → Espera app + db healthy → wait_for_db → queue:work
5. scheduler  → Espera app + db healthy → wait_for_db → schedule:run loop
```

Dependencias en compose:

```yaml
app        → depends_on: db (service_healthy)
nginx      → depends_on: app (service_started)
queue      → depends_on: app (service_started) + db (service_healthy)
scheduler  → depends_on: app (service_started) + db (service_healthy)
```

---

## Troubleshooting

### Container en restart loop

**Causa:** `set -e` en el entrypoint mataba el contenedor al primer error.
**Solución:** Se eliminó `set -e`. Todos los comandos no críticos usan `|| true`.

### "Connection refused" en migraciones

**Causa:** Docker healthcheck (`mysqladmin ping`) pasa durante el servidor temporal de MySQL (inicialización), antes de que el port 3306 esté disponible.
**Solución:** `wait_for_db()` usa PDO real con retry loop (30 intentos × 2s = 60s máximo).

### "Table already exists"

**Causa:** Un deploy anterior falló a mitad de las migraciones. Las tablas se crearon pero no se registraron en la tabla `migrations`.
**Solución:** `Schema::dropIfExists()` antes de cada `Schema::create()` en todas las migraciones.

### "Failed to open referenced table"

**Causa:** Migraciones con el mismo timestamp se ejecutan en orden alfabético. Si `client_documents` (FK → clients) está antes de `clients` alfabéticamente, la FK falla.
**Solución:** Las tablas dependientes tienen un timestamp 1 segundo después que sus padres.

### "View path not found"

**Causa:** `storage/framework/views/` no existe en un volumen vacío. `view:cache` intenta limpiar antes de cachear, y falla.
**Solución:** El entrypoint crea los directorios manualmente con `mkdir -p` (sin brace expansion — Alpine no la soporta).

### Queue/Scheduler "cache table doesn't exist"

**Causa:** Queue arranca antes de que app termine las migraciones. Intenta leer tabla `cache` que aún no existe.
**Solución:** `depends_on: db: condition: service_healthy` + `wait_for_db()` en el entrypoint para queue/scheduler.

### SSL muestra "TRAEFIK DEFAULT CERT"

**Causas posibles:**
1. DNS no apunta al VPS → Let's Encrypt no puede verificar
2. Puerto 80 bloqueado por firewall → HTTP-01 challenge falla
3. Nombre del certresolver incorrecto → verificar contra config de Traefik
4. Labels de redirect duplicados → Traefik ya redirige globalmente

---

## Estructura de Archivos Docker

```
crm/
├── Dockerfile                    # Multi-stage: node → php-fpm → nginx
├── docker-compose.yml            # 5 servicios + redes + volúmenes
├── .dockerignore                 # Excluye vendor/, node_modules/, tests/
├── docker/
│   ├── entrypoint.sh            # Inicialización: wait_for_db, migrate, cache
│   └── nginx/
│       └── default.conf         # Config de nginx: FastCGI + static + security
└── database/
    └── migrations/              # Todas idempotentes con dropIfExists
```
