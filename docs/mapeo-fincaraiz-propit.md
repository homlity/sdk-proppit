# Mapeo de nombres: Finca Raíz -> Proppit SDK

| Tipo | Finca Raíz | Proppit SDK |
|---|---|---|
| Cliente | `FincaRaizClient` | `ProppitClient` |
| API propiedades | `...PropertyApi` | `Proppit\\Api\\PropertyApi` |
| Config base url | `FINCARAIZ_BASE_URL` | `PROPPIT_BASE_URL` |
| Credencial 1 | `FINCARAIZ_API_KEY` | `PROPPIT_API_USER` (compat: `PROPPIT_API_KEY`) |
| Credencial 2 | `FINCARAIZ_API_SECRET` | `PROPPIT_API_PASSWORD` (compat: `PROPPIT_API_SECRET`) |
| Endpoint auth | N/A | `POST /token` |
| Endpoint crear | `/.../properties` | `POST /proppit/{country}/ads` |
| Endpoint actualizar | `/.../properties/{id}` | `PUT /proppit/{country}/ads/{referenceId}` |
| Endpoint consultar | `/.../properties/{id}` | `GET /proppit/{country}/ads/{referenceId}?externalId=` |
| Endpoint eliminar | `/.../properties/{id}` | `DELETE /proppit/{country}/ads/{referenceId}?externalId=` |
| Payload id externo | `external_id` | `referenceId` |
| Payload portal/publicador | `agency_id` | `publisher.externalId` |
