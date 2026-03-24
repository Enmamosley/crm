# CRM Mosley

CRM interno para gestión de prospectos, cotizaciones, facturas y clientes. Incluye API REST para integración con el agente IA **OpenClaw**.

## Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel 12 · PHP 8.2 |
| Frontend | Blade · Alpine.js · Tailwind CSS 4 · Vite |
| Base de datos | MySQL 8 |
| Colas / Caché | MySQL (driver `database`) |
| PDF | DomPDF |
| Auth API | Laravel Sanctum (Bearer token) |
| Deploy | Docker · Nginx · Traefik (SSL) |

## Módulos

- **Leads** — pipeline de prospectos con historial de estados y notas
- **Cotizaciones** — generación de PDFs con numeración automática e IVA configurable
- **Clientes** — portal de cliente con documentos y facturas
- **Facturación** — facturas, pagos, facturas recurrentes, dunning automático, integración FacturAPI
- **Correo 20i** — gestión de buzones por cliente vía API de 20i
- **MercadoPago** — checkout directo y carrito con webhook HMAC
- **Control del agente** — pausa/reactiva OpenClaw por canal desde el panel admin
- **API REST** — endpoints para que OpenClaw gestione leads y cotizaciones de forma autónoma

## Requisitos locales

- PHP 8.2+
- Composer 2
- Node 20+
- MySQL 8 (o SQLite para desarrollo rápido)

## Instalación local

```bash
git clone https://github.com/TU_USUARIO/crm.git && cd crm

# Variables de entorno
cp .env.example .env

# Dependencias
composer install
npm install

# Clave de la app
php artisan key:generate

# Base de datos
php artisan migrate --seed

# Assets
npm run build

# Levantar servidor
php artisan serve
```

## Deploy en VPS con Docker

### Pre-requisitos en el VPS
- Docker + Docker Compose
- Traefik corriendo en la red `openclaw-ehpt_default` con certresolver `letsencrypt`

### Primer deploy

```bash
# Clonar repo
git clone https://github.com/TU_USUARIO/crm.git /srv/crm
cd /srv/crm

# Configurar entorno de producción
cp .env.example .env
# Editar .env: APP_KEY, DB_*, MAIL_*, MP_*, FACTURAPI_KEY, APP_DOMAIN, etc.

# Generar APP_KEY
docker run --rm php:8.2-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
# Pegar el resultado en APP_KEY en el .env

# Construir y levantar
docker compose up -d --build
```

### Actualizar

```bash
cd /srv/crm
git pull
docker compose up -d --build app nginx
```

### Servicios Docker

| Servicio | Descripción |
|---|---|
| `app` | PHP-FPM — corre migraciones y cachea config al iniciar |
| `nginx` | Sirve assets estáticos y proxy a PHP-FPM |
| `db` | MySQL 8 — datos persistentes en volumen `db_data` |
| `queue` | Worker de colas (`queue:work`) |
| `scheduler` | Cron que ejecuta `schedule:run` cada minuto |

### Variables de entorno importantes

| Variable | Descripción |
|---|---|
| `APP_KEY` | Clave de cifrado (generar con artisan) |
| `APP_DOMAIN` | Dominio sin `https://` — usado por Traefik para el cert |
| `DB_PASSWORD` | Contraseña del usuario MySQL |
| `DB_ROOT_PASSWORD` | Contraseña root de MySQL |
| `MAIL_*` | Configuración SMTP |
| `MP_ACCESS_TOKEN` | Token de MercadoPago |
| `MP_WEBHOOK_SECRET` | Secret HMAC para validar webhooks de MercadoPago |
| `FACTURAPI_KEY` | API key de FacturAPI para timbrado |
| `TWENTYI_API_KEY` | API key de 20i para gestión de correos |

## API para OpenClaw

Ver [`OPENCLAW_API.md`](OPENCLAW_API.md) — documentación completa de todos los endpoints, flujos recomendados y reglas de negocio.

**Base URL:** `https://app.mosley.digital/api/v1`
**Auth:** Bearer token (Sanctum)

Endpoints principales:
- `GET /agent/status` — verificar si el agente está activo por canal
- `GET /services` — catálogo de servicios con precios
- `GET /leads/search` — buscar prospect existente
- `POST /leads` — registrar nuevo prospecto
- `POST /quotes` — generar cotización con PDF

## Tareas programadas

| Comando | Horario | Descripción |
|---|---|---|
| `invoices:send-reminders` | 9:00 AM | Recordatorios de pago |
| `invoices:process-recurring` | 6:00 AM | Generar facturas recurrentes |
| `quotes:expire` | 7:00 AM | Marcar cotizaciones vencidas |
| `db:backup` | 2:00 AM | Backup MySQL comprimido (guarda últimos 10) |
| `dunning:process` | 10:00 AM | Reintentos de cobro automáticos |


Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
