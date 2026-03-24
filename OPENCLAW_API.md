# API Reference — OpenClaw Integration
**Base URL:** `https://sys.mosley.mx/api/v1`
**Formato:** JSON (`Content-Type: application/json`)
**Autenticación:** Bearer Token (Sanctum)

---

## Autenticación

Todos los endpoints protegidos requieren el header:
```
Authorization: Bearer {token}
```

Los únicos endpoints públicos (sin token) son:
- `GET /test`
- `GET /services`

---

## Límites de peticiones

| Grupo | Límite |
|---|---|
| Endpoints públicos | 60 peticiones / minuto |
| Endpoints protegidos | Sin límite explícito |

---

## Formato de respuesta

Todas las respuestas siguen esta estructura:

```json
{
  "success": true | false,
  "data": { ... } | [ ... ],
  "message": "Descripción opcional"
}
```

En caso de error de validación (422):
```json
{
  "message": "El campo X es obligatorio.",
  "errors": {
    "campo": ["El campo X es obligatorio."]
  }
}
```

---

## Endpoints

### `GET /test`
> Público · Sin autenticación

Verifica que la API está en línea.

**Response 200:**
```json
{ "status": "ok", "message": "API funcionando correctamente" }
```

---

### `GET /settings`
> Protegido · Requiere Bearer token

Retorna la configuración pública del negocio. Llama esto al inicio de cada sesión para obtener nombre de empresa, IVA y moneda.

**Response 200:**
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

### `GET /services`
> Público · Sin autenticación

Retorna el catálogo de servicios disponibles con precios.

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Desarrollo Web",
      "slug": "desarrollo-web",
      "description": "Sitio web empresarial",
      "price": "15000.00",
      "category": "desarrollo",
      "active": true
    }
  ]
}
```

> **Nota:** Los precios están en MXN. El IVA se aplica al generar cotizaciones según `iva_percentage` en configuración.

---

### `GET /agent/status`
> Protegido · Requiere Bearer token

Verifica si el agente IA está activo o pausado. **Llama esto antes de responder al usuario.** Si `is_paused: true`, no debes responder automáticamente.

**Query params opcionales:**

| Param | Tipo | Default | Descripción |
|---|---|---|---|
| `channel` | string | `general` | Canal a consultar |

**Response 200:**
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

| `status` | Significado |
|---|---|
| `active` | El agente debe responder normalmente |
| `paused` | Un humano tomó el control — no responder |

---

## Leads

Los leads representan prospectos o contactos. Siempre **busca antes de crear** para evitar duplicados.

### Valores válidos de `status`

| Status | Descripción |
|---|---|
| `nuevo` | Recién registrado, sin contacto |
| `contactado` | Ya se tuvo comunicación inicial |
| `cotizado` | Se generó una cotización |
| `cerrado` | Venta concretada |
| `perdido` | No prosperó |

---

### `GET /leads/search`
> Protegido · Requiere Bearer token

Busca leads existentes. **Siempre ejecuta esto antes de crear un lead nuevo.**

**Query params (al menos uno requerido):**

| Param | Tipo | Descripción |
|---|---|---|
| `phone` | string | Coincidencia exacta de teléfono |
| `email` | string | Coincidencia exacta de email |
| `name` | string | Búsqueda parcial por nombre |

**Request:**
```
GET /leads/search?phone=5551234567
GET /leads/search?email=cliente@ejemplo.com
GET /leads/search?name=Juan
```

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Juan Pérez",
      "email": "juan@ejemplo.com",
      "phone": "5551234567",
      "business": "Empresa S.A.",
      "status": "contactado",
      "source": "agente",
      "created_at": "2026-01-15T10:00:00.000000Z"
    }
  ]
}
```

Si `data` es un array vacío `[]`, el lead no existe → procede a crearlo con `POST /leads`.

**Error 422** — Si no se envía ningún parámetro:
```json
{
  "success": false,
  "message": "Debes enviar al menos un parámetro: phone, email o name."
}
```

---

### `POST /leads`
> Protegido · Requiere Bearer token

Crea un nuevo lead. Solo ejecutar si `GET /leads/search` no devolvió resultados.

**Body:**
```json
{
  "name": "Juan Pérez",
  "email": "juan@ejemplo.com",
  "phone": "5551234567",
  "business": "Empresa S.A. de C.V.",
  "project_description": "Necesita sitio web con e-commerce y diseño moderno."
}
```

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `name` | string | ✅ | Nombre completo del prospecto |
| `email` | string | ❌ | Email (debe ser válido si se envía) |
| `phone` | string | ❌ | Teléfono |
| `business` | string | ❌ | Nombre de la empresa |
| `project_description` | string | ❌ | Descripción libre del proyecto o necesidad |
| `source` | string | ❌ | Origen (default automático: `agente`) |

> **Nota:** El campo `source` se establece automáticamente como `agente` si no se envía. No es necesario incluirlo.

**Response 201:**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "name": "Juan Pérez",
    "email": "juan@ejemplo.com",
    "phone": "5551234567",
    "business": "Empresa S.A. de C.V.",
    "project_description": "Necesita sitio web con e-commerce.",
    "status": "nuevo",
    "source": "agente",
    "created_at": "2026-03-18T12:00:00.000000Z",
    "updated_at": "2026-03-18T12:00:00.000000Z"
  },
  "message": "Lead creado exitosamente."
}
```

---

### `GET /leads/{id}`
> Protegido · Requiere Bearer token

Obtiene el detalle completo de un lead, incluyendo notas, historial de estados y cotizaciones.

**Response 200:**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "name": "Juan Pérez",
    "email": "juan@ejemplo.com",
    "phone": "5551234567",
    "business": "Empresa S.A.",
    "project_description": "Sitio web con e-commerce.",
    "status": "cotizado",
    "source": "agente",
    "created_at": "2026-03-18T12:00:00.000000Z",
    "notes": [
      {
        "id": 3,
        "lead_id": 42,
        "content": "Cliente interesado en plan premium.",
        "author": "agente",
        "created_at": "2026-03-18T12:05:00.000000Z"
      }
    ],
    "status_history": [
      {
        "id": 1,
        "lead_id": 42,
        "old_status": null,
        "new_status": "nuevo",
        "changed_by": "agente",
        "created_at": "2026-03-18T12:00:00.000000Z"
      }
    ],
    "quotes": [
      {
        "id": 7,
        "quote_number": "COT-2026-0007",
        "status": "enviada",
        "total": "17400.00",
        "valid_until": "2026-04-17"
      }
    ]
  }
}
```

---

### `GET /leads`
> Protegido · Requiere Bearer token

Lista leads paginados (20 por página), opcionalmente filtrados por status.

**Query params opcionales:**

| Param | Tipo | Descripción |
|---|---|---|
| `status` | string | Filtra por estado: `nuevo`, `contactado`, `cotizado`, `cerrado`, `perdido` |

**Response 200:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [ { "id": 1, "name": "...", "status": "nuevo", "..." : "..." } ],
    "total": 45,
    "per_page": 20,
    "last_page": 3
  }
}
```

---

### `PATCH /leads/{id}/status`
> Protegido · Requiere Bearer token

Actualiza el estado del lead. El historial de cambios se guarda automáticamente.

**Body:**
```json
{
  "status": "contactado",
  "changed_by": "agente"
}
```

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `status` | string | ✅ | `nuevo` \| `contactado` \| `cotizado` \| `cerrado` \| `perdido` |
| `changed_by` | string | ❌ | Quién realizó el cambio (default: `agente`) |

**Response 200:**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "status": "contactado",
    "updated_at": "2026-03-18T12:10:00.000000Z"
  },
  "message": "Estado del lead actualizado."
}
```

---

### `POST /leads/{id}/notes`
> Protegido · Requiere Bearer token

Agrega una nota interna al lead. Documenta cada interacción relevante de la conversación.

**Body:**
```json
{
  "content": "El cliente mostró interés en el plan premium. Prefiere contacto por WhatsApp.",
  "author": "agente"
}
```

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `content` | string | ✅ | Texto de la nota (sin límite de longitud) |
| `author` | string | ❌ | Autor (default: `agente`) |

**Response 201:**
```json
{
  "success": true,
  "data": {
    "id": 5,
    "lead_id": 42,
    "content": "El cliente mostró interés en el plan premium.",
    "author": "agente",
    "created_at": "2026-03-18T12:05:00.000000Z"
  },
  "message": "Nota agregada al lead."
}
```

---

## Cotizaciones

### Valores válidos de `status`

| Status | Descripción |
|---|---|
| `borrador` | Recién creada, no enviada |
| `enviada` | Entregada al prospecto |
| `aceptada` | El prospecto aceptó |
| `rechazada` | El prospecto rechazó |
| `vencida` | Expiró (vigencia 30 días) |

---

### `POST /quotes`
> Protegido · Requiere Bearer token

Genera una cotización para un lead. Incluye uno o más servicios con sus cantidades y precios. El número de cotización se genera automáticamente (formato `COT-YYYY-NNNN`). El IVA se aplica según la configuración del sistema. Si el lead no estaba en `cotizado` o `cerrado`, su estado se actualiza a `cotizado` automáticamente.

**Body:**
```json
{
  "lead_id": 42,
  "notes": "Propuesta inicial. Precios sujetos a revisión.",
  "items": [
    {
      "service_id": 1,
      "quantity": 1,
      "unit_price": 15000.00
    },
    {
      "service_id": 3,
      "quantity": 2
    }
  ]
}
```

| Campo | Tipo | Requerido | Descripción |
|---|---|---|---|
| `lead_id` | integer | ✅ | ID del lead al que pertenece la cotización |
| `notes` | string | ❌ | Notas o condiciones adicionales de la cotización |
| `items` | array | ✅ | Lista de items (mínimo 1) |
| `items[].service_id` | integer | ✅ | ID del servicio (de `GET /services`) |
| `items[].quantity` | integer | ✅ | Cantidad (mínimo 1) |
| `items[].unit_price` | number | ❌ | Precio unitario. Si se omite, se usa el precio del catálogo |

**Response 201:**
```json
{
  "success": true,
  "data": {
    "id": 7,
    "quote_number": "COT-2026-0007",
    "lead_id": 42,
    "status": "borrador",
    "iva_percentage": "16.00",
    "subtotal": "15000.00",
    "iva_amount": "2400.00",
    "total": "17400.00",
    "valid_until": "2026-04-17",
    "notes": "Propuesta inicial.",
    "created_at": "2026-03-18T12:00:00.000000Z",
    "lead": { "id": 42, "name": "Juan Pérez" },
    "items": [
      {
        "id": 1,
        "service_id": 1,
        "description": "Desarrollo Web",
        "quantity": 1,
        "unit_price": "15000.00",
        "total": "15000.00"
      }
    ]
  },
  "message": "Cotización generada exitosamente."
}
```

---

### `PATCH /quotes/{id}/status`
> Protegido · Requiere Bearer token

Actualiza el estado de una cotización.

**Body:**
```json
{ "status": "enviada" }
```

| Campo | Tipo | Requerido | Valores |
|---|---|---|---|
| `status` | string | ✅ | `borrador` \| `enviada` \| `aceptada` \| `rechazada` \| `vencida` |

**Response 200:**
```json
{
  "success": true,
  "data": { "id": 7, "status": "enviada", "..." : "..." },
  "message": "Estado de la cotización actualizado."
}
```

---

### `GET /quotes/{id}/pdf`
> Protegido · Requiere Bearer token

Descarga la cotización en formato PDF.

**Response:** Archivo binario PDF
```
Content-Type: application/pdf
Content-Disposition: attachment; filename="cotizacion-COT-2026-0007.pdf"
```

---

## Flujo recomendado

Este es el flujo de trabajo estándar que el agente debe seguir:

```
1. GET /agent/status          → Verificar si el agente está activo (si paused → stop)
2. GET /settings              → Obtener nombre, IVA y moneda del negocio
3. GET /services              → Consultar catálogo de servicios disponibles
4. GET /leads/search?phone=X  → Buscar si el contacto ya existe
   ├── Si existe → usar lead existente
   └── Si no existe → POST /leads (crear nuevo lead)
5. PATCH /leads/{id}/status   → Actualizar estado conforme avanza la conversación
6. POST /leads/{id}/notes     → Documentar puntos clave de la conversación
7. POST /quotes               → Generar cotización con los servicios seleccionados
8. PATCH /quotes/{id}/status  → Marcar como "enviada" al compartirla con el cliente
9. GET /quotes/{id}/pdf       → Obtener PDF si el cliente lo solicita
```

---

## Reglas de negocio

| Regla | Detalle |
|---|---|
| Moneda | Todos los precios en **MXN** |
| IVA | Se calcula automáticamente sobre el subtotal al crear una cotización |
| Vigencia de cotizaciones | 30 días desde la creación |
| Numeración de cotizaciones | Automática, formato `COT-YYYY-NNNN` |
| `source` en leads | Si no se especifica, se asigna `agente` automáticamente |
| Estado lead al cotizar | Se actualiza automáticamente a `cotizado` al crear una cotización (si no era `cerrado`) |
| Búsqueda antes de crear | Siempre buscar por teléfono o email antes de crear un lead nuevo para evitar duplicados |
| Agente pausado | Si `is_paused: true` en `/agent/status`, un humano tomó el control — el agente no debe responder |

---

## Códigos de error frecuentes

| HTTP | Causa típica |
|---|---|
| `401 Unauthorized` | Token inválido, expirado o ausente |
| `404 Not Found` | El recurso no existe (`lead_id` o `service_id` inválido) |
| `422 Unprocessable Entity` | Error de validación — revisar el campo `errors` en la respuesta |
| `429 Too Many Requests` | Límite de peticiones alcanzado — reducir frecuencia |
| `500 Internal Server Error` | Error interno del servidor |

---

## Ejemplo de sesión completa

```bash
# 1. Verificar estado del agente
GET /agent/status
→ { "is_paused": false, "status": "active" }

# 2. Buscar si el cliente ya existe
GET /leads/search?phone=5551234567
→ { "data": [] }  # No existe, crear nuevo

# 3. Crear lead
POST /leads
Body: { "name": "María García", "phone": "5551234567", "email": "maria@empresa.com", "project_description": "Quiere rediseño de sitio web con tienda en línea." }
→ { "data": { "id": 42, "status": "nuevo" } }

# 4. Documentar la conversación
POST /leads/42/notes
Body: { "content": "María necesita catálogo de 50 productos, pasarela de pago y diseño responsivo." }

# 5. Actualizar estado
PATCH /leads/42/status
Body: { "status": "contactado" }

# 6. Consultar servicios y crear cotización
GET /services
→ [ { "id": 1, "name": "E-Commerce", "price": "25000.00" } ]

POST /quotes
Body: {
  "lead_id": 42,
  "notes": "Incluye hosting primer año y capacitación.",
  "items": [{ "service_id": 1, "quantity": 1 }]
}
→ { "data": { "id": 7, "quote_number": "COT-2026-0007", "total": "29000.00" } }

# 7. Marcar cotización como enviada
PATCH /quotes/7/status
Body: { "status": "enviada" }
```
