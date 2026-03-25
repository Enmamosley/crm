# CRM Mosley - API Reference

**Base URL:** `https://sys.mosley.mx/api/v1`

---

## Endpoints Públicos (sin autenticación)

### `GET /test`

Estado del CRM.

**Response (200):**
```json
{
  "status": "ok",
  "app": "CRM Mosley",
  "version": "1.0",
  "timestamp": "2026-03-03T01:00:55.389749Z"
}
```

---

### `GET /services`

Catálogo de servicios disponibles con precios.

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Landing Page",
      "description": "Página de aterrizaje optimizada para conversión",
      "price": "5000.00",
      "service_category_id": 1,
      "category": {
        "id": 1,
        "name": "Desarrollo Web"
      }
    },
    {
      "id": 2,
      "name": "Sitio Web Corporativo",
      "description": "Sitio web completo hasta 10 páginas",
      "price": "15000.00",
      "service_category_id": 1,
      "category": {
        "id": 1,
        "name": "Desarrollo Web"
      }
    },
    {
      "id": 3,
      "name": "E-Commerce",
      "description": "Tienda en línea con carrito y pagos",
      "price": "25000.00",
      "service_category_id": 1,
      "category": {
        "id": 1,
        "name": "Desarrollo Web"
      }
    },
    {
      "id": 4,
      "name": "Sistema Web a Medida",
      "description": "Desarrollo de sistema web personalizado",
      "price": "40000.00",
      "service_category_id": 1,
      "category": {
        "id": 1,
        "name": "Desarrollo Web"
      }
    },
    {
      "id": 5,
      "name": "App iOS",
      "description": "Aplicación nativa para iOS",
      "price": "35000.00",
      "service_category_id": 2,
      "category": {
        "id": 2,
        "name": "Aplicaciones Móviles"
      }
    },
    {
      "id": 6,
      "name": "App Android",
      "description": "Aplicación nativa para Android",
      "price": "35000.00",
      "service_category_id": 2,
      "category": {
        "id": 2,
        "name": "Aplicaciones Móviles"
      }
    },
    {
      "id": 7,
      "name": "App Multiplataforma",
      "description": "App para iOS y Android con Flutter/React Native",
      "price": "50000.00",
      "service_category_id": 2,
      "category": {
        "id": 2,
        "name": "Aplicaciones Móviles"
      }
    },
    {
      "id": 8,
      "name": "Soporte Mensual Básico",
      "description": "Mantenimiento y soporte básico mensual",
      "price": "3000.00",
      "service_category_id": 3,
      "category": {
        "id": 3,
        "name": "Soporte Técnico"
      }
    },
    {
      "id": 9,
      "name": "Soporte Mensual Premium",
      "description": "Soporte prioritario con SLA garantizado",
      "price": "8000.00",
      "service_category_id": 3,
      "category": {
        "id": 3,
        "name": "Soporte Técnico"
      }
    },
    {
      "id": 10,
      "name": "Consultoría Técnica (hora)",
      "description": "Asesoría técnica por hora",
      "price": "800.00",
      "service_category_id": 3,
      "category": {
        "id": 3,
        "name": "Soporte Técnico"
      }
    }
  ]
}
```

---

## Endpoints Protegidos (Bearer Token)

**Header requerido:**
```
Authorization: Bearer {token}
Accept: application/json
```

> Sin token válido, todos estos endpoints retornan `401 Unauthenticated`.

---

### `GET /leads`

Lista todos los leads. Soporta filtro por status.

**Query params opcionales:**
| Param    | Tipo   | Descripción                                                        |
|----------|--------|--------------------------------------------------------------------|
| `status` | string | Filtrar por estado: `nuevo`, `contactado`, `cotizado`, `cerrado`, `perdido` |

**Response (200):**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Juan Pérez",
        "email": "juan@empresa.com",
        "phone": "5551234567",
        "business": "Empresa X",
        "project_description": "Necesito un sitio web corporativo",
        "source": "agente",
        "status": "cotizado",
        "created_at": "2026-03-02T23:25:40.000000Z",
        "updated_at": "2026-03-02T23:25:40.000000Z"
      }
    ],
    "per_page": 20,
    "total": 1
  }
}
```

---

### `POST /leads`

Crea un nuevo lead.

**Body (JSON):**
```json
{
  "name": "María López",
  "email": "maria@test.com",
  "phone": "5559876543",
  "business": "Startup Y",
  "project_description": "App móvil para delivery"
}
```

| Campo                | Tipo   | Requerido | Descripción              |
|----------------------|--------|-----------|--------------------------|
| `name`               | string | ✅        | Nombre del contacto      |
| `email`              | string | ❌        | Email del contacto       |
| `phone`              | string | ❌        | Teléfono                 |
| `business`           | string | ❌        | Nombre de la empresa     |
| `project_description`| string | ❌        | Descripción del proyecto |
| `source`             | string | ❌        | Origen (default: `agente`) |

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "name": "María López",
    "email": "maria@test.com",
    "phone": "5559876543",
    "business": "Startup Y",
    "project_description": "App móvil para delivery",
    "source": "agente",
    "status": "nuevo",
    "created_at": "2026-03-03T01:00:00.000000Z",
    "updated_at": "2026-03-03T01:00:00.000000Z"
  },
  "message": "Lead creado exitosamente."
}
```

---

### `GET /leads/{id}`

Detalle de un lead con notas, historial de estatus y cotizaciones.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Juan Pérez",
    "email": "juan@empresa.com",
    "phone": "5551234567",
    "business": "Empresa X",
    "project_description": "Necesito un sitio web corporativo",
    "source": "agente",
    "status": "cotizado",
    "created_at": "2026-03-02T23:25:40.000000Z",
    "updated_at": "2026-03-02T23:25:40.000000Z",
    "notes": [],
    "status_history": [
      {
        "id": 1,
        "old_status": null,
        "new_status": "nuevo",
        "changed_by": "agente",
        "created_at": "2026-03-02T23:25:40.000000Z"
      }
    ],
    "quotes": [
      {
        "id": 1,
        "quote_number": "COT-2026-0001",
        "status": "borrador",
        "total": "17400.00",
        "valid_until": "2026-04-01"
      }
    ]
  }
}
```

---

### `POST /quotes`

Genera una cotización para un lead.

**Body (JSON):**
```json
{
  "lead_id": 1,
  "notes": "Cotización para sitio web corporativo",
  "items": [
    {
      "service_id": 2,
      "quantity": 1
    },
    {
      "service_id": 8,
      "quantity": 3
    }
  ]
}
```

| Campo               | Tipo    | Requerido | Descripción                                      |
|----------------------|---------|-----------|--------------------------------------------------|
| `lead_id`           | integer | ✅        | ID del lead                                      |
| `notes`             | string  | ❌        | Notas de la cotización                           |
| `items`             | array   | ✅        | Lista de servicios (mínimo 1)                    |
| `items.*.service_id`| integer | ✅        | ID del servicio (ver `/services`)                |
| `items.*.quantity`  | integer | ✅        | Cantidad                                          |
| `items.*.unit_price`| number  | ❌        | Precio personalizado (default: precio del servicio) |

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "quote_number": "COT-2026-0001",
    "lead_id": 1,
    "subtotal": "15000.00",
    "iva_percentage": "16.00",
    "iva_amount": "2400.00",
    "total": "17400.00",
    "status": "borrador",
    "valid_until": "2026-04-01T23:25:40.000000Z",
    "notes": "Cotización para sitio web corporativo",
    "created_at": "2026-03-02T23:25:40.000000Z",
    "lead": {
      "id": 1,
      "name": "Juan Pérez",
      "email": "juan@empresa.com"
    },
    "items": [
      {
        "id": 1,
        "service_id": 2,
        "description": "Sitio Web Corporativo",
        "quantity": 1,
        "unit_price": "15000.00",
        "total": "15000.00",
        "service": {
          "id": 2,
          "name": "Sitio Web Corporativo"
        }
      }
    ]
  },
  "message": "Cotización generada exitosamente."
}
```

---

### `GET /leads/search`

Busca leads existentes por teléfono, email o nombre. Útil para verificar si un contacto ya existe antes de crear uno nuevo.

> **Importante:** Este endpoint debe registrarse antes de `GET /leads/{id}` para evitar conflictos de rutas.

**Query params (al menos uno requerido):**
| Param   | Tipo   | Descripción |
|---------|--------|-------------|
| `phone` | string | Número de teléfono exacto |
| `email` | string | Correo electrónico exacto |
| `name`  | string | Nombre parcial (búsqueda `LIKE`) |

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Juan Pérez",
      "email": "juan@empresa.com",
      "phone": "5551234567",
      "business": "Empresa X",
      "status": "cotizado"
    }
  ]
}
```

**Response (422) — sin parámetros:**
```json
{
  "success": false,
  "message": "Debes enviar al menos un parámetro: phone, email o name."
}
```

### `PATCH /leads/{id}/status`

Actualiza el estado de un lead.

**Body (JSON):**
```json
{
  "status": "contactado",
  "changed_by": "agente"
}
```

| Campo        | Tipo   | Requerido | Descripción |
|--------------|--------|-----------|-------------|
| `status`     | string | ✅        | Uno de: `nuevo`, `contactado`, `cotizado`, `cerrado`, `perdido` |
| `changed_by` | string | ❌        | Quién realizó el cambio (default: `agente`) |

**Response (200):**
```json
{
  "success": true,
  "data": { "id": 1, "status": "contactado", "...": "..." },
  "message": "Estado del lead actualizado."
}
```

---

### `POST /leads/{id}/notes`

Agrega una nota a un lead. Permite que el agente documente lo que ocurrió en la conversación.

**Body (JSON):**
```json
{
  "content": "El cliente mostró interés en el plan premium. Prefiere contacto por WhatsApp.",
  "author": "agente"
}
```

| Campo    | Tipo   | Requerido | Descripción |
|----------|--------|-----------|-------------|
| `content`| string | ✅        | Texto de la nota |
| `author` | string | ❌        | Autor de la nota (default: `agente`) |

**Response (201):**
```json
{
  "success": true,
  "data": {
    "id": 5,
    "lead_id": 1,
    "content": "El cliente mostró interés en el plan premium.",
    "author": "agente",
    "created_at": "2026-03-18T12:00:00.000000Z"
  },
  "message": "Nota agregada al lead."
}
```

---

### `PATCH /quotes/{id}/status`

Actualiza el estado de una cotización.

**Body (JSON):**
```json
{
  "status": "aceptada"
}
```

| Campo    | Tipo   | Requerido | Descripción |
|----------|--------|-----------|-------------|
| `status` | string | ✅        | Uno de: `borrador`, `enviada`, `aceptada`, `rechazada`, `vencida` |

**Response (200):**
```json
{
  "success": true,
  "data": { "id": 1, "status": "aceptada", "...": "..." },
  "message": "Estado de la cotización actualizado."
}
```

---

### `GET /quotes/{id}/pdf`

Descarga la cotización en PDF.

**Response:** Archivo PDF (`Content-Type: application/pdf`)

---

### `GET /agent/status`

Verifica si el agente está activo o pausado.

**Query params opcionales:**
| Param     | Tipo   | Descripción                        |
|-----------|--------|------------------------------------|
| `channel` | string | Canal a consultar (default: `general`) |

**Response (200):**
```json
{
  "success": true,
  "data": {
    "channel": "general",
    "is_paused": false,
    "status": "active"
  }
}
```

---

### `GET /settings`

Retorna la configuración pública del negocio (nombre, datos de contacto, IVA, etc.).

**Response (200):**
```json
{
  "success": true,
  "data": {
    "company_name": "Mosley",
    "company_address": "Av. Ejemplo 123, Ciudad de México",
    "company_phone": "5551234567",
    "company_email": "contacto@mosley.mx",
    "company_rfc": "MOX123456ABC",
    "iva_percentage": "16",
    "currency": "MXN"
  }
}
```

---

## Flujo de integración recomendado

1. `GET /settings` — Leer configuración del negocio (nombre, IVA, moneda)
2. `GET /services` — Consultar catálogo de servicios y precios
3. `GET /leads/search?phone=` — Verificar si el contacto ya existe
4. `POST /leads` — Registrar al prospecto si no existe
5. `POST /leads/{id}/notes` — Documentar la conversación
6. `PATCH /leads/{id}/status` — Actualizar estado del lead según avance
7. `POST /quotes` — Generar cotización con los servicios seleccionados
8. `PATCH /quotes/{id}/status` — Marcar cotización como enviada/aceptada/rechazada
9. `GET /quotes/{id}/pdf` — Obtener PDF de la cotización
10. `GET /agent/status` — Verificar si el agente está activo antes de actuar

## Notas

- Todos los precios están en **pesos mexicanos (MXN)**
- El IVA se calcula automáticamente según la configuración del sistema
- Las cotizaciones tienen vigencia de 30 días
- El número de cotización se genera automáticamente (formato: `COT-YYYY-NNNN`)

---

# Gestión de correo electrónico (20i)

## Descripción general

El CRM integra el API de [20i](https://api.20i.com) como proveedor de hosting para gestionar buzones de correo electrónico por cliente. Cada cliente puede tener un **Package ID** de 20i (campo `twentyi_package_id` en la tabla `clients`) vinculado a un dominio. La gestión está disponible tanto en el **panel de administración** como en el **portal del cliente**.

## Configuración requerida

| Setting (tabla `settings`) | Descripción |
|---|---|
| `twentyi_api_key` | Clave API general de 20i. Si se guardó la clave combinada (`general+oauth`), el servicio extrae solo la parte antes del `+`. |

| Campo en `clients` | Descripción |
|---|---|
| `twentyi_package_id` | ID del paquete 20i asignado al cliente (ej: `2296724`). Sin este campo, la sección de correo no aparece. |

## Servicio: `TwentyIService`

Ubicación: `app/Services/TwentyIService.php`

### Autenticación

20i usa un Bearer token que es el **base64** del API key general:

```
Authorization: Bearer base64_encode($generalKey)
```

### Métodos públicos

| Método | Descripción |
|---|---|
| `getDomain(Client): ?string` | Retorna el dominio de correo del paquete (ej: `mosley.mx`). |
| `listMailboxes(Client): array` | Lista los buzones. Cada buzón incluye `id`, `local`, `quotaMB`, `usageMB`, `enabled`. |
| `createMailbox(Client, local, password, quotaMB=10240): array` | Crea un buzón nuevo. |
| `deleteMailbox(Client, mailboxId): void` | Elimina un buzón por ID (ej: `m123456`). |
| `updateMailboxPassword(Client, mailboxId, newPassword): void` | Cambia la contraseña de un buzón. |
| `getWebmailUrl(Client, mailboxId): string` | Obtiene URL SSO de StackMail (webmail). |

## API 20i — Formatos de payload

> **Importante:** Estos formatos fueron capturados del panel web de 20i porque **no están documentados** oficialmente en su API.

**Base URL:** `https://api.20i.com`

### Obtener dominio del paquete

```
GET /package/{packageId}/email
```

Respuesta: objeto cuyas **claves** son los dominios asociados.

```json
{
  "mosley.mx": [...]
}
```

El primer key es el `emailId` (dominio) que se usa en todas las llamadas siguientes.

### Listar buzones

```
GET /package/{packageId}/email/{domain}/mailbox
```

**Response:**
```json
{
  "name": "mosley.mx",
  "mailbox": [
    {
      "id": "m123456",
      "local": "info",
      "quotaMB": 10240,
      "usageMB": 52.3,
      "enabled": true,
      "lastPasswordChange": "2026-03-15 12:00:00"
    }
  ]
}
```

### Crear buzón

```
POST /package/{packageId}/email/{domain}
```

**Payload:**
```json
{
  "new": {
    "mailbox": {
      "local": "usuario",
      "password": "contraseña_segura",
      "receive": true,
      "send": true,
      "quotaMB": 10240
    }
  }
}
```

`quotaMB` por defecto: 10240 (10 GB).

### Eliminar buzón

```
POST /package/{packageId}/email/{domain}
```

**Payload:**
```json
{
  "delete": ["m123456"]
}
```

### Cambiar contraseña

```
POST /package/{packageId}/email/{domain}
```

**Payload:**
```json
{
  "existing": {
    "m123456": {
      "password": "nueva_contraseña"
    }
  }
}
```

### Webmail SSO

```
POST /package/{packageId}/email/{domain}/webmail
```

**Payload:**
```json
{
  "id": "m123456"
}
```

**Response:**
```json
{
  "result": {
    "SsoLink": "https://stackmail.com/sso/..."
  }
}
```

> Nota: El campo es `SsoLink` (PascalCase), no `url` ni `ssoUrl`.

## Rutas — Panel de administración

Requieren autenticación (`auth` middleware).

| Método | URI | Nombre | Descripción |
|---|---|---|---|
| GET | `admin/clients/{client}/mailboxes` | `clients.mailboxes.index` | Lista buzones del cliente |
| POST | `admin/clients/{client}/mailboxes` | `clients.mailboxes.store` | Crea buzón |
| DELETE | `admin/clients/{client}/mailboxes/{mailbox}` | `clients.mailboxes.destroy` | Elimina buzón |
| POST | `admin/clients/{client}/mailboxes/{mailbox}/password` | `clients.mailboxes.password` | Cambia contraseña |
| POST | `admin/clients/{client}/mailboxes/{mailbox}/webmail` | `clients.mailboxes.webmail` | Redirige a webmail SSO |

**Controller:** `App\Http\Controllers\Admin\MailboxController`

## Rutas — Portal del cliente

Sin autenticación; acceso por token del portal (`portal_token`).

| Método | URI | Nombre | Descripción |
|---|---|---|---|
| GET | `portal/{token}/mailboxes` | `portal.mailboxes` | Lista buzones |
| POST | `portal/{token}/mailboxes` | `portal.mailboxes.store` | Crea buzón |
| DELETE | `portal/{token}/mailboxes/{mailbox}` | `portal.mailboxes.destroy` | Elimina buzón |
| POST | `portal/{token}/mailboxes/{mailbox}/password` | `portal.mailboxes.password` | Cambia contraseña |
| POST | `portal/{token}/mailboxes/{mailbox}/webmail` | `portal.mailbox.webmail` | Redirige a webmail SSO |

**Controller:** `App\Http\Controllers\ClientPortalController`

## Vistas

| Vista | Descripción |
|---|---|
| `admin/mailboxes/index.blade.php` | Gestión completa de buzones desde el admin |
| `portal/mailboxes.blade.php` | Gestión completa de buzones desde el portal del cliente (standalone HTML) |
| `portal/dashboard.blade.php` | Dashboard del portal — muestra resumen de buzones con enlace a gestión |

## Notas de implementación

- Los IDs de buzón tienen formato `mXXXXXX` (ej: `m628830`).
- Crear, eliminar y cambiar contraseña comparten el **mismo endpoint** (`POST /package/{id}/email/{domain}`); lo que cambia es la estructura del payload (`new`, `delete`, `existing`).
- La API de 20i retorna HTTP 200 incluso cuando el payload es inválido — simplemente devuelve `{"result":{"result":[]}}` sin crear nada. No hay mensajes de error explícitos para payloads mal formados.
- Las vistas del portal son **standalone HTML** (no usan layout), incluyen Tailwind CDN y Font Awesome directamente.
- En Blade, para mostrar `@dominio.com` se debe usar `{{ '@' . $domain }}` en lugar de `@{{ $domain }}` (que Blade interpreta como "no procesar esta expresión").
