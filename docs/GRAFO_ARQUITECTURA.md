# Grafo de arquitectura — CRM Mosley

Mapa de dependencias del repositorio generado a partir del código (rutas, controladores, servicios, modelos, comandos, eventos y APIs externas). Sirve para orientarse en el proyecto, evaluar el impacto de un cambio y detectar acoplamientos.

| Artefacto | Qué contiene |
|---|---|
| `docs/graph/crm-graph.json` | Grafo completo: 142 nodos y 365 aristas tipadas. Fuente de verdad para herramientas. |
| `docs/graph/crm-overview.svg` / `.dot` | Vista de arquitectura: entradas → controladores → servicios → APIs externas (sin modelos). |
| `docs/graph/crm-models.svg` / `.dot` | Modelos Eloquent y sus relaciones. |
| `docs/graph/crm-graph.svg` / `.dot` | Todo junto (denso; útil para buscar un nodo concreto). |
| `scripts/build-graph.php` | Generador. Análisis estático puro, no necesita `vendor/`. |

Regenerar tras cambios en el código:

```bash
php scripts/build-graph.php                       # JSON + DOT en docs/graph/
dot -Tsvg docs/graph/crm-overview.svg ...         # con Graphviz instalado, o:
npx -p @viz-js/viz -c "viz" < docs/graph/crm-overview.dot > docs/graph/crm-overview.svg
```

Los diagramas de abajo son una lectura curada del mismo grafo (Mermaid, se renderizan en GitHub).

---

## 1. Resumen en números

| Capa | Cantidad | Detalle |
|---|---|---|
| Zonas de entrada HTTP | 23 | 18 web + 5 API, agrupadas por prefijo y permiso |
| Controladores | 46 | 26 `Admin/*`, 9 `Portal/*`, 5 `Api/*`, 2 `Auth/*`, 4 públicos (tienda, webhooks) |
| Servicios | 12 | 8 hablan con APIs externas, 4 son orquestadores internos |
| Modelos Eloquent | 32 | 64 relaciones; 43 tablas en migraciones |
| Comandos Artisan | 7 | 5 programados en el scheduler + `mail:test` + `api:token` |
| Mailables | 6 | Cada uno con su vista en `resources/views/emails/` |
| Eventos de modelo | 4 | Observers en `AppServiceProvider` que sincronizan con DM Champ |
| APIs externas | 8 | Mercado Pago, PayPal, Facturapi, Finkok, 20i, Cosmotown, DM Champ, Meta CAPI |
| Vistas Blade | 88 | admin (54), portal (11), tienda (7), emails (6), pdf (3), resto |
| Tests | 27 archivos | 171 casos (`Feature` salvo el del reparto de IVA) |

Stack: Laravel 12 · PHP 8.2+ · MySQL 8 · Blade + Alpine.js + Tailwind 4 · Sanctum · DomPDF · Docker/Nginx/Traefik.

---

## 2. Arquitectura por capas

Cómo entra una petición y hasta dónde llega. Las flechas rojas salen del sistema.

```mermaid
flowchart LR
  subgraph IN["Entradas"]
    direction TB
    PUB["Tienda pública<br/>/ · /buy · /login · /auth"]
    PANEL["Panel admin<br/>/panel · auth + role"]
    PORTAL["Portal cliente<br/>/portal/{token}"]
    API["API OpenClaw<br/>/api/v1 · Sanctum"]
    DMF["Funciones DM Champ<br/>/api/v1/dmchamp · token"]
    WH["Webhooks<br/>/api/webhooks/*"]
    CRON["Scheduler<br/>5 comandos diarios"]
  end

  subgraph CTRL["Controladores y comandos"]
    direction TB
    C_SHOP["DirectCheckout · Cart"]
    C_ADMIN["Admin/* · 26 controladores"]
    C_PORTAL["ClientPortal"]
    C_API["Api/Lead · Quote · Agent · Setting"]
    C_DMF["Api/DmChampFunction"]
    C_WH["MercadoPagoWebhook · PayPalWebhook · DmChampWebhook"]
    C_CMD["invoices:* · quotes:expire · dunning:process · db:backup"]
  end

  subgraph SVC["Servicios"]
    direction TB
    MP["MercadoPagoService"]
    PP["PayPalService"]
    OF["OrderFinalizationService"]
    INV["InvoicingManager"]
    FA["FacturapiService"]
    FK["FinkokService"]
    CFDI["CfdiBuilderService"]
    PROV["ProvisioningService"]
    T20["TwentyIService"]
    COS["CosmotownService"]
    DM["DmChampService"]
    META["MetaConversionsService"]
  end

  subgraph EXT["APIs externas"]
    direction TB
    X_MP[("Mercado Pago")]
    X_PP[("PayPal")]
    X_FA[("Facturapi · PAC")]
    X_FK[("Finkok · PAC SOAP")]
    X_20[("20i · hosting, correo, DNS")]
    X_COS[("Cosmotown · dominios")]
    X_DM[("DM Champ · WhatsApp")]
    X_META[("Meta Conversions API")]
  end

  PUB --> C_SHOP
  PANEL --> C_ADMIN
  PORTAL --> C_PORTAL
  API --> C_API
  DMF --> C_DMF
  WH --> C_WH
  CRON --> C_CMD

  C_SHOP --> MP & PP & PROV & COS & META
  C_ADMIN --> FA & T20 & COS & INV & PROV & META
  C_PORTAL --> MP & PP & FA & T20 & COS
  C_WH --> MP & PP & PROV & META & DM
  C_CMD --> FA

  MP --> OF
  PP --> OF
  OF --> INV
  INV --> FA
  INV --> FK
  FK --> CFDI
  PROV --> T20
  PROV --> COS

  MP -.-> X_MP
  PP -.-> X_PP
  FA -.-> X_FA
  FK -.-> X_FK
  T20 -.-> X_20
  COS -.-> X_COS
  DM -.-> X_DM
  META -.-> X_META

  linkStyle 37,38,39,40,41,42,43,44 stroke:#dc2626,stroke-width:2px
```

Lecturas rápidas:

- **Los controladores públicos y de portal llegan directo a los servicios de pago.** `DirectCheckoutController`, `CartController` y `ClientPortalController` instancian `MercadoPagoService` y `PayPalService`; los webhooks cierran el ciclo.
- **`OrderFinalizationService` es el embudo post-pago.** Lo invocan `MercadoPagoService` y `PayPalService` una sola vez cuando la orden pasa a pagada: timbra el CFDI vía `InvoicingManager` y envía `PaymentConfirmed`.
- **`InvoicingManager` decide el PAC** según `Setting('invoicing_provider')`: Facturapi (timbra el PAC) o Finkok (el CRM construye y sella el XML con `CfdiBuilderService`, Finkok solo timbra).
- **`ProvisioningService` es idempotente**: registra el dominio en Cosmotown y crea el hosting en 20i tras un pago confirmado.
- `Api/*` (OpenClaw) y `Api/DmChampFunction` no tocan servicios externos: solo leen y escriben modelos.
- **Los servicios se resuelven por el contenedor** (`scoped`) y se inyectan por constructor; leen su configuración de Ajustes en cada uso.

---

## 3. Flujo de negocio: del pago a la factura y el aprovisionamiento

Es el camino más largo del sistema y el que cruza más capas.

```mermaid
flowchart TD
  A["Cliente paga<br/>tienda · carrito · portal"] --> B{Método}
  B -->|Tarjeta| MP["MercadoPagoService<br/>pago síncrono"]
  B -->|OXXO · SPEI| MPW["Mercado Pago<br/>webhook HMAC"]
  B -->|PayPal| PP["PayPalService<br/>capture"]
  B -->|Transferencia| MAN["OrderController::approveTransfer<br/>aprobación manual en el panel"]

  MP --> FIN
  MPW --> FIN
  PP --> FIN
  MAN --> FIN

  FIN["OrderFinalizationService::finalize(payment)<br/>una sola vez por orden"]
  FIN --> STAMP{"billing_preference ≠ none<br/>y PAC configurado"}
  STAMP -->|no| MAIL
  STAMP -->|sí| YA{"¿la factura ya<br/>estaba timbrada?"}

  YA -->|"no · se timbra al cobrar<br/>(PPD liquidada → PUE)"| INV["InvoicingManager::stampInvoice"]
  INV -->|invoicing_provider = facturapi| FA["Facturapi"]
  INV -->|invoicing_provider = finkok| CFDI["CfdiBuilderService<br/>XML tipo I + sello CSD"] --> FK["Finkok · timbrado"]
  FA --> FD["FiscalDocument<br/>source = facturapi"]
  FK --> FD2["FiscalDocument<br/>source = finkok"]

  YA -->|"sí y es PPD"| REP["InvoicingManager::issuePaymentComplement<br/>CFDI tipo P · uno por pago"]
  REP --> PC["PaymentComplement<br/>valid · pending · failed"]

  FD --> MAIL
  FD2 --> MAIL
  PC --> MAIL
  MAIL["Mail PaymentConfirmed<br/>comprobante al cliente"]

  FIN --> PROV["ProvisioningService::provisionForOrder"]
  PROV --> COS["Cosmotown · registrar dominio<br/>+ contactos WHOIS"]
  PROV --> T20["20i · crear hosting<br/>+ buzones si el paquete lo incluye"]
  FIN --> META["MetaConversionsService::sendPurchase<br/>event_id = order_id"]
  A -. "Order::created, al crear la orden" .-> DMC["DmChampService::notifyInvoiceCreated<br/>aviso por WhatsApp"]
```

Notas del flujo:

- Todo el post-pago cuelga de `OrderFinalizationService`, incluidos el aprovisionamiento y el evento de Meta, que antes llamaba cada controlador por su cuenta.
- La cancelación de CFDI y la emisión del complemento se enrutan por el proveedor que **timbró** (`FiscalDocument.source`), no por el ajuste actual: cambiar de PAC no rompe cancelaciones antiguas, y el REP tiene que salir del mismo sitio que la factura que relaciona.
- Los conceptos del CFDI los deriva `Order::fiscalLines()` para los dos PAC, y `Order::assertChargedTotalMatches()` impide timbrar un total distinto al cobrado.

---

## 4. Modelo de datos

Relaciones declaradas en los modelos Eloquent. `ActivityLog` y `Setting` son transversales y se omiten del grafo para no ensuciarlo.

```mermaid
erDiagram
  USER ||--o{ LEAD : "assigned_to"
  USER ||--o{ PERMISSION : tiene
  USER ||--o{ NOTIFICATION : recibe
  USER ||--o{ AGENT_CONTROL : registra
  USER ||--o{ ACTIVITY_LOG : genera
  ACTIVITY_LOG }o--|| SUBJECT : "subject (polimórfico)"

  LEAD ||--o{ LEAD_NOTE : tiene
  LEAD ||--o{ LEAD_STATUS_HISTORY : historial
  LEAD ||--o{ QUOTE : recibe
  LEAD ||--o{ TASK : tiene
  LEAD ||--o| CLIENT : "se convierte en"

  QUOTE ||--o{ QUOTE_ITEM : contiene
  QUOTE_ITEM }o--|| SERVICE : referencia
  QUOTE ||--o{ ORDER : genera
  QUOTE ||--o{ RECURRING_INVOICE_SCHEDULE : origina

  CLIENT ||--o{ ORDER : "invoices()"
  CLIENT ||--o{ CLIENT_SERVICE : contrata
  CLIENT_SERVICE }o--|| SERVICE : referencia
  CLIENT ||--o{ CLIENT_DOCUMENT : guarda
  CLIENT }o--o{ TAG : "client_tag"
  CLIENT ||--o{ SUPPORT_TICKET : abre
  CLIENT ||--o{ TASK : tiene
  CLIENT ||--o{ RECURRING_INVOICE_SCHEDULE : tiene
  CLIENT ||--o{ TICKET_REPLY : escribe

  SUPPORT_TICKET ||--o{ TICKET_REPLY : tiene
  SUPPORT_TICKET }o--o| USER : "assigned_to"
  TICKET_REPLY }o--o| USER : escribe
  TASK }o--o| USER : "assigned_to · created_by"

  RECURRING_INVOICE_SCHEDULE ||--o{ RECURRING_INVOICE_ITEM : contiene

  ORDER ||--o{ INVOICE_ITEM : contiene
  ORDER ||--o{ PAYMENT : recibe
  ORDER ||--o| FISCAL_DOCUMENT : "CFDI timbrado"
  ORDER ||--o{ PAYMENT_COMPLEMENT : "REP · sólo PPD"
  PAYMENT ||--o| PAYMENT_COMPLEMENT : "uno por pago"
  ORDER ||--o{ DUNNING_ATTEMPT : reintentos

  SERVICE_CATEGORY ||--o{ SERVICE : agrupa
  SERVICE_BUNDLE ||--o{ SERVICE_BUNDLE_ITEM : contiene
  SERVICE_BUNDLE_ITEM }o--|| SERVICE : referencia
  CART_ITEM }o--|| SERVICE : referencia
```

Modelos sin relaciones Eloquent: `DiscountCode` y `Setting` (clave/valor con caché).

Núcleo del dominio por grado de conexión: **Client** (11 relaciones), **User** (9), **Order** (8), **Lead** (6), **Service** (6). Un cambio en `Client` u `Order` afecta a casi todos los controladores.

---

## 5. Automatizaciones: scheduler y eventos de modelo

```mermaid
flowchart LR
  subgraph CRON["Scheduler · routes/console.php"]
    direction TB
    S1["02:00 db:backup"]
    S2["06:00 invoices:process-recurring"]
    S3["07:00 quotes:expire"]
    S4["09:00 invoices:send-reminders --days=7"]
    S5["10:00 dunning:process"]
  end

  S1 --> BK["Dump MySQL comprimido<br/>conserva los últimos 10"]
  S2 --> RIS["RecurringInvoiceSchedule → Order"] --> FA["FacturapiService::stampInvoice<br/>si auto_stamp"]
  S3 --> QE["Quote.status = expired"]
  S4 --> PR1["Mail PaymentReminder"]
  S5 --> DUN["DunningAttempt + Notification"] --> PR2["Mail PaymentReminder"]

  subgraph OBS["Observers · AppServiceProvider"]
    direction TB
    E1["Lead::created"]
    E2["Client::created"]
    E3["Order::created"]
    E4["Quote::updated · status = sent"]
  end

  E1 -->|syncLead| DM["DmChampService"]
  E2 -->|syncClient| DM
  E3 -->|notifyInvoiceCreated| DM
  E4 -->|notifyQuoteSent| DM
  DM -.-> X["DM Champ API · WhatsApp"]

  PANEL["/panel/backups · POST create"] -. "Artisan::call" .-> S1
```

`DmChampService` está registrado como singleton y se resuelve perezosamente en cada observer; si `dmchamp_enabled` está apagado las llamadas son no-op.

---

## 6. Zonas de acceso

Cada zona es un prefijo con su middleware de grupo. El middleware inline por ruta (`throttle:payments`, `throttle:login`, …) está en `crm-graph.json` en la meta de cada arista `routes_to`.

| Zona | Middleware | Controladores |
|---|---|---|
| `/`, `/buy` | ninguno (público) | DirectCheckout, Cart |
| `/login`, `/logout`, `/auth` | ninguno · `throttle:login`, `throttle:5,1` | Auth/Login, Auth/MagicLink |
| `/panel` | `auth` + `can:*` | Cada sección exige su permiso: `leads.view`/`leads.manage`, `quotes.view`/`quotes.manage`, `clients.view`/`clients.manage` (incluye dominios, buzones y DNS), `tickets.view`/`tickets.manage`, `invoices.manage`, `reports.view` |
| `/panel` | `auth` + `role:admin` | ServiceCategory, Service, ServiceBundle, Setting, AgentControl, ActivityLog, DiscountCode, Tag, Permission, User (`role:admin` inline) |
| `/panel` | `auth` + `can:invoices.manage` | Order, RecurringInvoice |
| `/panel/reports` | `auth` + `can:reports.view` | Report |
| `/panel/backups` | `auth` + `role:admin` | closures → `db:backup` |
| `/panel/notifications` | `auth` | Notification |
| `/portal/{token}` | `portal` (ValidatePortalToken) | Portal/* — ocho controladores, 37 rutas |
| `/api/v1` | `throttle:60,1` (público) | closures: `test`, `services` |
| `/api/v1` | `auth:sanctum` + `throttle:120,1` + `abilities:*` | Api/Lead, Api/Quote, Api/Agent, Api/Setting — cada ruta exige el permiso que lleva escrito el token (`leads:read`, `leads:write`, `quotes:read`, `quotes:write`, `agent:read`, `settings:read`) |
| `/api/v1/dmchamp` | `throttle:60,1` + `no.cache` + DmChampTokenMiddleware | Api/DmChampFunction |
| `/api/webhooks` | `throttle:webhooks` (firma HMAC / verificación PayPal en el controlador) | MercadoPagoWebhook, PayPalWebhook, DmChampWebhook |

---

## 7. Hallazgos derivados del grafo

1. ~~**`ClientInvoice` es código muerto.**~~ **Resuelto.** El modelo huérfano se eliminó junto con la tabla `client_invoices` y las tres columnas `client_invoice_id` que quedaban en `payments`, `invoice_items` y `dunning_attempts`.
2. ~~**`invoices:process-recurring` salta `InvoicingManager`.**~~ **Resuelto.** El comando enruta el timbrado por el manager, que decide el PAC según `invoicing_provider`.
3. ~~**`ClientPortalController` es el nodo más cargado del grafo.**~~ **Resuelto.** Sus 1138 líneas se repartieron en ocho controladores bajo `app/Http/Controllers/Portal/` sobre una clase base común. Los nombres de ruta y las URIs no cambiaron.
4. ~~**Post-pago repartido en cinco sitios.**~~ **Resuelto.** `OrderFinalizationService` es ahora el embudo único: timbrado, comprobante, aprovisionamiento, cupón y evento de Meta. La transición a pagada vive en `Order::markPaid()` y es idempotente.
5. ~~**Servicios instanciados con `new` en lugar del contenedor.**~~ **Resuelto.** Los once servicios de integración se registran como `scoped` y se inyectan por constructor, y las credenciales se leen de Ajustes en cada uso en vez de cachearse al construir. (El hallazgo original decía que los tests usaban `Http::fake()`: no era cierto, evitaban la red dejando las credenciales vacías. Ahora sí hay `Http::fake()` y dobles inyectados por el contenedor.)
6. ~~**Las facturas PPD no llevaban complemento de pago.**~~ **Resuelto.** Un CFDI timbrado como PPD obliga a emitir un REP por cada cobro; no se emitía ninguno (el método de Facturapi existía sin que lo llamara nadie —y con un tipo `Payment` que ni siquiera resolvía— y con Finkok no existía). Ahora se emite por `InvoicingManager::issuePaymentComplement()`, queda registrado en `payment_complements` y el panel muestra los pendientes. De raíz: una venta liquidada al timbrarse se marca PUE en vez de PPD, así que la mayoría ya no genera obligación.
7. ~~**Los servicios exentos de IVA se cobraban con IVA.**~~ **Resuelto.** El impuesto se reparte línea por línea en `App\Support\TaxBreakdown`, con el descuento prorrateado entre la parte gravada y la exenta.
8. ~~**Los tokens de la API valían para todo.**~~ **Resuelto.** El único sitio donde nacía un token era el seeder, con `createToken('openclaw-agent')` a secas: sin lista de permisos —que en Sanctum significa comodín `*`— y colgando del administrador. Ahora cada token lleva su alcance escrito (`App\Support\ApiAbilities`), las rutas lo exigen con el middleware `abilities`, la identidad es una cuenta de máquina sin permisos de panel, y `php artisan api:token` emite, lista y revoca. (El hallazgo original decía además que no caducaban: no era cierto, `sanctum.expiration` lleva 30 días y se aplica; ahora hay un test que lo fija.)
9. **Acceso a `/api/v1/services` es público** (solo `throttle`), expone catálogo y precios sin token. Es intencional: lo consume el agente OpenClaw. Se corrigió que devolviera también los servicios no marcados como públicos.
10. **Cosmotown apunta a sandbox por defecto** (`cosmotown_base_url` en `Setting`); el valor real está configurado en producción, así que el default no se cambia.

---

## 8. Cómo leer `crm-graph.json`

```jsonc
{
  "nodes": [{ "id": "service:InvoicingManager", "label": "InvoicingManager", "type": "service", "meta": { "file": "...", "description": "..." } }],
  "edges": [{ "from": "routes:web:/panel|auth,role:admin,accounting", "to": "controller:Admin/OrderController", "type": "routes_to",
              "meta": { "routes": ["PATCH /panel/orders/{order}/stamp → stamp()"], "middleware": [] } }]
}
```

Tipos de nodo: `routes`, `scheduler`, `middleware`, `controller`, `command`, `service`, `mail`, `model`, `event`, `external`.

Tipos de arista: `routes_to`, `guarded_by`, `uses_service`, `uses_model`, `sends_mail`, `calls_api`, `relation` (con `kind` y `method`), `schedules` (con `when`), `invokes`, `fires`.

Ejemplo, listar todo lo que toca `Order`:

```bash
php -r '$g=json_decode(file_get_contents("docs/graph/crm-graph.json"),true);
  foreach($g["edges"] as $e) if($e["to"]==="model:Order") echo $e["from"],"\n";'
```
