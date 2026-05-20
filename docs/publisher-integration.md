# Publisher Integration Guide

## Conceptos clave

### Credenciales del sistema (client_id / client_secret)

`PROPIT_CLIENT_ID` y `PROPIT_CLIENT_SECRET` son credenciales técnicas que Proppit entrega a Homlity como aplicación cliente. Identifican a **Homlity como sistema integrador**, no a cada inmobiliaria individualmente.

| Concepto | Descripción |
|---|---|
| `PROPIT_CLIENT_ID` | Identificador de Homlity como cliente API |
| `PROPIT_CLIENT_SECRET` | Secreto del sistema — nunca se expone ni se guarda por inmobiliaria |
| Scope | Global para toda la instalación de Homlity |
| Almacenamiento | Solo en `.env` |

**No deben:**
- Guardarse en la tabla de inmobiliarias.
- Viajar dentro de payloads de propiedades.
- Loguearse (ni siquiera parcialmente para `client_secret`).
- Hardcodearse en el código.

### Publisher (inmobiliaria/agencia)

Cada inmobiliaria/agencia se registra en Proppit como un **publisher**. El publisher representa a la agencia dentro del ecosistema Proppit y es el contenedor de todos sus anuncios.

```
Homlity (client_id/client_secret)
└── Publisher A (homlity_agency_10)   ← Inmobiliaria Alfa
    └── Ads: CO-001, CO-002, CO-003
└── Publisher B (homlity_agency_25)   ← Inmobiliaria Beta
    └── Ads: CO-100, CO-101
```

### external_id del publisher

Homlity debe generar y mantener un `external_id` estable y único para cada inmobiliaria. Este valor se usa como identificador del publisher en Proppit.

**Formato recomendado:**

```php
// Con ID numérico:
$externalId = 'homlity_agency_' . $inmobiliaria->id;

// Con UUID:
$externalId = 'homlity_agency_' . $inmobiliaria->uuid;
```

**Reglas:**
- Debe ser único por inmobiliaria.
- Debe ser estable (no cambiar con el tiempo).
- No debe ser el email, nombre comercial ni NIT como valor principal.
- Solo caracteres: letras, dígitos, guiones, guiones bajos, puntos, `@`.
- Máximo 255 caracteres.

---

## Flujo completo de integración

### 1. Configurar credenciales (una sola vez)

```ini
# .env
PROPIT_CLIENT_ID=tu-client-id
PROPIT_CLIENT_SECRET=tu-client-secret
PROPIT_COUNTRY=CO
```

### 2. Registrar/sincronizar un publisher

```php
use Propit\DTO\PublisherPayload;

// Generar el externalId desde el ID interno de Homlity
$externalId = 'homlity_agency_' . $inmobiliaria->id;

$payload = new PublisherPayload(
    externalId: $externalId,
    name:       $inmobiliaria->nombre,
    email:      $inmobiliaria->email_contacto,
    phone:      $inmobiliaria->telefono,
);

$response = $client->publishers()->createOrUpdate($payload);

// Persistir el publisher_id devuelto por Proppit
$inmobiliaria->update([
    'proppit_external_id'    => $externalId,
    'proppit_publisher_id'   => $response->publisherId(),
    'proppit_sync_status'    => 'synced',
    'proppit_last_synced_at' => now(),
    'proppit_last_error'     => null,
]);
```

### 3. Publicar un inmueble vinculado al publisher

```php
$response = $client->properties()->publish([
    'referenceId' => 'CO-' . $inmueble->id,
    'publisher'   => ['externalId' => $inmobiliaria->proppit_external_id],
    // ... resto del payload
]);
```

---

## Campos sugeridos en la tabla `inmobiliarias`

El SDK es agnóstico a la base de datos de Homlity. La migración debe crearse en la aplicación consumidora:

```php
// database/migrations/xxxx_add_proppit_fields_to_inmobiliarias_table.php
Schema::table('inmobiliarias', function (Blueprint $table) {
    $table->string('proppit_external_id')->nullable()->unique()->comment('homlity_agency_{id}');
    $table->string('proppit_publisher_id')->nullable()->index()->comment('ID devuelto por Proppit');
    $table->string('proppit_sync_status')->nullable()->comment('synced | pending | error');
    $table->timestamp('proppit_last_synced_at')->nullable();
    $table->text('proppit_last_error')->nullable();
});
```

---

## Endpoints utilizados por el SDK

| Operación | Método | Endpoint |
|---|---|---|
| Obtener token | `POST` | `/token` |
| Crear publisher | `POST` | `/proppit/{country}/publishers` |
| Actualizar publisher | `PUT` | `/proppit/{country}/publishers/{id}` |
| Consultar publisher | `GET` | `/proppit/{country}/publishers/{id}` |
| Publicar inmueble | `POST` | `/proppit/{country}/ads` |
| Actualizar inmueble | `PUT` | `/proppit/{country}/ads/{referenceId}` |
| Consultar inmueble | `GET` | `/proppit/{country}/ads/{referenceId}?externalId={publisherExternalId}` |
| Eliminar inmueble | `DELETE` | `/proppit/{country}/ads/{referenceId}?externalId={publisherExternalId}` |

> **Nota:** El token endpoint usa los campos `user` y `password` en el body (per spec OpenAPI). `PROPIT_CLIENT_ID` se envía como `user` y `PROPIT_CLIENT_SECRET` como `password`. El nombre de los campos en la API es del spec oficial; los nombres en `.env` reflejan la semántica de negocio (credenciales del cliente Homlity).

---

## Activación manual del publisher

Proppit requiere que el equipo de soporte **active manualmente** cada publisher antes de que pueda publicar anuncios. El `createOrUpdate()` registra el publisher en su sistema, pero eso no implica que esté habilitado para publicar.

### Síntoma

```
HTTP 403 — "Publisher could not publish"
```

### Comportamiento del SDK

El SDK detecta este error y lanza `PublisherNotReadyException` con el `publisherExternalId` y el `proppitRequestId` de Proppit:

```php
use Propit\Exceptions\PublisherNotReadyException;

try {
    $client->properties()->publish($payload);
} catch (PublisherNotReadyException $e) {
    // El publisher existe pero Proppit no lo ha activado todavía.
    echo $e->publisherExternalId; // 'homlity_agency_1'
    echo $e->proppitRequestId;    // 'dyc4wyw2zie8baakicp1'
}
```

### Qué enviar a soporte de Proppit

- Publisher external ID: el valor de `$e->publisherExternalId`
- Request ID: el valor de `$e->proppitRequestId`
- Preguntar explícitamente si el publisher requiere activación manual y si puede publicar anuncios.

### Diagnóstico del estado del publisher

Mientras esperas la activación, puedes consultar el estado raw del publisher para ver si Proppit devuelve campos como `active` o `state`:

```php
try {
    $response = $client->publishers()->status('homlity_agency_1');
    // El raw array contiene la respuesta completa de Proppit:
    var_dump($response->raw);
} catch (\Propit\Exceptions\ApiException $e) {
    // 404 = el publisher no existe en absoluto — ejecutar createOrUpdate() primero
}
```

---

## Errores comunes

| Error | Causa | Solución |
|---|---|---|
| `PublisherNotReadyException` | Publisher creado pero no activado por Proppit | Contactar soporte de Proppit con `publisherExternalId` y `proppitRequestId` |
| `AuthException: Missing PROPIT_CLIENT_ID` | Falta la variable en `.env` | Configurar `PROPIT_CLIENT_ID` |
| `AuthException: Proppit rejected the client credentials` | Credenciales incorrectas | Verificar `PROPIT_CLIENT_ID` y `PROPIT_CLIENT_SECRET` |
| `ValidationException: externalId is required` | `PublisherPayload::externalId` vacío | Generar el externalId con `'homlity_agency_' . $id` |
| `ValidationException: publisher.externalId is required` | Falta el publisher en el ad payload | Incluir `publisher.externalId` en el payload del inmueble |
| `ApiException: HTTP 404` en publisher | Publisher aún no registrado | Ejecutar `createOrUpdate()` primero |

---

## Seguridad

- `client_secret` se redacta completamente en logs.
- `client_id` aparece parcialmente en logs de debug (primeros 4 chars + `***`).
- El header `Authorization: Bearer ...` se redacta en logs.
- `access_token` y `refresh_token` se redactan en logs.
- Nunca loguear el body de la request al endpoint `/token`.
