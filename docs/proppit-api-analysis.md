# Proppit Real-Time API — Technical Analysis

| | |
|---|---|
| **Fecha revisión** | 2026-05-16 |
| **Swagger UI** | https://real-time.proppit.com/api/v2/docs |
| **OpenAPI spec** | https://real-time.proppit.com/api/v2/docs/openapi.yaml |
| **Versión spec** | OpenAPI 3.1.0 |
| **Contacto API** | ingester@lifullconnect.com |

---

## 1. Base URL

```
https://real-time.proppit.com/api/v2
```

Todos los paths en este documento son relativos a esa base.

---

## 2. Autenticación

**Esquema:** HTTP Bearer (`components.securitySchemes.Authorization`)

### Obtención del token

```
POST /token
Content-Type: application/json
```

**Request body:**

```json
{
  "user": "string",
  "password": "string"
}
```

**Response 200:**

```json
{
  "token": "string",
  "expiration": 1671620388
}
```

- `expiration` es un Unix timestamp que indica cuándo vence el token.
- Implementación SDK: el token se renueva automáticamente 30 segundos antes de expirar.

**Uso en endpoints protegidos:**

```
Authorization: Bearer <token>
```

---

## 3. Endpoints implementados en el SDK

### 3.1 Crear anuncio

```
POST /proppit/{country}/ads
Authorization: Bearer <token>
Content-Type: application/json
```

- **country** (path, required): código de país (ver enum Country)
- **Response exitosa:** `201 Created` — devuelve el Ad completo
- **SDK method:** `PropertyApi::publish()`

### 3.2 Actualizar anuncio

```
PUT /proppit/{country}/ads/{referenceId}
Authorization: Bearer <token>
Content-Type: application/json
```

- **referenceId** (path, required): identificador del anuncio
- El PUT realiza un **reemplazo completo** — reenviar todos los campos requeridos
- **Response exitosa:** `200 OK` — devuelve el Ad actualizado
- **SDK method:** `PropertyApi::update()`

### 3.3 Consultar anuncio

```
GET /proppit/{country}/ads/{referenceId}?externalId={publisherExternalId}
Authorization: Bearer <token>
```

- **externalId** (query, required): identificador externo del publisher
- **Nota técnica:** el YAML define el path como `'{referenceId}?externalId={external-id-publisher}'` pero en implementación HTTP se usa path limpio + query param
- **Response exitosa:** `200 OK`
- **SDK methods:** `PropertyApi::find()`, `PropertyApi::findByExternalId()`

### 3.4 Eliminar anuncio

```
DELETE /proppit/{country}/ads/{referenceId}?externalId={publisherExternalId}
Authorization: Bearer <token>
```

- **Response exitosa:** `200 OK` (body vacío)
- **SDK method:** `PropertyApi::delete()`

---

## 4. Endpoints documentados pero NO implementados en el SDK (fuera de scope actual)

### Publisher

| Method | Path | Descripción |
|---|---|---|
| POST | `/proppit/{country}/publishers` | Crear publisher |
| PUT | `/proppit/{country}/publishers/{id}` | Actualizar publisher |
| GET | `/proppit/{country}/publishers/{id}` | Consultar publisher |

### Property Types (catálogos)

| Method | Path | Descripción |
|---|---|---|
| GET | `/proppit/{country}/property-types` | Tipos de inmueble + valores permitidos de amenities, nearbyLocations y rules por país |

> **TODO (no implementado):** El catálogo de `property.type`, `amenities`, `nearbyLocations` y `rules` varía por país y debe consultarse en runtime con `GET /proppit/{country}/property-types`. No está embebido en el spec estático.

---

## 5. Schema principal: Ad

### 5.1 Campos requeridos

| Campo | Tipo | Reglas |
|---|---|---|
| `referenceId` | string | Identificador único del anuncio en el sistema del integrador |
| `publisher` | object | Ver AdPublisher |
| `publisher.externalId` | string | required |
| `property` | object | Ver Property |
| `property.type` | string (PropertyType) | Valores desde `/property-types` endpoint |
| `property.location` | object | Ver Location |
| `property.location.coordinates` | object | `lat` y `long` requeridos |
| `property.location.coordinates.lat` | number | |
| `property.location.coordinates.long` | number | |
| `operations` | array | Mínimo 1 elemento |
| `operations[].type` | enum | `sell` \| `rent` |
| `operations[].price` | object | |
| `operations[].price.value` | number | |
| `operations[].price.currency` | enum (Currency) | Ver 5.5 |
| `title` | LocalisedText | |
| `title.locale` | enum (Locale) | Ver 5.6 |
| `title.text` | string | |
| `description` | LocalisedText | |
| `description.locale` | enum (Locale) | |
| `description.text` | string | |

### 5.2 Campos opcionales

| Campo | Tipo | Notas |
|---|---|---|
| `contact.name` | string | |
| `contact.email` | string (email) | |
| `contact.phone` | string | |
| `contact.whatsapp` | string | |
| `property.location.countryCode` | enum (Country) | Ver 5.7 |
| `property.location.visibility` | enum | `accurate` \| `approximate` |
| `property.location.address` | string | |
| `property.location.postcode` | string | |
| `property.location.nearbyLocations[]` | string | Valores desde `/property-types` |
| `property.location.geo[]` | array | `{name, level}` — ver LocationLevel |
| `property.communityFees` | object | `{value, currency}` — cuota de administración |
| `property.floor` | string | Piso |
| `property.bankProperty` | boolean | Solo MX (propiedad en remate) |
| `property.project.name` | string | Debe coincidir exactamente con el proyecto en Proppit |
| `multimedia.pictures[].url` | string (URL) | Content-type debe ser `image/*` o `application/octet-stream` |
| `multimedia.floorPlans[].url` | string (URL) | |
| `multimedia.videos[].url` | string (URL) | |
| `multimedia.virtualTours[].url` | string (URL) | |
| `totalArea` | Area | **Obligatorio** para tipo `land`. Opcional para house, villa, commercial, industrial unit |
| `floorArea` | Area | **Obligatorio** para todos excepto `land` |
| `usableArea` | Area | Opcional |
| `isBoosted` | boolean | Default `false`. Aumenta visibilidad hasta el límite del plan |
| `bedrooms` | number (≥0) | |
| `bathrooms` | number (≥0) | |
| `halfBathrooms` | number (≥0) | Solo ciertos tipos y países (ver spec) |
| `stratum` | number (1–7) | **Solo CO** |
| `parkingSpaces` | number (≥0) | |
| `constructionYear` | number (1500–2100) | |
| `condition` | enum (Condition) | Ver 5.8 |
| `furnished` | enum | `fully` \| `partly` \| `unfurnished` |
| `rules` | array of string | Valores desde `/property-types` |
| `amenities` | array of string | Valores desde `/property-types` |
| `paymentMethodsDetails` | string | Descripción libre |
| `acceptedPaymentMethods` | enum | Solo MX: `forbidden_bank_credit`, `bank_credit`, `cash`, `infonavit`, `fovissste` |

### 5.3 Areas — reglas por tipo de inmueble

| Tipo | `floorArea` | `totalArea` |
|---|---|---|
| `apartment`, `office` | Total area → **floorArea** | — |
| `house`, `villa`, `commercial`, `industrial unit` | Área construida → **floorArea** | Área terreno → **totalArea** |
| `land` | — | Área terreno → **totalArea** (obligatorio) |

Unidad: siempre `sqm` (metros cuadrados).

### 5.4 LocationLevel enum

```
administrative_area_level_1 | administrative_area_level_2 | administrative_area_level_5
neighborhood | sublocality_level_1 | sublocality_level_2 | locality
aws_region | aws_sub_region | aws_minucipality | aws_neighborhood
```

### 5.5 Currency enum

```
AED | ARS | CLF | CLP | COP | EUR | MXN | PAB | PEN | PHP | THB | USD | VND | IDR
```

### 5.6 Locale enum

```
es-AR | es-CL | es-CO | es-MX | es-PE | en-PH | id-ID | th-TH | vi-VN | es-ES | es-PA | es-EC
```

### 5.7 Country enum (path param)

```
AE | AR | CL | CO | EC | ES | ID | MX | PA | PE | PH | TH | VN
```

### 5.8 Condition enum

```
excellent | good | normal | to renew | second hand | ruin | semi-renovated | semi-new | in construction | new | renovated
```

---

## 6. Error schema

Todos los errores devuelven el siguiente objeto:

```json
{
  "status": 400,
  "requestId": "uuid-string",
  "error": "Description of the failure",
  "timestamp": "2023-10-01T12:00:00Z"
}
```

| Campo | Tipo | Descripción |
|---|---|---|
| `status` | integer | HTTP status code |
| `requestId` | string | UUID para referenciar con soporte |
| `error` | string | Razón del error |
| `timestamp` | date-time | ISO 8601 UTC |

### Códigos de error documentados

| HTTP | Descripción | Excepción SDK |
|---|---|---|
| 400 Bad Request | Parámetros o body inválidos | `ApiException` |
| 401 Unauthorized | Token ausente o inválido | `AuthException` |
| 404 Not Found | Recurso no disponible para las credenciales dadas | `ApiException` |
| 500 Internal Server Error | Error no manejado — reportar a soporte | `ApiException` |
| 502 Bad Gateway | Error de comunicación — reintentar | `ApiException` (con retry) |
| 503 Service Unavailable | API temporalmente fuera de servicio | `ApiException` (con retry) |
| 504 Gateway Timeout | Timeout — reintentar | `ApiException` (con retry) |

---

## 7. Rate limits

El spec **no documenta** explícitamente un rate limit ni un header `Retry-After`.

El SDK implementa soporte defensivo para `429 Too Many Requests` y `Retry-After` por si la API los devuelve en runtime.

> **TODO:** Confirmar con soporte de Proppit si existen rate limits específicos y qué headers usan.

---

## 8. Retries implementados en el SDK

El SDK reintenta automáticamente en errores transitorios:

| Condición | Comportamiento |
|---|---|
| `429 Too Many Requests` | Retry con `Retry-After` si existe, sino backoff exponencial con jitter |
| `5xx` (500, 502, 503, 504) | Retry con backoff exponencial |
| `4xx` (salvo 429) | **No reintenta** — son errores del cliente |

Configurable vía `PROPIT_RETRY_ATTEMPTS` y `PROPIT_RETRY_DELAY_MS`.

---

## 9. TODOs pendientes por falta de documentación oficial

| ID | Descripción | Impacto |
|---|---|---|
| `TODO_PROPPIT_PROPERTY_TYPES` | Catálogo de `property.type`, `amenities`, `nearbyLocations`, `rules` varía por país. Debe obtenerse en runtime con `GET /property-types` | Medio — se puede usar valores conocidos de ejemplos del spec |
| `TODO_PROPPIT_RATE_LIMIT` | Rate limits no documentados en el spec | Bajo — el SDK maneja 429 de forma defensiva |
| `TODO_PROPPIT_404_SEMANTICS` | La doc dice que 404 "no necesariamente significa que no existe, sino que no está disponible para las credenciales dadas" | Bajo — SDK lo trata como ApiException |
| ~~`TODO_PROPPIT_PUBLISHER_WORKFLOW`~~ | ~~El flujo de activación del publisher requiere aprobación manual del equipo de Proppit antes de poder crear anuncios~~ | **Resuelto** — SDK lanza `PublisherNotReadyException` en 403 "Publisher could not publish". Ver `publisher-integration.md`. |
| `TODO_PROPPIT_HALFBATHROOMS_RULES` | Las reglas exactas de `halfBathrooms` por tipo y país no están completamente detalladas | Bajo |
