# Documento 5 — API Endpoints (REST, versionada `/api/v1`)

Convenciones:
- Todas las rutas (excepto `/auth/login`) requieren `Authorization: Bearer {token}` (Sanctum).
- `company_id` **nunca** viaja en la URL/body: se resuelve del usuario autenticado.
- Respuestas paginadas: `{data: [...], meta: {current_page, per_page, total}}`.
- Errores: `{message, errors: {campo: [...]}}` con status HTTP correcto (422 validación, 403 autorización, 404, 409 conflicto de estado).
- Autorización: cada endpoint documenta el/los roles permitidos; se aplica vía Policy, no solo por ruta.

## Auth
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/auth/login` | público | email+password → token + datos de usuario/empresa/rol |
| POST | `/auth/logout` | autenticado | revoca el token actual |
| POST | `/auth/forgot-password` | público | envía enlace de recuperación |
| POST | `/auth/reset-password` | público | aplica nueva contraseña con token |
| GET | `/auth/me` | autenticado | usuario, rol, permisos, empresa actual |

## Empresa
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/company` | todos | datos de la empresa actual (config, logo, moneda) |
| PUT | `/company` | ADMIN | actualizar configuración/parámetros |

## Usuarios
| Método | Ruta | Rol |
|---|---|---|
| GET | `/users` | ADMIN |
| POST | `/users` | ADMIN |
| GET | `/users/{id}` | ADMIN |
| PUT | `/users/{id}` | ADMIN |
| DELETE | `/users/{id}` | ADMIN (soft delete) |
| GET | `/roles` | ADMIN | catálogo de roles/permisos |

## Clientes
| Método | Ruta | Rol |
|---|---|---|
| GET | `/customers` | ADMIN, SUPERVISOR |
| POST | `/customers` | ADMIN |
| GET | `/customers/{id}` | ADMIN, SUPERVISOR |
| PUT | `/customers/{id}` | ADMIN |
| DELETE | `/customers/{id}` | ADMIN |

## Productos
| Método | Ruta | Rol |
|---|---|---|
| GET | `/products` | todos (lectura) |
| POST | `/products` | ADMIN |
| GET | `/products/{id}` | todos |
| PUT | `/products/{id}` | ADMIN |
| DELETE | `/products/{id}` | ADMIN |
| GET | `/products/lookup?barcode=` | PREPARADOR | resolución rápida para escáner |
| GET | `/product-categories` | todos |

## Vehículos
| Método | Ruta | Rol |
|---|---|---|
| GET/POST/PUT/DELETE | `/vehicles` `/vehicles/{id}` | ADMIN, SUPERVISOR (lectura) |

## Pedidos
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/orders` | ADMIN, SUPERVISOR (todos) / PREPARADOR (solo asignados) | filtros: `status, customer_id, date_from, date_to` |
| POST | `/orders` | ADMIN, SUPERVISOR | crea pedido + `order_items` |
| GET | `/orders/{id}` | según asignación | detalle con items |
| PUT | `/orders/{id}` | ADMIN, SUPERVISOR | solo si `status = PENDIENTE` |
| POST | `/orders/{id}/assign` | SUPERVISOR | asigna preparador → `PREPARANDO` |
| POST | `/orders/{id}/cancel` | ADMIN, SUPERVISOR | → `CANCELADO`, exige motivo |
| GET | `/orders/{id}/trace` | según rol | línea de tiempo completa (Documento 3 §6) |

## Preparación
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/orders/{id}/preparation` | PREPARADOR, SUPERVISOR | estado actual de preparación |
| POST | `/orders/{id}/preparation/items` | PREPARADOR | registra/actualiza cantidad preparada de un `order_item` (body: `order_item_id, quantity_prepared, difference_reason?`) |
| POST | `/orders/{id}/preparation/scan` | PREPARADOR | body: `barcode` → incrementa cantidad preparada del producto resuelto (aplica reglas de sobre-empaque) |
| POST | `/orders/{id}/preparation/finish` | PREPARADOR | → `orders.status = PREPARADO` |

## Revisión
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/reviews/pending` | REVISOR | pedidos en `PREPARADO` |
| POST | `/orders/{id}/review/approve` | REVISOR | → `APROBADO` |
| POST | `/orders/{id}/review/reject` | REVISOR | body: `reason` (obligatorio) → vuelve a `PREPARANDO` |

## Despacho
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/dispatches` | ADMIN, SUPERVISOR | filtros: fecha, cliente, usuario, vehículo |
| POST | `/orders/{id}/dispatch` | SUPERVISOR | body: `vehicle_id, driver_id` → crea `dispatch` + `dispatch_items` desde `preparation_items` aprobados |
| GET | `/dispatches/{id}` | según rol | detalle |

## Rutas (Fase 2)
| Método | Ruta | Rol |
|---|---|---|
| GET | `/routes` | ADMIN, SUPERVISOR |
| POST | `/routes` | SUPERVISOR — body: `vehicle_id, driver_id, dispatch_ids[]` (orden = índice del array) |
| PUT | `/routes/{id}/reorder` | SUPERVISOR — body: `stop_ids[]` en nuevo orden |
| POST | `/routes/{id}/start` | SUPERVISOR/MOTORISTA |
| POST | `/routes/{id}/finish` | SUPERVISOR/MOTORISTA |
| GET | `/routes/mine` | MOTORISTA — solo su ruta activa |

## Entregas (Fase 2, con soporte offline)
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/routes/{id}/deliveries` | MOTORISTA | detalle de todas las paradas para descarga offline |
| POST | `/deliveries` | MOTORISTA | **idempotente por `client_uuid`**: crea o retorna la existente si ya se procesó ese UUID |
| POST | `/deliveries/{id}/evidence` | MOTORISTA | multipart: foto/firma comprimidas |
| POST | `/sync/deliveries/batch` | MOTORISTA | envío en lote de operaciones encoladas offline (mismo contrato idempotente item por item, respuesta detalla éxito/error por `client_uuid`) |

## Dashboard
| Método | Ruta | Rol |
|---|---|---|
| GET | `/dashboard/summary` | ADMIN, SUPERVISOR | contadores del día (pedidos, preparados, en revisión, despachados, en ruta, entregados, parciales, rechazados) |
| GET | `/dashboard/charts` | ADMIN, SUPERVISOR | series para pedidos por día/cliente/producto |

## Reportes
| Método | Ruta | Filtros |
|---|---|---|
| GET | `/reports/orders` | fecha, cliente, estado |
| GET | `/reports/dispatches` | fecha, cliente, usuario, vehículo |
| GET | `/reports/deliveries` | fecha, cliente, motorista, resultado |
| GET | `/reports/products` | solicitado/preparado/despachado/entregado/diferencias |
| GET | `/reports/customers` | pedidos/unidades/entregas/diferencias/rechazos |
| GET | `/reports/{tipo}/export?format=csv\|xlsx\|pdf` | mismo filtro, exporta (Fase 2+) |

## Auditoría
| Método | Ruta | Rol |
|---|---|---|
| GET | `/audit-logs` | ADMIN — filtros: `auditable_type, auditable_id, user_id, date_from, date_to` |

## Notas de diseño
- Los verbos de transición de estado (`assign`, `finish`, `approve`, `reject`, `dispatch`, `start`, `finish`) son **acciones explícitas** (`POST /recurso/{id}/accion`), no `PUT` genéricos — así cada transición puede tener su propia autorización, validación y evento de auditoría, evitando que un `PUT` arbitrario salte reglas de negocio (regla 21).
- Todas las respuestas de recursos usan **API Resources** de Laravel para no filtrar columnas internas y mantener el contrato estable aunque cambie el esquema de base de datos.
