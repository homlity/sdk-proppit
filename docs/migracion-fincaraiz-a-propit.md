# Migración mental FincaRaizSDK -> PropitSDK (Proppit Real Time API)

## Cambios clave
- Auth cambia de API key/secret a token bearer con `POST /token`.
- Identificador principal de publicación: `referenceId`.
- Lectura/borrado exige `externalId` del publisher como query param.
- Payload de publicación ahora sigue schema `Ad` oficial de Proppit.

## Tabla técnica de mapeo
| Campo interno/FincaRaíz | Campo Proppit | Tipo | Obligatorio | Transformación | Observaciones |
|---|---|---|---|---|---|
| código externo inmueble | `referenceId` | string | sí | trim | id técnico de anuncio |
| inmobiliaria externa | `publisher.externalId` | string | sí | trim/lower recomendado | requerido para get/delete |
| tipo inmueble | `property.type` | string | sí | homologar catálogo | usar `/property-types` |
| tipo operación | `operations[].type` | enum | sí | venta->sell, arriendo->rent | enum estricto |
| precio venta/arriendo | `operations[].price.value` | number | sí | cast float | con currency |
| moneda | `operations[].price.currency` | enum | sí | ISO soportado por Proppit | ver schema Currency |
| título | `title.text` | string | sí | trim | junto a `title.locale` |
| descripción | `description.text` | string | sí | trim | junto a `description.locale` |
| ciudad/geo | `property.location.geo[]` | array | no | map niveles | opcional |
| latitud/longitud | `property.location.coordinates.lat/long` | number | sí | cast float | obligatorio |
| dirección | `property.location.address` | string | no | trim | opcional |
| área construida | `floorArea` | object | depende tipo | map `{value,unit:sqm}` | reglas por tipo |
| área privada | `usableArea` | object | no | map `{value,unit:sqm}` | opcional |
| área lote | `totalArea` | object | depende tipo | map `{value,unit:sqm}` | requerida en land |
| habitaciones | `bedrooms` | number | no | cast int>=0 | validación local |
| baños | `bathrooms` | number | no | cast int>=0 | validación local |
| garajes | `parkingSpaces` | number | no | cast int>=0 | validación local |
| estrato | `stratum` | number | no | cast int | solo CO según docs |
| imágenes | `multimedia.pictures[].url` | array | no | validar URL | content-type image/* |
| asesor nombre | `contact.name` | string | recomendado | trim | contact no requerido en schema |
| asesor email | `contact.email` | string | recomendado | validar email | si se envía contact |
| asesor teléfono | `contact.phone` | string | no | trim | opcional |
