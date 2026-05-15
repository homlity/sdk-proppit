# Proppit OpenAPI Analysis

- Fecha revisión: 2026-05-15
- Swagger UI: https://real-time.proppit.com/api/v2/docs
- OpenAPI detectado en Swagger UI: `https://real-time.proppit.com/api/v2/docs/openapi.yaml`
- Versión spec: OpenAPI 3.1.0
- Server base URL: `https://real-time.proppit.com/api/v2`

## Autenticación oficial
- Esquema: `http bearer` (`components.securitySchemes.Authorization`)
- Obtención token: `POST /token`
- Body: `{ "user": "...", "password": "..." }`
- Respuesta 200: `{ "token": "...", "expiration": 1671620388 }`
- Uso: header `Authorization: Bearer <token>` en endpoints protegidos.

## Endpoints relevantes implementados en SDK
- `POST /proppit/{country}/ads` create/publish ad
- `PUT /proppit/{country}/ads/{referenceId}` update ad
- `GET /proppit/{country}/ads/{referenceId}` con query `externalId`
- `DELETE /proppit/{country}/ads/{referenceId}` con query `externalId`

Nota técnica:
- En el YAML aparece el path como `'/proppit/{country}/ads/{referenceId}?externalId={external-id-publisher}'`; para implementación HTTP se usa path limpio + query param (`externalId`) según `parameters.in=query`.

## Esquema principal de payload (Ad)
- Required: `referenceId`, `publisher`, `property`, `operations`, `title`, `description`
- Campos críticos:
  - `publisher.externalId`
  - `property.type`
  - `property.location.coordinates.lat|long`
  - `operations[].type` enum: `rent|sell`
  - `operations[].price.value`, `operations[].price.currency`
- Multimedia:
  - `multimedia.pictures[].url` URL válida
- Error schema: `status`, `requestId`, `error`, `timestamp`

## Errores documentados
- 400, 401, 404, 500, 502, 503, 504
- No se documenta 429 ni `Retry-After` en el spec, pero el SDK soporta manejo defensivo si aparece.

## TODOs reales por falta de detalle explícito en spec
- Semántica exacta de 404 en `findByExternalId` (si recurso inexistente o no autorizado).
- Catálogo exacto de `property.type`, `rules`, `amenities`, `nearbyLocations` por país: depende de `GET /proppit/{country}/property-types` en runtime.
- Reglas de rate-limit no especificadas formalmente (solo soporte defensivo implementado).
