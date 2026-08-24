# Proppit — Guía de Sincronización Inicial

## Antes de iniciar la integración por API

Proppit recomienda **eliminar las propiedades existentes** en la cuenta antes de iniciar
la sincronización por API. Esto evita conflictos entre el inventario histórico cargado
manualmente y las propiedades que serán gestionadas desde Homlity vía API.

---

## Riesgos de no depurar el inventario previo

| Riesgo | Descripción |
|---|---|
| **Duplicados** | Una misma propiedad puede aparecer dos veces: la versión manual y la versión API con distinto `referenceId` |
| **Referencias cruzadas rotas** | El `referenceId` que asigna Homlity puede no coincidir con el ID de la propiedad pre-existente |
| **Conflictos de actualización** | `PUT` por `referenceId` puede fallar o sobrescribir una propiedad equivocada |
| **Estados incoherentes** | Una propiedad puede estar publicada desde el panel web y archivada desde la API |
| **Publicaciones antiguas no gestionadas** | Propiedades fuera del inventario de Homlity continúan visibles en Proppit |
| **Inventario desfasado** | Actualizaciones por API solo afectan propiedades con `referenceId` conocido; las pre-existentes quedan sin sincronizar |

---

## Checklist de sincronización inicial

Antes de activar la publicación desde Homlity, completar los siguientes pasos **en orden**:

- [ ] **1. Crear/sincronizar publisher**
  Ejecutar `createOrUpdate()` con el `external_id` estable de la inmobiliaria.
  Guardar `proppit_publisher_id` y `proppit_external_id` en la base de datos.

- [ ] **2. Solicitar activación a Proppit**
  Contactar a soporte de Proppit usando la plantilla en `docs/publisher-integration.md`.
  Incluir `external_id`, `publisher_id` (si fue devuelto) y nombre de la inmobiliaria.

- [ ] **3. Confirmar que el publisher está activo**
  Verificar con `$client->publishers()->status($externalId)` que `canPublish() === true`,
  o esperar confirmación explícita del equipo de Proppit.
  Actualizar `proppit_can_publish = true` y `proppit_publisher_status = 'active'` en BD.

- [ ] **4. Eliminar o depurar propiedades existentes en la cuenta Proppit**
  Coordinar con el equipo de Proppit la limpieza del inventario previo.
  Alternativamente, identificar qué propiedades ya gestionadas por Homlity tienen
  un `referenceId` asignado y cuáles no.

- [ ] **5. Iniciar publicación desde API**
  Solo con `proppit_can_publish === true` en la inmobiliaria.
  Usar `referenceId` generado por Homlity (ej. `CO-{inmueble_id}`).

- [ ] **6. Guardar `referenceId` por propiedad**
  Persistir el `referenceId` devuelto por Proppit en la tabla de inmuebles
  para poder actualizar o eliminar la propiedad en el futuro.

- [ ] **7. Usar actualizaciones API para cambios futuros**
  Todos los cambios en propiedades deben hacerse vía `update()` del SDK,
  no desde el panel web de Proppit, para mantener consistencia.

---

## Eliminación de propiedades previas

### Opción A: Limpieza coordinada con Proppit

Solicitar al equipo de Proppit que elimine las propiedades existentes de la cuenta
antes de iniciar la integración API. Incluir en la solicitud:

- Nombre de la cuenta / inmobiliaria
- Confirmación de que las propiedades a eliminar NO están siendo gestionadas desde otro sistema

### Opción B: Eliminación programática desde el SDK

Si las propiedades previas tienen un `referenceId` identificable:

```php
// Eliminar una propiedad por referenceId
$client->properties()->delete('CO-1001');
```

Si no se conoce el `referenceId`, consultar primero a Proppit el listado de propiedades
activas en la cuenta.

### Opción C: Coexistencia controlada

Si no es posible eliminar las propiedades previas, asegurarse de que:

1. Los `referenceId` generados por Homlity son únicos y no colisionan con los IDs existentes.
2. Las propiedades pre-existentes no generan actualizaciones accidentales.
3. Se lleva un registro de cuáles propiedades están bajo gestión API y cuáles no.

> Esta opción conlleva riesgo operativo. Proppit **recomienda limpiar** el inventario previo.

---

## Flujo recomendado por Proppit

Mensaje original de Proppit:

> _"Una vez que envían a publicar el publisher deben informarnos a nosotros para habilitar
> la integración entre lo que ellos enviaron y nosotros en Proppit. Al momento lo hemos
> hecho [para el publisher activado]. Ya pueden enviar los anuncios. Tomar en cuenta que
> las propiedades actuales que tiene la cuenta deben ser eliminadas para que no generen
> conflictos con lo que envíen actualmente desde el API."_

---

## Nota sobre el identificador de activación

Proppit puede informar un identificador (como un UUID) al confirmar la activación del
publisher. Ese identificador debe guardarse en `proppit_activation_id` de la inmobiliaria
para referencia futura.

Pendiente validar si ese identificador corresponde a:
- `publisher_id` interno de Proppit
- `external_id` enviado desde Homlity
- `integration_id` de la conexión
- `agency_id` de la cuenta en Proppit

**No asumir** el tipo sin verificar la respuesta real del endpoint de publisher o los
logs de la solicitud de activación. Preguntar explícitamente a Proppit al solicitar la
activación.

---

## Referencia cruzada

- [docs/publisher-integration.md](publisher-integration.md) — Flujo completo de publisher y plantilla de activación
- [examples/sync_publisher.php](../examples/sync_publisher.php) — Ejemplo de registro de publisher
- [examples/publish_property.php](../examples/publish_property.php) — Ejemplo de publicación de anuncio
