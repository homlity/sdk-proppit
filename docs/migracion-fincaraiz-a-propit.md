# Migración FincaRaíz SDK → Proppit SDK

Este documento describe los cambios conceptuales y técnicos necesarios para migrar una integración basada en el SDK de FincaRaíz al SDK de Proppit (`propit/sdk-propit`).

---

## Cambios clave de arquitectura

| Aspecto | FincaRaíz SDK | Proppit SDK |
|---|---|---|
| **Auth** | API Key + Secret en headers | Bearer token vía `POST /token` (user/password) |
| **Token lifecycle** | Sin token — credenciales en cada request | Token con expiración, renovación automática |
| **Identificador del anuncio** | Depende de la impl. interna | `referenceId` — string único del integrador |
| **Identificador del publisher** | `agency_id` | `publisher.externalId` |
| **Payload estructura** | Flat o propietaria | Estrictamente jerárquica per OpenAPI |
| **Localización** | Sin locale explícito | Locale obligatorio en `title` y `description` |
| **Áreas** | Campos planos (area, area_lote) | Objetos `{value, unit: sqm}` diferenciados por tipo |
| **Precio** | Campo único `price` | Dentro de `operations[].price.{value, currency}` |
| **Operación** | Campo `tipo_operacion` | `operations[].type: sell \| rent` |
| **Ubicación** | Strings de ciudad/barrio | Objeto `location` con `coordinates` requeridas |
| **Geolocalización** | Opcional | `coordinates.lat/long` — **requerido** |

---

## Tabla técnica de mapeo de campos

| Campo Homlity / FincaRaíz | Campo Proppit | Tipo Proppit | Obligatorio | Transformación | Observaciones |
|---|---|---|---|---|---|
| `codigo_inmueble` | `referenceId` | string | ✅ | `'HOM-' . codigo` | ID único del anuncio |
| `id_inmobiliaria` / `publisher_external_id` | `publisher.externalId` | string | ✅ | string, lowercase si email | Requerido para GET/DELETE |
| `tipo_inmueble` | `property.type` | string | ✅ | homologar catálogo (ver tabla tipos) | Valores desde `GET /property-types` |
| `operacion` | `operations[].type` | enum | ✅ | `Venta → sell`, `Arriendo → rent` | Enum estricto |
| `precio` | `operations[].price.value` | number | ✅ | cast float | Precio de la operación |
| `moneda` | `operations[].price.currency` | enum | ✅ | uppercase ISO | `COP`, `USD`, `EUR`, etc. |
| `titulo` | `title.text` | string | ✅ | trim, max recomendado 200 | Con `title.locale: es-CO` |
| `descripcion` | `description.text` | string | ✅ | trim, max 5000 | Con `description.locale: es-CO` |
| `ubicacion.latitud` | `property.location.coordinates.lat` | float | ✅ | cast float | Requerido por Proppit |
| `ubicacion.longitud` | `property.location.coordinates.long` | float | ✅ | cast float | Requerido por Proppit |
| `ubicacion.direccion` | `property.location.address` | string | ❌ | trim | Opcional |
| `ubicacion.codigo_postal` | `property.location.postcode` | string | ❌ | string | Opcional |
| `ubicacion.ciudad` | `property.location.geo[].name` + level `locality` | string | ❌ | usar nivel `locality` | Geo hierarchy |
| `ubicacion.estado` / `departamento` | `property.location.geo[].name` + level `administrative_area_level_1` | string | ❌ | primer nivel del geo | Departamento |
| `ubicacion.barrio` | `property.location.geo[].name` + level `neighborhood` | string | ❌ | tercer nivel del geo | Opcional |
| `area_construida` | `floorArea.value` | float | Depende tipo | con `unit: sqm` | Obligatorio en no-land |
| `area_terreno` | `totalArea.value` | float | Depende tipo | con `unit: sqm` | Obligatorio en land |
| `area_privada` / `area_util` | `usableArea.value` | float | ❌ | con `unit: sqm` | Opcional |
| `habitaciones` | `bedrooms` | integer ≥ 0 | ❌ | cast int | |
| `banos` | `bathrooms` | integer ≥ 0 | ❌ | cast int | |
| `garajes` | `parkingSpaces` | integer ≥ 0 | ❌ | cast int | |
| `estrato` | `stratum` | integer 1–7 | ❌ | cast int | **Solo CO** |
| `administracion` | `property.communityFees.value` | float | ❌ | + `currency: COP` | Cuota mensual |
| `piso` | `property.floor` | string | ❌ | cast string | |
| `fotos[].url` | `multimedia.pictures[].url` | string (URL) | ❌ | validar URL | Content-type: `image/*` |
| `contacto.nombre` | `contact.name` | string | ❌ | trim | Si se envía contact |
| `contacto.email` | `contact.email` | string (email) | ❌ | validar email | Si se envía contact |
| `contacto.telefono` | `contact.phone` | string | ❌ | trim | |
| `estado_inmueble` | `condition` | enum | ❌ | homologar (ver tabla) | |
| `amueblado` | `furnished` | enum | ❌ | `fully/partly/unfurnished` | |
| `amenidades[]` | `amenities[]` | string[] | ❌ | homologar catálogo | Valores desde `/property-types` |
| `año_construccion` | `constructionYear` | integer 1500–2100 | ❌ | cast int | |
| `visibilidad_mapa` | `property.location.visibility` | enum | ❌ | `accurate/approximate` | Default `approximate` si no hay coords exactas |

---

## Homologación de tipos de inmueble

| Tipo Homlity | Tipo Proppit |
|---|---|
| `casa` | `house` |
| `apartamento`, `apto`, `apartaestudio`, `estudio`, `penthouse`, `duplex` | `apartment` |
| `lote`, `terreno` | `land` |
| `local`, `local comercial`, `comercial` | `commercial` |
| `oficina` | `office` |
| `bodega` | `industrial unit` |
| `villa`, `finca`, `casa campestre`, `townhouse` | `house` o `villa` |

> Los valores exactos aceptados por país se obtienen con `GET /proppit/{country}/property-types`.

---

## Homologación de condición

| Condición Homlity | Condición Proppit |
|---|---|
| `nuevo`, `new` | `new` |
| `excelente`, `excellent` | `excellent` |
| `bueno`, `buen estado` | `good` |
| `normal`, `usado` | `normal` |
| `para remodelar` | `to renew` |
| `seminuevo` | `semi-new` |
| `remodelado`, `renovado` | `renovated` |
| `en construccion` | `in construction` |

---

## Checklist de migración

- [ ] Configurar `PROPIT_API_USER` y `PROPIT_API_PASSWORD` (no son la misma API Key de FincaRaíz)
- [ ] Registrar publisher con `POST /proppit/{country}/publishers` y esperar aprobación del equipo Proppit
- [ ] Confirmar `PROPIT_PUBLISHER_EXTERNAL_ID` con Proppit (requerido para GET/DELETE)
- [ ] Validar que todos los inmuebles tienen `latitud` y `longitud` (requerido)
- [ ] Adaptar `titulo` y `descripcion` con el campo `locale: es-CO`
- [ ] Mapear `tipo_inmueble` local al catálogo de Proppit (`GET /property-types`)
- [ ] Asegurarse de que las fotos tengan URLs públicas accesibles
- [ ] Verificar que el `referenceId` es único y consistente entre publicaciones y actualizaciones
