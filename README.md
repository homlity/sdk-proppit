# propit/sdk-propit

PHP/Laravel SDK for the [Proppit Real-Time API](https://real-time.proppit.com/api/v2/docs).

Supports PHP 8.1+ and Laravel 9/10/11.

---

## Credenciales: separación entre sistema e inmobiliaria

> **Importante:** `PROPIT_CLIENT_ID` y `PROPIT_CLIENT_SECRET` son credenciales del **sistema Homlity** (no de cada inmobiliaria). Proppit las entrega una sola vez para identificar a Homlity como cliente API. Cada inmobiliaria se representa mediante un **publisher** con un `externalId` generado por Homlity.

| Concepto | Variable | Scope |
|---|---|---|
| Credencial del sistema | `PROPIT_CLIENT_ID` / `PROPIT_CLIENT_SECRET` | Global — solo en `.env` |
| Identificador de inmobiliaria | `publisher.externalId` (`homlity_agency_{id}`) | Por inmobiliaria — en BD |
| ID devuelto por Proppit | `proppit_publisher_id` | Por inmobiliaria — en BD |

Ver [docs/publisher-integration.md](docs/publisher-integration.md) para el flujo completo.

---

## Installation

```bash
composer require propit/sdk-propit
```

El SDK se registra automáticamente vía Laravel package auto-discovery.

---

## Configuration

### Publicar el config

```bash
php artisan vendor:publish --tag=propit-config
```

### Variables de entorno

```dotenv
PROPIT_BASE_URL=https://real-time.proppit.com/api/v2
PROPIT_CLIENT_ID=tu-client-id-de-proppit
PROPIT_CLIENT_SECRET=tu-client-secret-de-proppit
PROPIT_COUNTRY=CO

# Opcionales
PROPIT_TIMEOUT=30
PROPIT_RETRY_ATTEMPTS=3
PROPIT_RETRY_DELAY_MS=500
PROPIT_USER_AGENT="Homlity-Proppit-SDK/1.0"
PROPIT_ENABLE_STRUCTURED_LOGS=true
```

> **Seguridad:** Nunca commitear credenciales reales. Usar `.env` o un gestor de secretos. `PROPIT_CLIENT_SECRET` nunca se loguea.

### Flujo de autenticación

1. El SDK llama `POST /token` con `client_id` (como `user`) y `client_secret` (como `password`).
2. Proppit devuelve un token con timestamp de expiración Unix.
3. El token se cachea en memoria y se renueva 30 s antes de expirar.
4. Cada request incluye `Authorization: Bearer <token>`.

---

## Usage

### Standalone (sin Laravel)

```php
use GuzzleHttp\Client;
use Propit\Api\PropertyApi;
use Propit\Api\PublisherApi;
use Propit\Auth\ClientCredentialsAuthenticator;
use Propit\Config\PropitConfig;
use Propit\Http\GuzzlePropitHttpClient;
use Propit\Normalizers\PropertyPayloadNormalizer;
use Propit\Normalizers\PublisherPayloadNormalizer;
use Propit\PropitClient;
use Propit\Support\StructuredLogger;

$config = PropitConfig::fromArray([
    'base_url'      => 'https://real-time.proppit.com/api/v2',
    'client_id'     => getenv('PROPIT_CLIENT_ID'),
    'client_secret' => getenv('PROPIT_CLIENT_SECRET'),
    'country'       => 'CO',
]);

$guzzle = new Client();
$auth   = new ClientCredentialsAuthenticator($config, $guzzle);
$http   = new GuzzlePropitHttpClient($guzzle, $config, $auth, new StructuredLogger(false));

$client = new PropitClient(
    properties: new PropertyApi($http, new PropertyPayloadNormalizer(), $config),
    publishers: new PublisherApi($http, new PublisherPayloadNormalizer(), $config),
);
```

### Con Laravel

```php
use Propit\PropitClient;

$client = app(PropitClient::class);
```

---

## Sincronizar publisher (inmobiliaria)

Antes de publicar inmuebles, registra la inmobiliaria como publisher en Proppit:

```php
use Propit\DTO\PublisherPayload;

$externalId = 'homlity_agency_' . $inmobiliaria->id;

$response = $client->publishers()->createOrUpdate(new PublisherPayload(
    externalId: $externalId,
    name:       $inmobiliaria->nombre,
    email:      $inmobiliaria->email_contacto,
    phone:      $inmobiliaria->telefono,
));

// Persiste este ID en tu base de datos
$inmobiliaria->update([
    'proppit_external_id'  => $externalId,
    'proppit_publisher_id' => $response->publisherId(),
    'proppit_sync_status'  => 'synced',
]);
```

Ver [examples/sync_publisher.php](examples/sync_publisher.php).

---

## Publishing a property

```php
$response = $client->properties()->publish([
    'referenceId' => 'CO-MY-PROPERTY-001',
    'publisher'   => ['externalId' => $inmobiliaria->proppit_external_id],
    'property'    => [
        'type'     => 'apartment',
        'location' => [
            'countryCode' => 'CO',
            'visibility'  => 'accurate',
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
]);

echo $response->referenceId; // CO-MY-PROPERTY-001
echo $response->status;      // published
```

## Updating a property

```php
$response = $client->properties()->update('CO-MY-PROPERTY-001', $payload);
```

> El endpoint PUT hace un reemplazo completo — reenviar todos los campos requeridos.

## Fetching a property

```php
// Usando publisher_external_id del config:
$response = $client->properties()->find('CO-MY-PROPERTY-001');

// O pasando el externalId explícitamente:
$response = $client->properties()->findByExternalId('homlity_agency_42', 'CO-MY-PROPERTY-001');
```

## Deleting a property

```php
$response = $client->properties()->delete('CO-MY-PROPERTY-001');
echo $response->status; // deleted
```

---

## Required payload fields

| Field | Type | Notes |
|---|---|---|
| `referenceId` | string | Identificador único del inmueble en tu sistema |
| `publisher.externalId` | string | `homlity_agency_{id}` de la inmobiliaria |
| `property.type` | string | `apartment`, `house`, `land`, `commercial`, `office`… ver `/property-types` |
| `property.location.coordinates.lat` | float | Latitud |
| `property.location.coordinates.long` | float | Longitud |
| `operations[].type` | `sell` \| `rent` | Tipo de operación |
| `operations[].price.value` | float | Precio |
| `operations[].price.currency` | enum | `COP`, `USD`, `EUR`, `MXN`, `ARS`… |
| `title.locale` | enum | `es-CO`, `es-MX`, `es-AR`… |
| `title.text` | string | Título del anuncio |
| `description.locale` | enum | Mismo enum de locale |
| `description.text` | string | Descripción del anuncio |

---

## Error handling

```php
use Propit\Exceptions\ApiException;
use Propit\Exceptions\AuthException;
use Propit\Exceptions\RateLimitException;
use Propit\Exceptions\ValidationException;

try {
    $response = $client->properties()->publish($payload);
} catch (ValidationException $e) {
    // Validación local falló antes de la llamada HTTP.
    echo $e->getMessage();
} catch (AuthException $e) {
    // 401/403 de la API, o credenciales faltantes.
    echo $e->getMessage();
} catch (RateLimitException $e) {
    // 429 — reintentar después de $e->retryAfter segundos.
    echo "Retry after: {$e->retryAfter}s";
} catch (ApiException $e) {
    // Otros errores HTTP (400, 404, 500…).
    echo "HTTP {$e->statusCode} on {$e->method} {$e->endpoint}: {$e->getMessage()}";
}
```

### Jerarquía de excepciones

```
PropitException
├── ValidationException   — validación local del payload
├── AuthException         — credenciales faltantes o 401/403
├── RateLimitException    — 429 (extends ApiException)
└── ApiException          — cualquier otro error HTTP
```

---

## Logging

Activar logs estructurados: `PROPIT_ENABLE_STRUCTURED_LOGS=true`.

El logger registra: `method`, `uri`, `status_code`, `duration_ms`, `attempt`, `request_id`.

**Campos siempre redactados:** `client_secret`, `Authorization`, `access_token`, `refresh_token`, `password`, `token`, `signature`. `client_id` se redacta parcialmente.

---

## Extending via DI

```php
use Propit\Contracts\PublisherApiInterface;
use Propit\Contracts\PropertyPayloadMapperInterface;

app()->bind(PublisherApiInterface::class, MyCustomPublisherApi::class);
app()->bind(PropertyPayloadMapperInterface::class, MyCustomNormalizer::class);
```

| Interface | Binding por defecto |
|---|---|
| `PropitAuthenticatorInterface` | `ClientCredentialsAuthenticator` |
| `PropitHttpClientInterface` | `GuzzlePropitHttpClient` |
| `PropertyApiInterface` | `PropertyApi` |
| `PublisherApiInterface` | `PublisherApi` |
| `PropertyPayloadMapperInterface` | `PropertyPayloadNormalizer` |

---

## Testing

```bash
cd /path/to/sdk-propit
composer install
vendor/bin/phpunit
```

Mockear el HTTP client en tus tests de aplicación:

```php
use Propit\Contracts\PropitHttpClientInterface;
use Propit\DTO\HttpResponse;

$mock = $this->createMock(PropitHttpClientInterface::class);
$mock->method('request')->willReturn(
    new HttpResponse(201, [], '{}', ['referenceId' => 'CO-1', 'status' => 'published'])
);

app()->bind(PropitHttpClientInterface::class, fn () => $mock);
```

---

## Security

- Nunca loguear `client_secret`, tokens o API keys — `StructuredLogger` los sanitiza automáticamente.
- Usar `$config->redacted()` para loguear estado de configuración.
- Guardar credenciales en `.env`, nunca en source control.

---

## Migrating from FincaRaíz SDK

Ver [`docs/migracion-fincaraiz-a-propit.md`](docs/migracion-fincaraiz-a-propit.md).
