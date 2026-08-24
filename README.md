<p align="center">
  <a href="https://homlity.com/">
    <img src="https://homlity.com/wp-content/uploads/2026/08/Diseno-sin-titulo-1-e1787507729419-1024x338.webp" alt="Homlity" width="420">
  </a>
</p>

<h1 align="center">Homlity SDK for Proppit</h1>

<p align="center">
  SDK oficial de <a href="https://homlity.com/">Homlity</a> en PHP/Laravel para publicar,
  actualizar, consultar y eliminar inmuebles en el portal
  <a href="https://real-time.proppit.com/api/v2/docs">Proppit (Real-Time API v2)</a>.
</p>

<p align="center">
  <a href="https://homlity.com/">homlity.com</a> ·
  <a href="https://homlity.com/desarrolladores/">Portal de desarrolladores</a> ·
  <a href="https://github.com/homlity/sdk-propit">GitHub</a> ·
  <a href="https://packagist.org/packages/homlity/sdk-proppit">Packagist</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-%E2%89%A5%208.1-777BB4?logo=php&logoColor=white" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/Laravel-9%20%7C%2010%20%7C%2011-FF2D20?logo=laravel&logoColor=white" alt="Laravel 9/10/11">
  <img src="https://img.shields.io/badge/packagist-homlity%2Fsdk--proppit-F28D1A?logo=packagist&logoColor=white" alt="Packagist">
</p>

---

## Tabla de contenidos

1. [¿Para qué sirve este SDK?](#1-para-qué-sirve-este-sdk)
2. [Arquitectura en 30 segundos](#2-arquitectura-en-30-segundos)
3. [Instalación](#3-instalación)
4. [Configuración](#4-configuración)
5. [Autenticación](#5-autenticación)
6. [Quick start](#6-quick-start)
7. [Publishers (inmobiliarias)](#7-publishers-inmobiliarias)
8. [Propiedades (anuncios)](#8-propiedades-anuncios)
9. [Referencia del payload de anuncio](#9-referencia-del-payload-de-anuncio)
10. [Manejo de errores](#10-manejo-de-errores)
11. [Reintentos, timeouts y rate limits](#11-reintentos-timeouts-y-rate-limits)
12. [Logging estructurado](#12-logging-estructurado)
13. [Extender el SDK vía DI](#13-extender-el-sdk-vía-di)
14. [Testing](#14-testing)
15. [Receta completa: integrar una inmobiliaria de cero](#15-receta-completa-integrar-una-inmobiliaria-de-cero)
16. [Referencia de API del SDK](#16-referencia-de-api-del-sdk)
17. [Troubleshooting](#17-troubleshooting)
18. [Soporte y contribución](#18-soporte-y-contribución)

---

## 1. ¿Para qué sirve este SDK?

Proppit (LIFULL Connect) es el portal donde las inmobiliarias publican su inventario.
Su **Real-Time API v2** permite enviar anuncios por API en lugar de cargarlos a mano
desde el panel web. Este SDK es la capa PHP que Homlity —y cualquier CRM, lonja o
portal que se integre con Homlity— usa para hablar con esa API sin escribir HTTP a mano.

**Qué te resuelve:**

| Problema | Qué hace el SDK |
|---|---|
| Autenticación con token que expira | Pide el token, lo cachea en memoria y lo renueva 30 s antes de expirar |
| Payloads que Proppit rechaza con 400 | Valida y normaliza el payload **antes** de la llamada HTTP (enums de moneda, locale, área, coordenadas, stratum…) |
| Errores HTTP crudos | Los convierte en excepciones tipadas (`AuthException`, `RateLimitException`, `PublisherPermissionException`…) |
| Caídas intermitentes / 429 | Reintenta 5xx y 429 con backoff exponencial + jitter y respeta `Retry-After` |
| Publisher creado pero no habilitado | Modela explícitamente el estado de activación (`pending_activation` / `active`) y lanza una excepción específica en el 403 de Proppit |
| Secretos en logs | `StructuredLogger` redacta tokens, secrets, emails y teléfonos automáticamente |
| Laravel | Auto-discovery, config publicable y bindings de contenedor listos |

**Qué NO hace (por diseño):**

- No gestiona colas ni jobs — eso lo pone tu aplicación (`ShouldQueue`, Horizon, etc.).
- No persiste nada en base de datos — tú decides qué guardar (`referenceId`, estado del publisher…).
- No sube imágenes: envías URLs públicas y Proppit las descarga.
- No implementa todavía `GET /property-types` (catálogo de tipos, amenities y reglas por país).

---

## 2. Arquitectura en 30 segundos

```
Tu aplicación (Homlity / CRM / lonja)
        │
        ▼
  ProppitClient                       ← fachada: properties() y publishers()
        ├── PropertyApi              ← POST/PUT/GET/DELETE /proppit/{country}/ads
        └── PublisherApi             ← POST/PUT/GET  /proppit/{country}/publishers
                │
                ▼
     PropertyPayloadNormalizer       ← valida el payload antes de salir a la red
     PublisherPayloadNormalizer
                │
                ▼
     GuzzleProppitHttpClient          ← retries, backoff, mapeo de errores, logging
                │
                ▼
  ClientCredentialsAuthenticator     ← POST /token → Bearer token cacheado
                │
                ▼
     Proppit Real-Time API v2
```

Cada capa está detrás de una interfaz (`src/Contracts/`), así que cualquier pieza es
reemplazable desde el contenedor de Laravel. Ver [§13](#13-extender-el-sdk-vía-di).

**Modelo de datos:**

```
Homlity  (PROPPIT_CLIENT_ID / PROPPIT_CLIENT_SECRET — credenciales del sistema)
└── Publisher A  id = homlity_agency_10      ← Inmobiliaria Alfa
│   └── Ads: CO-001, CO-002, CO-003          ← referenceId generado por ti
└── Publisher B  id = homlity_agency_25      ← Inmobiliaria Beta
    └── Ads: CO-100, CO-101
```

> **Regla de oro:** `client_id` / `client_secret` son del **sistema**, no de cada
> inmobiliaria. Cada inmobiliaria se identifica con un `publisher.externalId`
> que genera y mantiene tu aplicación.

| Concepto | Dónde vive | Scope |
|---|---|---|
| `PROPPIT_CLIENT_ID` / `PROPPIT_CLIENT_SECRET` | `.env` | Global — toda la instalación |
| `publisher.externalId` (`homlity_agency_{id}`) | Base de datos | Por inmobiliaria |
| `proppit_publisher_id` (devuelto por Proppit) | Base de datos | Por inmobiliaria |
| `referenceId` (`CO-{inmueble_id}`) | Base de datos | Por inmueble |

---

## 3. Instalación

**Requisitos:** PHP ≥ 8.1, ext-json, Composer 2. Laravel 9/10/11 es opcional.

```bash
composer require homlity/sdk-proppit
```

> **Nota de estado:** mientras no exista un tag estable publicado en Packagist,
> instala desde la rama principal:
>
> ```bash
> composer require homlity/sdk-proppit:dev-main
> ```
>
> …y si tu proyecto usa `minimum-stability: stable`, añade:
>
> ```json
> { "minimum-stability": "dev", "prefer-stable": true }
> ```

### Instalar desde el repositorio Git (sin Packagist)

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/homlity/sdk-propit" }
  ],
  "require": {
    "homlity/sdk-proppit": "dev-main"
  }
}
```

### Dependencias

| Paquete | Versión | Para qué |
|---|---|---|
| `guzzlehttp/guzzle` | ^7.8 | Transporte HTTP |
| `psr/log` | ^2.0 \| ^3.0 | Logging estructurado (opcional en runtime) |
| `illuminate/support` | ^9 \| ^10 \| ^11 | Service provider de Laravel |

En Laravel el SDK se registra solo vía **package auto-discovery**
(`Proppit\Laravel\ProppitServiceProvider`). No hay que tocar `config/app.php`.

---

## 4. Configuración

### Publicar el archivo de configuración (Laravel)

```bash
php artisan vendor:publish --tag=proppit-config
# → config/proppit.php
```

### Variables de entorno

```dotenv
# ── Obligatorias ──────────────────────────────────────────────────────────────
PROPPIT_BASE_URL=https://real-time.proppit.com/api/v2
PROPPIT_CLIENT_ID=tu-client-id-entregado-por-proppit
PROPPIT_CLIENT_SECRET=tu-client-secret-entregado-por-proppit
PROPPIT_COUNTRY=CO

# ── Opcionales ────────────────────────────────────────────────────────────────
PROPPIT_TIMEOUT=30
PROPPIT_RETRY_ATTEMPTS=3
PROPPIT_RETRY_DELAY_MS=500
PROPPIT_USER_AGENT="Homlity-Proppit-SDK/1.0"
PROPPIT_ENABLE_STRUCTURED_LOGS=true
```

| Variable | Default | Descripción |
|---|---|---|
| `PROPPIT_BASE_URL` | `https://real-time.proppit.com/api/v2` | Base de la API. Se valida como URL y se le quita la `/` final |
| `PROPPIT_CLIENT_ID` | — | **Requerido.** Se envía como `user` en `POST /token` |
| `PROPPIT_CLIENT_SECRET` | — | **Requerido.** Se envía como `password` en `POST /token`. Nunca se loguea |
| `PROPPIT_COUNTRY` | `CO` | Path param de todos los endpoints. Se normaliza a mayúsculas. Valores: `AE AR CL CO EC ES ID MX PA PE PH TH VN` |
| `PROPPIT_TIMEOUT` | `30` | Timeout por request en segundos (> 0) |
| `PROPPIT_RETRY_ATTEMPTS` | `3` | Reintentos adicionales sobre 429/5xx (≥ 0) |
| `PROPPIT_RETRY_DELAY_MS` | `500` | Base del backoff exponencial en ms (≥ 0) |
| `PROPPIT_USER_AGENT` | `Homlity-Proppit-SDK/1.0` | User-Agent enviado a Proppit |
| `PROPPIT_ENABLE_STRUCTURED_LOGS` | `true` | Activa el logger PSR-3 estructurado |

**Claves de config adicionales** (solo vía `config/proppit.php` o `ProppitConfig::fromArray()`):

| Clave | Descripción |
|---|---|
| `custom_headers` | Array de headers extra en cada request (p. ej. trazas internas) |
| `publisher_external_id` | `externalId` por defecto para `find()` y `delete()` de propiedades. Útil en instalaciones mono-inmobiliaria |

> Si trabajas multi-inmobiliaria, **no** definas `publisher_external_id` global:
> usa `findByExternalId($externalId, $referenceId)` y pasa el ID de cada agencia.

**Alias legacy aceptados** (deprecados, por compatibilidad con versiones previas):

| Legacy | Actual |
|---|---|
| `PROPIT_*` (una sola `p`) | `PROPPIT_*` |
| `api_user` / `api_key` | `client_id` |
| `api_password` / `api_secret` | `client_secret` |

Cada clave lee primero el nombre `PROPPIT_*` y cae al `PROPIT_*` antiguo si no existe,
así que un `.env` escrito antes del renombrado sigue funcionando sin cambios. Los nombres
de una sola `p` están deprecados: migra a `PROPPIT_*` cuando puedas.

### Validación temprana de la configuración

`ProppitConfig` valida al construirse y falla rápido:

| Situación | Excepción |
|---|---|
| `base_url` no es URL válida | `InvalidArgumentException` |
| `client_id` vacío | `AuthException` |
| `client_secret` vacío | `AuthException` |
| `timeout <= 0`, `retry_attempts < 0`, `retry_delay_ms < 0` | `InvalidArgumentException` |

Para depurar la configuración sin filtrar secretos:

```php
logger()->info('proppit_config', app(\Proppit\Config\ProppitConfig::class)->redacted());
// client_id => "abcd***", client_secret => "***"
```

---

## 5. Autenticación

No tienes que hacer nada: el SDK autentica solo. Así funciona por dentro:

1. `ClientCredentialsAuthenticator` llama `POST {base_url}/token` con
   `{"user": PROPPIT_CLIENT_ID, "password": PROPPIT_CLIENT_SECRET}`.
2. Proppit responde `{"token": "...", "expiration": <unix_ts>}`.
3. El token se guarda **en memoria en la instancia** y se reutiliza.
4. Se renueva automáticamente cuando faltan menos de **30 segundos** para expirar.
5. Cada request lleva `Authorization: Bearer <token>`.

```php
// El authenticator es un contrato — puedes sustituirlo (p. ej. cachear el token en Redis)
interface ProppitAuthenticatorInterface {
    public function authenticate(array $headers = []): array;
}
```

| Respuesta de `/token` | Resultado |
|---|---|
| 401 / 403 | `AuthException` — "Proppit rejected the client credentials…" |
| 2xx sin `token` o sin `expiration` | `AuthException` — respuesta inválida |
| Error de red | `AuthException` con la excepción de Guzzle como `previous` |

> **Nota de rendimiento:** el token se cachea por instancia, y en Laravel
> `ProppitClient` es un singleton — así que dentro de un mismo request/job solo se
> pide un token. En workers de larga vida se renueva solo. Si ejecutas muchos
> procesos cortos (p. ej. un comando por inmueble) considera implementar un
> authenticator que cachee el token en Redis para no pedir uno por proceso.

---

## 6. Quick start

### Con Laravel

```php
use Proppit\ProppitClient;

$client = app(ProppitClient::class);   // singleton, ya cableado

$client->properties();   // PropertyApiInterface
$client->publishers();   // PublisherApiInterface
```

También puedes inyectarlo por constructor:

```php
final class SincronizarInmuebleJob implements ShouldQueue
{
    public function __construct(private readonly int $inmuebleId) {}

    public function handle(ProppitClient $proppit): void
    {
        $proppit->properties()->publish($this->payload());
    }
}
```

### Standalone (sin Laravel)

```php
use GuzzleHttp\Client;
use Proppit\Api\PropertyApi;
use Proppit\Api\PublisherApi;
use Proppit\Auth\ClientCredentialsAuthenticator;
use Proppit\Config\ProppitConfig;
use Proppit\Http\GuzzleProppitHttpClient;
use Proppit\Normalizers\PropertyPayloadNormalizer;
use Proppit\Normalizers\PublisherPayloadNormalizer;
use Proppit\ProppitClient;
use Proppit\Support\StructuredLogger;

$config = ProppitConfig::fromArray([
    'base_url'      => 'https://real-time.proppit.com/api/v2',
    'client_id'     => getenv('PROPPIT_CLIENT_ID'),
    'client_secret' => getenv('PROPPIT_CLIENT_SECRET'),
    'country'       => 'CO',
]);

$guzzle = new Client();
$auth   = new ClientCredentialsAuthenticator($config, $guzzle);
$logger = new StructuredLogger(enabled: true, logger: $miLoggerPsr3);   // o new StructuredLogger(false)
$http   = new GuzzleProppitHttpClient($guzzle, $config, $auth, $logger);

$client = new ProppitClient(
    properties: new PropertyApi($http, new PropertyPayloadNormalizer(), $config),
    publishers: new PublisherApi($http, new PublisherPayloadNormalizer(), $config),
);
```

Ejemplos ejecutables completos en [`examples/`](examples/):
[`sync_publisher.php`](examples/sync_publisher.php),
[`publish_property.php`](examples/publish_property.php),
[`update_property.php`](examples/update_property.php),
[`find_property.php`](examples/find_property.php).

---

## 7. Publishers (inmobiliarias)

### Regla fundamental: publisher enviado ≠ publisher activo

Crear un publisher en Proppit **no** lo habilita para publicar. Proppit debe
activarlo/conectarlo manualmente. Hasta entonces, cualquier intento de publicar
un anuncio responde:

```json
{ "status": 403, "error": "Publisher could not publish" }
```

Esto **no** es un problema de credenciales. El SDK lo modela explícitamente.

### Registrar o actualizar un publisher

```php
use Proppit\DTO\PublisherPayload;

$externalId = 'homlity_agency_' . $inmobiliaria->uuid;

$response = $client->publishers()->createOrUpdate(new PublisherPayload(
    externalId: $externalId,                    // → campo `id` en la API de Proppit
    name:       $inmobiliaria->nombre,
    email:      $inmobiliaria->email_contacto,
    phone:      $inmobiliaria->telefono,        // opcional
));

$inmobiliaria->update([
    'proppit_external_id'      => $externalId,
    'proppit_publisher_id'     => $response->publisherId(),
    'proppit_publisher_status' => $response->activationStatus,  // 'pending_activation'
    'proppit_can_publish'      => $response->canPublish(),      // false por defecto
    'proppit_last_request_id'  => $response->requestId,
    'proppit_last_synced_at'   => now(),
]);
```

`createOrUpdate()` hace un `find()` interno y decide entre `POST` (crear) y `PUT` (actualizar).

### Reglas del `externalId`

- Único y **estable** por inmobiliaria — cambiarlo desconecta el publisher ya activado.
- Solo `letras, dígitos, - _ . @`, máximo 255 caracteres (validado por el SDK).
- No uses el email, nombre comercial o NIT como valor principal; usa el UUID/ID interno.
- Formato recomendado: `homlity_agency_{uuid}`.

### Consultar el estado de activación

```php
$response = $client->publishers()->status($externalId);   // lanza ApiException(404) si no existe

if ($response->canPublish()) {
    $inmobiliaria->update([
        'proppit_publisher_status' => $response->activationStatus,   // 'active'
        'proppit_can_publish'      => true,
        'proppit_activated_at'     => now(),
    ]);
}
```

`find()` devuelve `null` en 404 en vez de lanzar; `status()` lanza. Elige según el caso.

### Estados de activación

| Constante | Valor | Significado |
|---|---|---|
| `STATUS_PENDING_SYNC` | `pending_sync` | Todavía no enviado a Proppit |
| `STATUS_SYNCED` | `synced` | Enviado; Proppit no confirmó activación |
| `STATUS_PENDING_ACTIVATION` | `pending_activation` | **Default** tras create/update |
| `STATUS_ACTIVE` | `active` | Proppit confirmó que puede publicar |
| `STATUS_CANNOT_PUBLISH` | `cannot_publish` | Proppit respondió 403 al publicar |
| `STATUS_REJECTED` | `rejected` | Proppit rechazó el publisher |
| `STATUS_ERROR` | `error` | Estado inesperado en la respuesta |

Proppit todavía no estandariza el campo de activación, así que
`PublisherResponse::fromArray()` inspecciona `canPublish`, `can_publish`, `state`,
`activationStatus`, `active` y `status` — y **solo** marca `active` si Proppit lo
confirma explícitamente. Ante la duda, asume `pending_activation`.

```php
$response->canPublish();          // true solo si activationStatus === 'active'
$response->isPendingActivation(); // true si 'pending_activation' o 'synced'
$response->raw;                   // respuesta cruda completa para inspección
```

### Plantilla para solicitar activación a Proppit

```
Hola equipo Proppit,

Ya sincronizamos el publisher de la inmobiliaria desde la integración de Homlity.
Solicitamos habilitar/conectar este publisher para publicar propiedades vía API.

  - Publisher external_id         : {external_id}
  - Publisher ID devuelto por API : {publisher_id}
  - Nombre de la inmobiliaria     : {agency_name}
  - Request ID del sync           : {request_id}
```

Flujo completo en [docs/publisher-integration.md](docs/publisher-integration.md).

---

## 8. Propiedades (anuncios)

### Publicar

```php
$response = $client->properties()->publish([
    'referenceId' => 'CO-MY-PROPERTY-001',
    'publisher'   => ['externalId' => $inmobiliaria->proppit_external_id],
    'contact'     => [
        'name'  => 'Ana Restrepo',
        'email' => 'ana@inmobiliaria.com',
        'phone' => '+5712345678',
    ],
    'property'    => [
        'type'     => 'apartment',
        'location' => [
            'countryCode' => 'CO',
            'visibility'  => 'accurate',
            'address'     => 'Calle 63 #11-20',
            'coordinates' => ['lat' => 4.711, 'long' => -74.0721],
        ],
    ],
    'operations'  => [
        ['type' => 'sell', 'price' => ['value' => 450_000_000, 'currency' => 'COP']],
    ],
    'title'       => ['locale' => 'es-CO', 'text' => 'Apartamento en Chapinero'],
    'description' => ['locale' => 'es-CO', 'text' => 'Hermoso apartamento con vista panorámica.'],
    'floorArea'   => ['value' => 80.0, 'unit' => 'sqm'],
    'bedrooms'    => 3,
    'bathrooms'   => 2,
    'stratum'     => 4,
    'multimedia'  => [
        'pictures' => [
            ['url' => 'https://cdn.homlity.com/inmuebles/1001/1.jpg'],
            ['url' => 'https://cdn.homlity.com/inmuebles/1001/2.jpg'],
        ],
    ],
]);

echo $response->referenceId;  // CO-MY-PROPERTY-001
echo $response->status;       // published
$response->data;              // array crudo de la respuesta
```

También acepta el DTO `PropertyPayload`:

```php
use Proppit\DTO\PropertyPayload;

$client->properties()->publish(PropertyPayload::fromArray($payload));
```

> `referenceId` es **tu** identificador. Guárdalo en tu base de datos: es la única
> forma de actualizar o borrar el anuncio después. Convención sugerida: `CO-{inmueble_id}`.

### Actualizar

```php
$response = $client->properties()->update('CO-MY-PROPERTY-001', $payload);
```

> ⚠️ `PUT` es un **reemplazo completo**: reenvía todos los campos requeridos, no
> solo los que cambiaron. El SDK vuelve a validar el payload entero.

### Consultar

```php
// Usando el publisher_external_id de la config (instalación mono-inmobiliaria)
$response = $client->properties()->find('CO-MY-PROPERTY-001');

// Multi-inmobiliaria: pasa el externalId de la agencia
$response = $client->properties()->findByExternalId(
    $inmobiliaria->proppit_external_id,
    'CO-MY-PROPERTY-001',
);

if ($response === null) {
    // Proppit devolvió cuerpo vacío — el anuncio no está disponible
}
```

`find()` lanza `ValidationException` si no hay `publisher_external_id` configurado.

### Eliminar

```php
$response = $client->properties()->delete('CO-MY-PROPERTY-001');
echo $response->status;   // deleted
```

Igual que `find()`, requiere `publisher_external_id` en la config.

### Endpoints que usa cada método

| Método del SDK | HTTP | Path |
|---|---|---|
| `properties()->publish()` | `POST` | `/proppit/{country}/ads` |
| `properties()->update($ref)` | `PUT` | `/proppit/{country}/ads/{referenceId}` |
| `properties()->find($ref)` / `findByExternalId()` | `GET` | `/proppit/{country}/ads/{referenceId}?externalId=` |
| `properties()->delete($ref)` | `DELETE` | `/proppit/{country}/ads/{referenceId}?externalId=` |
| `publishers()->create()` | `POST` | `/proppit/{country}/publishers` |
| `publishers()->update($id)` | `PUT` | `/proppit/{country}/publishers/{id}` |
| `publishers()->find($id)` / `status($id)` | `GET` | `/proppit/{country}/publishers/{id}` |

El `referenceId` se codifica con `rawurlencode()`, así que es seguro usar `/`, espacios o acentos.

---

## 9. Referencia del payload de anuncio

### Campos requeridos

| Campo | Tipo | Notas |
|---|---|---|
| `referenceId` | string | Único en tu sistema, no vacío |
| `publisher.externalId` | string | `externalId` de la inmobiliaria |
| `property.type` | string | `apartment`, `house`, `land`, `commercial`, `office`, `villa`… |
| `property.location.coordinates.lat` | number | Latitud |
| `property.location.coordinates.long` | number | Longitud |
| `operations[]` | array | Mínimo 1 elemento |
| `operations[].type` | enum | `sell` \| `rent` |
| `operations[].price.value` | number | |
| `operations[].price.currency` | enum | Ver tabla de monedas |
| `title.locale` / `description.locale` | enum | Ver tabla de locales |
| `title.text` / `description.text` | string | No vacío |

### Campos opcionales más usados

| Campo | Tipo | Notas |
|---|---|---|
| `contact.name` / `.email` / `.phone` / `.whatsapp` | string | Datos de contacto del anuncio |
| `property.location.countryCode` | enum | `CO`, `MX`, `AR`… |
| `property.location.visibility` | enum | `accurate` \| `approximate` |
| `property.location.address` / `.postcode` | string | |
| `property.location.nearbyLocations[]` | string[] | Valores desde `GET /property-types` |
| `property.location.geo[]` | array | `{name, level}` |
| `property.communityFees` | object | `{value, currency}` — administración |
| `property.floor` | string | Piso |
| `property.project.name` | string | Debe coincidir exactamente con el proyecto en Proppit |
| `property.bankProperty` | boolean | Solo MX |
| `multimedia.pictures[].url` | URL | Content-type `image/*` |
| `multimedia.floorPlans[].url` / `videos[].url` / `virtualTours[].url` | URL | |
| `totalArea` / `floorArea` / `usableArea` | `{value, unit:'sqm'}` | Ver reglas de área |
| `bedrooms` / `bathrooms` / `halfBathrooms` / `parkingSpaces` | number ≥ 0 | |
| `stratum` | number 1–7 | **Solo CO** — validado por el SDK |
| `constructionYear` | number 1500–2100 | |
| `condition` | enum | Ver tabla |
| `furnished` | enum | `fully` \| `partly` \| `unfurnished` |
| `isBoosted` | boolean | Default `false` |
| `amenities[]` / `rules[]` | string[] | Valores desde `GET /property-types` |
| `acceptedPaymentMethods` | enum | Solo MX |

### Reglas de área por tipo de inmueble

| Tipo | `floorArea` | `totalArea` |
|---|---|---|
| `apartment`, `office` | Área total → **floorArea** | — |
| `house`, `villa`, `commercial`, `industrial unit` | Área construida → **floorArea** | Área de terreno → **totalArea** |
| `land` | — | Área de terreno → **totalArea** (obligatorio) |

Unidad soportada: `sqm`.

### Enums validados por el SDK

| Enum | Valores |
|---|---|
| **Moneda** | `AED` `ARS` `CLF` `CLP` `COP` `EUR` `IDR` `MXN` `PAB` `PEN` `PHP` `THB` `USD` `VND` |
| **Locale** | `es-AR` `es-CL` `es-CO` `es-EC` `es-ES` `es-MX` `es-PA` `es-PE` `en-PH` `id-ID` `th-TH` `vi-VN` |
| **Operación** | `sell` `rent` |
| **Condición** | `excellent` `good` `normal` `to renew` `second hand` `ruin` `semi-renovated` `semi-new` `in construction` `new` `renovated` |
| **Amoblado** | `fully` `partly` `unfurnished` |
| **Visibilidad** | `accurate` `approximate` |
| **País (path)** | `AE` `AR` `CL` `CO` `EC` `ES` `ID` `MX` `PA` `PE` `PH` `TH` `VN` |

### Qué valida el normalizador antes de salir a la red

- Presencia de los 6 campos requeridos y `referenceId` no vacío.
- `publisher.externalId` presente y no vacío.
- `property.type`, `property.location` y coordenadas numéricas.
- `operations` array no vacío; `type`, `price.value` numérico y `currency` en el enum.
- `title` y `description` con `locale` válido y `text` no vacío.
- `multimedia.pictures[].url` con URL válida.
- Áreas con `value` numérico y `unit` = `sqm`.
- `stratum` entre 1 y 7.
- `condition`, `furnished`, `visibility` contra sus enums.
- Limpieza recursiva de `null` y strings vacíos antes de serializar.

Cualquier fallo lanza `ValidationException` **sin** consumir una llamada HTTP.

---

## 10. Manejo de errores

```php
use Proppit\Exceptions\ApiException;
use Proppit\Exceptions\AuthException;
use Proppit\Exceptions\ForbiddenException;
use Proppit\Exceptions\PublisherPermissionException;
use Proppit\Exceptions\RateLimitException;
use Proppit\Exceptions\ValidationException;

try {
    $response = $client->properties()->publish($payload);

} catch (PublisherPermissionException $e) {
    // 403 "Publisher could not publish" — el publisher NO está activado por Proppit.
    // NO son credenciales incorrectas. Ningún cambio de código lo resuelve.
    $e->requestId();        // Request ID de Proppit (para soporte)
    $e->originalError();    // "Publisher could not publish"
    $e->rawResponse();      // JSON saneado del cuerpo del 403
    $e->operationalHint();  // Guía legible para operadores

} catch (ForbiddenException $e) {
    // 403 genérico, no relacionado con activación de publisher.

} catch (ValidationException $e) {
    // El payload no pasó la validación local — no hubo llamada HTTP.

} catch (AuthException $e) {
    // 401, credenciales rechazadas o respuesta inválida del endpoint /token.

} catch (RateLimitException $e) {
    // 429 tras agotar los reintentos.
    $e->retryAfter;         // segundos, si Proppit envió Retry-After

} catch (ApiException $e) {
    // Cualquier otro error HTTP (400, 404, 5xx) o error de transporte.
    $e->statusCode; $e->method; $e->endpoint; $e->proppitErrorCode; $e->context();
}
```

### Jerarquía

```
RuntimeException
└── ProppitException                        ← captura todo el SDK
    ├── ValidationException                — validación local del payload
    ├── AuthException                      — credenciales faltantes / 401
    └── ApiException                       — cualquier error HTTP (statusCode, method, endpoint)
        ├── RateLimitException             — 429 (+ retryAfter)
        ├── ForbiddenException             — 403 genérico
        └── PublisherPermissionException   — 403 "Publisher could not publish"
            └── PublisherNotReadyException — subclase con publisherExternalId (alias compatible)
```

> **Orden de los `catch`:** de más específico a más general. Si pones
> `ApiException` primero, nunca alcanzarás `PublisherPermissionException`.

### Mapa de códigos HTTP

| HTTP | Excepción | ¿Reintenta? | Qué hacer |
|---|---|---|---|
| 400 | `ApiException` | No | Revisar el payload; leer `proppitErrorCode` |
| 401 | `AuthException` | No | Verificar `PROPPIT_CLIENT_ID` / `PROPPIT_CLIENT_SECRET` |
| 403 "Publisher could not publish" | `PublisherNotReadyException` | No | Solicitar activación del publisher a Proppit |
| 403 (otro) | `ForbiddenException` | No | Revisar permisos de la cuenta |
| 404 | `ApiException` | No | El recurso no existe **o** no está disponible para tus credenciales |
| 429 | `RateLimitException` | Sí | Respetar `retryAfter`, encolar y reintentar |
| 500/502/503/504 | `ApiException` | Sí | Reintento automático; si persiste, reportar con el `requestId` |
| Error de red | `ApiException` (`statusCode = 0`) | No | Revisar conectividad/DNS/timeout |

`$e->context()` trae `status`, `body` y `headers` **ya saneados** — es seguro loguearlo.

### Captura específica del publisher no activado

```php
use Proppit\Exceptions\PublisherNotReadyException;

try {
    $client->properties()->publish($payload);
} catch (PublisherNotReadyException $e) {
    $inmobiliaria->update([
        'proppit_publisher_status' => 'cannot_publish',
        'proppit_can_publish'      => false,
        'proppit_last_error'       => $e->getMessage(),
        'proppit_last_request_id'  => $e->requestId(),
    ]);

    Log::warning('proppit_publisher_no_activado', [
        'external_id' => $e->publisherExternalId,
        'request_id'  => $e->proppitRequestId,
    ]);
}
```

`PropertyApi` reinyecta el `publisher.externalId` del payload en la excepción, así
que `$e->publisherExternalId` te dice **qué** inmobiliaria está bloqueada.

---

## 11. Reintentos, timeouts y rate limits

| Condición | Comportamiento |
|---|---|
| `429 Too Many Requests` | Usa `Retry-After` si viene; si no, backoff exponencial con jitter |
| `5xx` (500, 502, 503, 504) | Backoff exponencial con jitter |
| `4xx` (salvo 429) | **No reintenta** — son errores del cliente |
| Error de transporte de Guzzle | **No reintenta** — `ApiException` con `statusCode = 0` |

Fórmula del backoff: `(PROPPIT_RETRY_DELAY_MS × 2^(intento-1) + jitter(0–100 ms))`.

Con los valores por defecto (`retry_delay_ms=500`, `retry_attempts=3`) el peor caso
es 4 intentos y ≈ 3,5 s de espera acumulada, más 4 × `timeout` en el peor escenario
de red. Dimensiona el timeout de tu job en consecuencia.

**Recomendaciones de producción:**

- Ejecuta publicaciones en **colas**, nunca en el ciclo request/response.
- Un job por inmueble, con `$tries` y `backoff()` propios encima de los del SDK.
- Ante `RateLimitException`, usa `release($e->retryAfter ?? 60)`.
- No paralelices agresivamente: Proppit no documenta sus límites (`TODO_PROPPIT_RATE_LIMIT`).

```php
public function handle(ProppitClient $proppit): void
{
    try {
        $proppit->properties()->publish($this->payload());
    } catch (RateLimitException $e) {
        $this->release($e->retryAfter ?? 60);
    } catch (PublisherNotReadyException $e) {
        $this->fail($e);   // no reintentar: requiere acción manual de Proppit
    }
}
```

---

## 12. Logging estructurado

```dotenv
PROPPIT_ENABLE_STRUCTURED_LOGS=true
```

En Laravel el logger PSR-3 de la app se inyecta solo. Standalone, pásalo al construir
`StructuredLogger(enabled: true, logger: $psr3)`. Sin logger PSR-3 no se emite nada.

Cada request emite un evento `proppit_http` con:

| Campo | Descripción |
|---|---|
| `method` / `uri` | Verbo y path relativo |
| `status_code` | HTTP de la respuesta |
| `duration_ms` | Duración medida en el cliente |
| `attempt` | Número de intento (1 = primero) |
| `request_id` | `x-request-id` de Proppit o `requestId` del cuerpo — **guárdalo, soporte lo pide** |
| `error_code` | Campo `error` del cuerpo, si viene |
| `headers` | Headers enviados, saneados |

### Redacción automática

**Redactados por completo (`***`):** `authorization`, `client_secret`, `api_secret`,
`api_password`, `password`, `token`, `access_token`, `refresh_token`, `bearer`,
`signature`, `secret`, `email`, `phone`, `whatsapp`.

**Redactados parcialmente (4 primeros caracteres + `***`):** `client_id`, `api_key`, `api_user`.

La redacción es recursiva (`array_walk_recursive`) y también se aplica al
`context()` de las excepciones y a `PublisherPermissionException::rawResponse()`.

```php
// Sanea cualquier array antes de loguearlo tú mismo:
\Proppit\Support\StructuredLogger::sanitize($arrayConDatosSensibles);
```

> Ojo: `email` y `phone` se redactan también en payloads salientes de log. Eso es
> deliberado (PII) — no te alarmes si no ves el contacto en los logs.

---

## 13. Extender el SDK vía DI

| Interfaz | Binding por defecto |
|---|---|
| `Proppit\Contracts\ProppitAuthenticatorInterface` | `ClientCredentialsAuthenticator` |
| `Proppit\Contracts\ProppitHttpClientInterface` | `GuzzleProppitHttpClient` |
| `Proppit\Contracts\PropertyApiInterface` | `PropertyApi` |
| `Proppit\Contracts\PublisherApiInterface` | `PublisherApi` |
| `Proppit\Contracts\PropertyPayloadMapperInterface` | `PropertyPayloadNormalizer` |

```php
// AppServiceProvider::register()
$this->app->bind(PropertyPayloadMapperInterface::class, MiNormalizadorConReglasDeNegocio::class);
$this->app->bind(ProppitAuthenticatorInterface::class, TokenCacheadoEnRedisAuthenticator::class);
```

**Casos típicos de extensión:**

- **Cachear el token en Redis** — implementa `ProppitAuthenticatorInterface` si corres
  muchos procesos cortos.
- **Mapper propio** — implementa `PropertyPayloadMapperInterface` para convertir tu
  modelo `Inmueble` directamente al payload de Proppit:

```php
final class InmuebleMapper implements PropertyPayloadMapperInterface
{
    public function __construct(private readonly PropertyPayloadNormalizer $base) {}

    public function normalize(PropertyPayload|array $payload): array
    {
        $payload = $this->aplicarReglasHomlity($payload);
        return $this->base->normalize($payload);   // conserva la validación oficial
    }
}
```

- **Cliente HTTP propio** — implementa `ProppitHttpClientInterface` para instrumentar
  con OpenTelemetry, un circuit breaker o una caché de respuestas.
- **Headers extra** — más simple: `'custom_headers' => ['X-Homlity-Trace' => $traceId]`.

---

## 14. Testing

### Tests del SDK

```bash
composer install
vendor/bin/phpunit
```

Suites: `tests/Unit` (normalizadores, config, auth, cliente HTTP, DTOs, logger) y
`tests/Integration` (bindings del contenedor de Laravel). No tocan la red.

### Mockear el SDK en tu aplicación

Sustituye el cliente HTTP y no saldrá ni un byte a la red:

```php
use Proppit\Contracts\ProppitHttpClientInterface;
use Proppit\DTO\HttpResponse;

$mock = $this->createMock(ProppitHttpClientInterface::class);
$mock->method('request')->willReturn(
    new HttpResponse(201, [], '{}', ['referenceId' => 'CO-1', 'status' => 'published'])
);

$this->app->bind(ProppitHttpClientInterface::class, fn () => $mock);
```

Para probar la ruta de error del publisher no activado:

```php
$mock->method('request')->willThrowException(
    new \Proppit\Exceptions\PublisherNotReadyException('homlity_agency_1', 'req-123', [])
);
```

O mockea la API completa cuando solo te interesa tu lógica de negocio:

```php
$api = $this->createMock(\Proppit\Contracts\PropertyApiInterface::class);
$api->expects($this->once())->method('publish');
$this->app->bind(\Proppit\Contracts\PropertyApiInterface::class, fn () => $api);
```

También puedes usar `GuzzleHttp\Handler\MockHandler` si quieres ejercitar el SDK
completo (retries, mapeo de errores) contra respuestas fabricadas.

---

## 15. Receta completa: integrar una inmobiliaria de cero

### Paso 0 — Migración sugerida

```php
Schema::table('inmobiliarias', function (Blueprint $table) {
    $table->string('proppit_external_id')->nullable()->unique();
    $table->string('proppit_publisher_id')->nullable();
    $table->string('proppit_publisher_status')->default('pending_sync');
    $table->boolean('proppit_can_publish')->default(false);
    $table->string('proppit_last_request_id')->nullable();
    $table->text('proppit_last_error')->nullable();
    $table->timestamp('proppit_last_synced_at')->nullable();
    $table->timestamp('proppit_activated_at')->nullable();
});

Schema::table('inmuebles', function (Blueprint $table) {
    $table->string('proppit_reference_id')->nullable()->unique();
    $table->timestamp('proppit_published_at')->nullable();
});
```

### Checklist de sincronización inicial

- [ ] **1.** Configurar `PROPPIT_CLIENT_ID` / `PROPPIT_CLIENT_SECRET` / `PROPPIT_COUNTRY`.
- [ ] **2.** `createOrUpdate()` del publisher; guardar `proppit_external_id` y `proppit_publisher_id`.
- [ ] **3.** Solicitar la activación a Proppit con la plantilla de [§7](#7-publishers-inmobiliarias).
- [ ] **4.** Confirmar con `status($externalId)` que `canPublish() === true`; marcar `proppit_can_publish = true`.
- [ ] **5.** **Depurar el inventario previo en la cuenta de Proppit** — ver aviso abajo.
- [ ] **6.** Publicar por API solo si `proppit_can_publish === true`.
- [ ] **7.** Guardar el `referenceId` de cada inmueble.
- [ ] **8.** Hacer todos los cambios posteriores vía `update()`, **no** desde el panel web de Proppit.

> ⚠️ **Antes del primer sync, Proppit recomienda eliminar las propiedades existentes**
> en la cuenta. Si no lo haces, arriesgas duplicados, `referenceId` que no coinciden,
> `PUT` que sobreescriben el inmueble equivocado y estados incoherentes entre el panel
> web y la API. Detalle en [docs/proppit-initial-sync.md](docs/proppit-initial-sync.md).

### Guardas en código

```php
if (! $inmobiliaria->proppit_can_publish) {
    // No llames a la API: Proppit responderá 403.
    return;
}

$referenceId = $inmueble->proppit_reference_id ?? 'CO-' . $inmueble->id;

$response = $inmueble->proppit_reference_id
    ? $client->properties()->update($referenceId, $payload)
    : $client->properties()->publish($payload);

$inmueble->update([
    'proppit_reference_id' => $response->referenceId,
    'proppit_published_at' => now(),
]);
```

---

## 16. Referencia de API del SDK

### `Proppit\ProppitClient`

| Método | Devuelve |
|---|---|
| `properties()` | `PropertyApiInterface` |
| `publishers()` | `PublisherApiInterface` |

### `Proppit\Contracts\PropertyApiInterface`

| Método | Devuelve | Notas |
|---|---|---|
| `publish(PropertyPayload\|array $payload)` | `PropertyResponse` | Valida y crea el anuncio |
| `update(string $referenceId, PropertyPayload\|array $payload)` | `PropertyResponse` | Reemplazo completo |
| `find(string $referenceId)` | `PropertyResponse` | Requiere `publisher_external_id` en config |
| `findByExternalId(string $externalId, string $referenceId)` | `?PropertyResponse` | `null` si la respuesta viene vacía |
| `delete(string $referenceId)` | `PropertyResponse` | `status = deleted` |

### `Proppit\Contracts\PublisherApiInterface`

| Método | Devuelve | Notas |
|---|---|---|
| `create(PublisherPayload $p)` | `PublisherResponse` | |
| `update(string $id, PublisherPayload $p)` | `PublisherResponse` | |
| `find(string $id)` | `?PublisherResponse` | `null` en 404 |
| `createOrUpdate(PublisherPayload $p)` | `PublisherResponse` | Idempotente |
| `status(string $id)` | `PublisherResponse` | Lanza `ApiException(404)` si no existe |

### DTOs

| Clase | Propiedades |
|---|---|
| `PropertyPayload` | `fromArray()`, `toArray()` |
| `PropertyResponse` | `referenceId`, `status`, `data` |
| `PublisherPayload` | `externalId`, `name`, `email`, `phone` |
| `PublisherResponse` | `publisherId`, `name`, `email`, `phone`, `raw`, `activationStatus`, `externalId`, `requestId`, `message` + `canPublish()`, `isPendingActivation()` |
| `HttpResponse` | `statusCode`, `headers`, `rawBody`, `json` + `successful()`, `failed()`, `clientError()`, `serverError()` |

---

## 17. Troubleshooting

| Síntoma | Causa probable | Solución |
|---|---|---|
| `AuthException: Missing PROPPIT_CLIENT_ID` | Config vacía o `.env` no cargado | Verificar `.env`; en Laravel limpiar caché: `php artisan config:clear` |
| `AuthException: Proppit rejected the client credentials` | Credenciales incorrectas o del entorno equivocado | Confirmar con Proppit; recordar que `client_id` va como `user` |
| `403 Publisher could not publish` | Publisher no activado por Proppit | Solicitar activación ([§7](#7-publishers-inmobiliarias)). **No** es un problema de credenciales ni de código |
| `ValidationException: title.locale must be one of…` | Locale fuera del enum (p. ej. `es_CO` o `es`) | Usar `es-CO` con guion |
| `ValidationException: floorArea.unit must be one of: sqm` | Unidad en `m2` | Usar `sqm` |
| `ValidationException: stratum must be between 1 and 7` | `stratum` fuera de rango o usado fuera de CO | Enviar solo en Colombia, valores 1–7 |
| `ValidationException: PROPPIT_PUBLISHER_EXTERNAL_ID is required` | `find()`/`delete()` sin `publisher_external_id` | Usar `findByExternalId()` o definir la clave en config |
| `ApiException` con `statusCode = 0` | Error de transporte (DNS, TLS, timeout) | Revisar salida a internet del servidor; subir `PROPPIT_TIMEOUT` |
| `ApiException` 404 al consultar | El recurso no existe **o** no pertenece a tus credenciales | Verificar `referenceId` y `externalId`; Proppit no distingue ambos casos |
| `composer require homlity/sdk-proppit` no encuentra versiones | Aún no hay tag estable en Packagist | Usar `:dev-main` o el repositorio VCS ([§3](#3-instalación)) |
| No aparece nada en los logs | `PROPPIT_ENABLE_STRUCTURED_LOGS=false` o sin logger PSR-3 | Activar la variable; standalone, pasar el logger al `StructuredLogger` |
| El anuncio no se ve en Proppit | Imágenes no accesibles o publisher recién activado | Las URLs deben ser públicas con content-type `image/*`; dar tiempo al ingester |

Cuando escribas a soporte de Proppit (`ingester@lifullconnect.com`), incluye siempre
el **`requestId`** — lo tienes en los logs, en `$e->requestId()` y en `$response->requestId`.

---

## 18. Soporte y contribución

| Recurso | Enlace |
|---|---|
| Sitio de Homlity | https://homlity.com/ |
| Portal de desarrolladores | https://homlity.com/desarrolladores/ |
| Repositorio | https://github.com/homlity/sdk-propit |
| Packagist | https://packagist.org/packages/homlity/sdk-proppit |
| Swagger de Proppit | https://real-time.proppit.com/api/v2/docs |
| OpenAPI de Proppit | https://real-time.proppit.com/api/v2/docs/openapi.yaml |
| Soporte del ingester de Proppit | ingester@lifullconnect.com |

### Documentación adicional en este repositorio

| Documento | Contenido |
|---|---|
| [docs/desarrolladores.html](docs/desarrolladores.html) | **Guía web completa para desarrolladores** — versión navegable de este README, lista para publicar en `homlity.com/desarrolladores/` |
| [docs/publisher-integration.md](docs/publisher-integration.md) | Flujo completo de publishers, estados y plantilla de activación |
| [docs/proppit-initial-sync.md](docs/proppit-initial-sync.md) | Checklist de sincronización inicial y depuración del inventario previo |
| [docs/proppit-api-analysis.md](docs/proppit-api-analysis.md) | Análisis técnico de la API, schemas y enums |
| [docs/proppit-openapi-analysis.md](docs/proppit-openapi-analysis.md) | Notas sobre el spec OpenAPI |
| [docs/migracion-fincaraiz-a-propit.md](docs/migracion-fincaraiz-a-propit.md) | Migración desde el SDK de Finca Raíz |
| [docs/mapeo-fincaraiz-propit.md](docs/mapeo-fincaraiz-propit.md) | Tabla de equivalencias Finca Raíz → Proppit |

### Contribuir

1. Crea una rama desde `main`.
2. Añade tests en `tests/Unit` o `tests/Integration`.
3. `vendor/bin/phpunit` en verde.
4. Abre un PR describiendo el cambio y el impacto en el payload o en la API pública.

Convenciones del código: `declare(strict_types=1)`, clases `final` salvo que se
diseñen para extenderse, constructor property promotion, propiedades `readonly` y
todo lo público detrás de una interfaz en `src/Contracts/`.

### Seguridad

- Nunca commitear credenciales; usar `.env` o un gestor de secretos.
- Nunca loguear `client_secret` ni tokens — `StructuredLogger` los redacta, pero no
  construyas logs propios con esos valores.
- Usar `$config->redacted()` para volcar el estado de la configuración.
- Reportar vulnerabilidades de forma privada a través de https://homlity.com/desarrolladores/.

---

<p align="center">
  <a href="https://homlity.com/"><img src="https://homlity.com/wp-content/uploads/2026/08/Diseno-sin-titulo-1-e1787507729419-1024x338.webp" alt="Homlity" width="200"></a><br>
  <sub>Hecho por <a href="https://homlity.com/">Homlity</a> · <a href="https://homlity.com/desarrolladores/">homlity.com/desarrolladores</a></sub>
</p>
