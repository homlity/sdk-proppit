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
// Con UUID estable de inmobiliaria (preferido):
$externalId = $inmobiliaria->uuid;

// Con prefijo para mayor claridad:
$externalId = 'homlity_agency_' . $inmobiliaria->uuid;

// Con ID numérico (solo si no existe UUID):
$externalId = 'homlity_agency_' . $inmobiliaria->id;
```

**Reglas:**
- Debe ser único por inmobiliaria.
- Debe ser estable — **no cambiar** sin coordinar con Proppit (el publisher ya activado quedaría desconectado).
- No debe ser el email, nombre comercial ni NIT como valor principal.
- Solo caracteres: letras, dígitos, guiones, guiones bajos, puntos, `@`.
- Máximo 255 caracteres.
- Si ya se envió y activó un external_id en Proppit, **no modificarlo** sin soporte de Proppit.

---

## Flujo real CRM/lonja: publisher enviado ≠ publisher activo

> **Regla fundamental:** Crear o sincronizar un publisher en Proppit **no significa** que el publisher esté habilitado para publicar anuncios.
>
> Proppit debe activar/conectar manualmente cada publisher después de recibirlo.
> Hasta que Proppit lo habilite, cualquier intento de publicar un anuncio recibirá:
>
> ```json
> { "status": 403, "error": "Publisher could not publish" }
> ```

### Estados del publisher (internos del SDK)

| Estado | Valor | Significado |
|---|---|---|
| `pending_sync` | — | Publisher todavía no enviado a Proppit |
| `synced` | — | Publisher enviado, Proppit lo recibió pero no confirmó activación |
| `pending_activation` | **default** | Publisher recibido; Proppit no ha confirmado permiso para publicar |
| `active` | — | Proppit confirmó explícitamente que el publisher puede publicar |
| `cannot_publish` | — | Proppit respondió 403 al intentar publicar |
| `rejected` | — | Proppit rechazó el publisher |
| `error` | — | Estado inesperado en la respuesta |

El SDK asigna `pending_activation` por defecto después de `create` o `update`, salvo que Proppit confirme explícitamente lo contrario en la respuesta.

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

$externalId = 'homlity_agency_' . $inmobiliaria->uuid;

$payload = new PublisherPayload(
    externalId: $externalId,
    name:       $inmobiliaria->nombre,
    email:      $inmobiliaria->email_contacto,
    phone:      $inmobiliaria->telefono,
);

$response = $client->publishers()->createOrUpdate($payload);

// ⚠ isPendingActivation() = true después del sync por defecto
if ($response->isPendingActivation()) {
    $inmobiliaria->update([
        'proppit_external_id'      => $externalId,
        'proppit_publisher_id'     => $response->publisherId(),
        'proppit_publisher_status' => $response->activationStatus,
        'proppit_can_publish'      => false,   // NO puede publicar todavía
        'proppit_last_synced_at'   => now(),
        'proppit_last_error'       => null,
    ]);

    // → Solicitar activación a Proppit (ver plantilla más abajo)
}
```

### 3. Solicitar activación a Proppit (paso manual)

Después del sync, el equipo de Homlity debe contactar a Proppit usando la plantilla de la sección siguiente.

### 4. Confirmar activación

Si Proppit notifica que el publisher fue activado, o si `status()` confirma `canPublish() === true`:

```php
$response = $client->publishers()->status($externalId);

if ($response->canPublish()) {
    $inmobiliaria->update([
        'proppit_publisher_status' => $response->activationStatus,
        'proppit_can_publish'      => true,
        'proppit_activated_at'     => now(),
    ]);
}
```

### 5. Publicar anuncios (solo después de activación)

```php
// Verificar que el publisher está habilitado antes de publicar
if (! $inmobiliaria->proppit_can_publish) {
    // No intentar — Proppit responderá 403
    return;
}

try {
    $response = $client->properties()->publish([
        'referenceId' => 'CO-' . $inmueble->id,
        'publisher'   => ['externalId' => $inmobiliaria->proppit_external_id],
        // ... resto del payload
    ]);

    // Guardar referenceId para actualizaciones futuras
    $inmueble->update(['proppit_reference_id' => $response->referenceId]);

} catch (\Propit\Exceptions\PublisherPermissionException $e) {
    // El publisher no está habilitado todavía
    $inmobiliaria->update([
        'proppit_publisher_status' => 'cannot_publish',
        'proppit_can_publish'      => false,
        'proppit_last_error'       => $e->getMessage(),
        'proppit_last_request_id'  => $e->requestId(),
    ]);
}
```

---

## Plantilla para solicitar activación a Proppit

Enviar a soporte de Proppit después de sincronizar el publisher:

```
Hola equipo Proppit,

Ya enviamos/sincronizamos el publisher de la inmobiliaria desde
la integración CRM/lonja de Homlity.

Solicitamos por favor habilitar/conectar este publisher para que
pueda publicar propiedades vía API.

Datos:

  - Publisher external_id         : {external_id}
  - Publisher ID devuelto por API : {publisher_id}   (si aplica)
  - Nombre de la inmobiliaria     : {agency_name}
  - Request ID del sync           : {request_id}     (si aplica)
  - Ambiente                      : producción

También agradecemos confirmar si el identificador activado
corresponde a publisher_id, external_id, integration_id o agency_id,
y qué valor quedó asociado a la integración.

Antes de proceder, confirmaremos si hay propiedades existentes
en la cuenta que deban eliminarse para evitar conflictos con
lo que se enviará desde la API.

Quedamos atentos para iniciar el envío de anuncios.

Gracias.
```

> **Nota sobre el ID `d103d0d0-5d99-4f81-b9e0-ae56cac95872`:**
> Proppit mencionó este identificador al confirmar la activación de un publisher.
> Debe validarse si corresponde a `publisher_id`, `external_id`, `integration_id`
> o `agency_id` revisando la respuesta real del endpoint de publisher o los logs
> de la solicitud original. El SDK **no hardcodea** este valor; se usa solo como
> referencia en esta documentación.

---

## Campos sugeridos en la tabla `inmobiliarias`

El SDK es agnóstico a la base de datos de Homlity. La migración debe crearse en la aplicación consumidora:

```php
// database/migrations/xxxx_add_proppit_fields_to_inmobiliarias_table.php
Schema::table('inmobiliarias', function (Blueprint $table) {
    $table->string('proppit_external_id')->nullable()->unique()
          ->comment('Identificador enviado a Proppit como publisher.id');
    $table->string('proppit_publisher_id')->nullable()->index()
          ->comment('ID devuelto por Proppit en la respuesta');
    $table->string('proppit_activation_id')->nullable()->index()
          ->comment('ID mencionado por Proppit al confirmar activación');
    $table->string('proppit_publisher_status')->nullable()
          ->comment('pending_activation | active | cannot_publish | rejected | error');
    $table->boolean('proppit_can_publish')->default(false)
          ->comment('Solo true cuando Proppit confirma activación');
    $table->text('proppit_last_error')->nullable();
    $table->string('proppit_last_request_id')->nullable();
    $table->timestamp('proppit_last_synced_at')->nullable();
    $table->timestamp('proppit_activated_at')->nullable();
});
```

> El SDK **no crea ni requiere** estas columnas. Son recomendaciones para Homlity.

---

## Jerarquía de excepciones 403

```
ApiException
└── PublisherPermissionException   ← 403 "Publisher could not publish"
    └── PublisherNotReadyException  ← alias compatible con versiones anteriores
ForbiddenException                 ← 403 genérico (no relacionado con publisher)
```

Campos disponibles en `PublisherPermissionException`:
- `$e->requestId()` — Request ID de Proppit (útil para diagnóstico)
- `$e->originalError()` — Texto del error devuelto por Proppit
- `$e->rawResponse()` — Cuerpo sanitizado de la respuesta 403
- `$e->operationalHint()` — Mensaje de guía para operadores

Campos adicionales en `PublisherNotReadyException` (heredados):
- `$e->publisherExternalId` — external_id del publisher involucrado
- `$e->proppitRequestId` — alias de `requestId()`

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

El SDK detecta este error y lanza `PublisherPermissionException` (o su subclase `PublisherNotReadyException`) con el `requestId` de Proppit:

```php
use Propit\Exceptions\PublisherPermissionException;

try {
    $client->properties()->publish($payload);
} catch (PublisherPermissionException $e) {
    // El publisher existe pero Proppit no lo ha activado todavía.
    // NO son credenciales inválidas — el client_id/client_secret son correctos.
    echo $e->requestId();       // 'req-abc123' (de Proppit)
    echo $e->originalError();   // 'Publisher could not publish'
    echo $e->operationalHint(); // guía legible para operadores
}
```

### Diagnóstico del estado del publisher

Mientras esperas la activación, puedes consultar el estado del publisher:

```php
try {
    $response = $client->publishers()->status('homlity_agency_1');
    var_dump($response->raw);              // respuesta completa de Proppit
    var_dump($response->canPublish());     // false hasta que Proppit active
    var_dump($response->activationStatus); // 'pending_activation'
} catch (\Propit\Exceptions\ApiException $e) {
    // 404 = el publisher no existe — ejecutar createOrUpdate() primero
}
```

---

## Errores comunes

| Error | Causa | Solución |
|---|---|---|
| `PublisherPermissionException` | Publisher recibido por Proppit pero no activado | Contactar soporte de Proppit con `external_id` y `requestId()` |
| `ForbiddenException` | 403 genérico no relacionado con publisher | Revisar permisos de la cuenta y endpoint usado |
| `AuthException: Missing PROPIT_CLIENT_ID` | Falta la variable en `.env` | Configurar `PROPIT_CLIENT_ID` |
| `AuthException: Unauthorized response` | Credenciales incorrectas (401) | Verificar `PROPIT_CLIENT_ID` y `PROPIT_CLIENT_SECRET` |
| `ValidationException: externalId is required` | `PublisherPayload::externalId` vacío | Generar el externalId con uuid o id de inmobiliaria |
| `ValidationException: publisher.externalId is required` | Falta el publisher en el ad payload | Incluir `publisher.externalId` en el payload del inmueble |
| `ApiException: HTTP 404` en publisher | Publisher aún no registrado | Ejecutar `createOrUpdate()` primero |

---

## Seguridad

- `client_secret` se redacta completamente en logs.
- `client_id` aparece parcialmente en logs de debug (primeros 4 chars + `***`).
- El header `Authorization: Bearer ...` se redacta en logs.
- `access_token` y `refresh_token` se redactan en logs.
- `rawResponse()` en excepciones devuelve el body sanitizado (sin tokens ni secretos).
- Nunca loguear el body de la request al endpoint `/token`.
