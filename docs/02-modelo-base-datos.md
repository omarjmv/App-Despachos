# Documento 2 — Modelo de Base de Datos

Todas las tablas de negocio incluyen `id` (bigint PK), `created_at`, `updated_at`, y `company_id` cuando aplica (todas excepto `companies`, `audit_logs` que referencia pero no pertenece a una sola empresa de forma exclusiva si en el futuro hay superadmin, y tablas puramente de catálogo global). Soft delete (`deleted_at`) se aplica donde borrar un registro podría romper trazabilidad histórica (customers, products, users, vehicles) — nunca en tablas de eventos/hechos (order_items, dispatches, deliveries, audit_logs), que son append-only.

## 1. Diagrama entidad-relación (textual)

```
companies 1───* users
companies 1───* roles (roles del sistema, seed fijo; ver nota)
companies 1───* customers
companies 1───* products ──* product_categories
companies 1───* vehicles
companies 1───* orders ──* order_items ──* products
                 │
                 └─* preparations ──* preparation_items ──* order_items
                          │
                          └─1 reviews
                                │
                                └─* dispatches ──* dispatch_items ──* preparation_items
                                          │
                                          └─* route_stops ──1 routes ──1 vehicles / users(motorista)
                                                    │
                                                    └─1 deliveries ──* delivery_items
                                                              │
                                                              └─* delivery_evidence

audit_logs   *───1 users (nullable), polimórfico hacia cualquier entidad auditable
```

## 2. Entidades

### companies (empresa / tenant)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | string | `Company.name`, nunca hardcodear nombre de cliente |
| slug | string unique | subdominio/identificador |
| logo_path | string nullable | |
| currency | string(3) | default configurable, ej. HNL/USD |
| timezone | string | |
| date_format | string | configurable (sección 25) |
| settings | jsonb | parámetros libres (ej. `allow_overpack`, umbrales de autorización) |
| is_active | boolean | |
| deleted_at | timestamp nullable | |

### roles
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | string unique | `ADMINISTRADOR, SUPERVISOR, PREPARADOR, REVISOR, MOTORISTA` (seed global, no por empresa) |
| description | string | |

### permissions / role_permission
Tabla `permissions` (slug, description) y pivote `role_permission(role_id, permission_id)`. Permisos iniciales mapeados 1:1 a las acciones listadas en la sección 4 del brief (ej. `orders.assign`, `preparation.execute`, `dispatch.approve`, `delivery.execute`). Se seedean junto con los roles.

### users
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| company_id | FK companies | |
| role_id | FK roles | un rol principal por usuario en el MVP |
| name | string | |
| email | string unique (por company_id) | |
| phone | string nullable | |
| password | string (hash) | |
| is_active | boolean | |
| last_login_at | timestamp nullable | |
| deleted_at | timestamp nullable | |

`personal_access_tokens` (tabla estándar de Sanctum) maneja los tokens.

### customers
| Campo | Tipo |
|---|---|
| id, company_id | |
| code | string, único por empresa |
| name | string |
| description | string nullable |
| phone, email | nullable |
| address | text nullable |
| latitude, longitude | decimal nullable (ubicación GPS) |
| is_active | boolean |
| deleted_at | nullable |

### product_categories
| id, company_id, name, is_active |

### products
| Campo | Tipo | Notas |
|---|---|---|
| id, company_id | |
| category_id | FK nullable |
| sku | string, único por empresa |
| barcode | string nullable, indexado (escáner) |
| name, description | |
| unit | string (ej. UNIDAD, CAJA, LITRO) |
| is_active | boolean |
| price, cost | decimal nullable (**preparado para futuro**, no usado en Fase 1) |
| weight, volume | decimal nullable (**futuro**) |
| deleted_at | nullable |

> Campos de inventario/lote/vencimiento se dejan como **extensión futura** (tabla `product_stocks` en Fase posterior) para no acoplar el MVP a control de inventario, que el brief marca como "preparar arquitectura", no implementar ya.

### vehicles
| id, company_id, plate (unique por company_id), brand, model, capacity, is_active, deleted_at |

### orders (pedidos)
| Campo | Tipo | Notas |
|---|---|---|
| id, company_id | |
| number | string, único por empresa (correlativo, ej. `PED-000001`) |
| customer_id | FK customers |
| created_by | FK users |
| order_date | date |
| required_date | date nullable |
| status | enum string: `PENDIENTE, PREPARANDO, PREPARADO, EN_REVISION, APROBADO, DESPACHADO, EN_RUTA, ENTREGADO, PARCIAL, CANCELADO` |
| notes | text nullable |

### order_items
| id, order_id, product_id, quantity_requested (decimal >0), unit, notes nullable |
No se borran ni modifican una vez que el pedido pasa de `PENDIENTE` salvo por el creador y solo mientras esté en `PENDIENTE` (regla de negocio, ver Documento 3).

### preparations
| Campo | Tipo | Notas |
|---|---|---|
| id, company_id, order_id | |
| prepared_by | FK users |
| status | enum: `EN_PROCESO, FINALIZADA` |
| started_at, finished_at | timestamp nullable |

### preparation_items
| id, preparation_id, order_item_id, quantity_prepared (decimal ≥0), difference_reason (enum nullable: `FALTANTE_INVENTARIO, PRODUCTO_DANADO, ERROR_PEDIDO, SUSTITUCION, OTRO`), difference_notes text nullable |

Regla: si `quantity_prepared < order_item.quantity_requested` ⇒ `difference_reason` es obligatorio (validado en servidor).

### reviews
| Campo | Tipo | Notas |
|---|---|---|
| id, company_id, preparation_id | |
| reviewed_by | FK users |
| result | enum: `APROBADO, RECHAZADO` |
| rejection_reason | text nullable (obligatorio si `RECHAZADO`) |
| reviewed_at | timestamp |

### dispatches (despachos)
| Campo | Tipo | Notas |
|---|---|---|
| id, company_id | |
| number | string único por empresa (`DES-000001`) |
| order_id | FK orders (referencia, nunca se modifica el pedido original) |
| review_id | FK reviews (debe estar `APROBADO`) |
| vehicle_id | FK vehicles nullable |
| driver_id | FK users nullable (motorista) |
| dispatched_by | FK users |
| dispatched_at | timestamp |

### dispatch_items
| id, dispatch_id, preparation_item_id, quantity_dispatched (decimal ≥0) |

### routes
| Campo | Tipo | Notas |
|---|---|---|
| id, company_id | |
| code | string único por empresa (`RUTA-000024`) |
| vehicle_id | FK vehicles |
| driver_id | FK users |
| status | enum: `PLANIFICADA, EN_CURSO, FINALIZADA` |
| started_at, finished_at | nullable |

### route_stops
| id, route_id, dispatch_id, sequence (int, orden de entrega), status (`PENDIENTE, EN_CURSO, COMPLETADA`) |

### deliveries (entregas)
| Campo | Tipo | Notas |
|---|---|---|
| id, company_id, route_stop_id | |
| dispatch_id | FK dispatches |
| client_uuid | string unique — generado en el dispositivo, garantiza **idempotencia** en la sincronización offline |
| delivered_by | FK users (motorista) |
| result | enum: `COMPLETA, PARCIAL, RECHAZADA` |
| rejection_reason | text nullable |
| notes | text nullable |
| latitude, longitude | decimal nullable |
| delivered_at | timestamp |
| synced_at | timestamp nullable (cuándo llegó al servidor, distinto de `delivered_at` real en campo) |

### delivery_items
| id, delivery_id, dispatch_item_id, quantity_delivered (decimal ≥0, ≤ quantity_dispatched), quantity_rejected (decimal ≥0), rejection_reason enum nullable |

### delivery_evidence
| Campo | Tipo | Notas |
|---|---|---|
| id, delivery_id | |
| type | enum: `FIRMA, FOTO, DOCUMENTO` |
| file_path | string (disco privado) |
| mime_type, size_bytes | |
| captured_at | timestamp |

### audit_logs (append-only, sin updated_at mutable)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK |
| company_id | FK nullable (algunas acciones de sistema) |
| user_id | FK users nullable |
| action | string (ej. `APROBAR_DESPACHO`, `CREAR_PEDIDO`, `RECHAZAR_REVISION`) |
| auditable_type, auditable_id | polimórfico (Eloquent `morphs`) |
| old_values, new_values | jsonb nullable (snapshot, no referencia al registro actual) |
| ip_address | string nullable |
| created_at | timestamp |

> Clave del requisito 15: "la auditoría no debe depender únicamente de los datos actuales del registro" ⇒ se guarda **snapshot** (`old_values`/`new_values`) en el propio log, no un puntero que cambia con el registro.

### sync_operations (soporte a modo offline, Fase 3 — modelado desde ya)
| id, company_id, device_id, client_uuid, entity_type, payload (jsonb), status (`PENDIENTE, PROCESADA, ERROR`), processed_at, error_message |

Sirve como bitácora servidor-side de qué `client_uuid` ya se procesaron, reforzando la idempotencia además de la unique constraint en `deliveries.client_uuid`.

## 3. Índices y constraints clave

- `UNIQUE(company_id, email)` en `users`.
- `UNIQUE(company_id, code)` en `customers`.
- `UNIQUE(company_id, sku)` en `products`; índice en `barcode`.
- `UNIQUE(company_id, number)` en `orders` y `dispatches`.
- `UNIQUE(client_uuid)` en `deliveries` — evita duplicados por reintentos de sync.
- `CHECK(quantity_requested > 0)`, `CHECK(quantity_prepared >= 0)`, `CHECK(quantity_delivered >= 0)` etc. a nivel de base de datos (Postgres) además de validación en Laravel.
- FK con `ON DELETE RESTRICT` en relaciones de trazabilidad (no se puede borrar un pedido con despachos), `ON DELETE CASCADE` solo en detalle-de-detalle (`order_items` cuando se borra un `order` en estado `PENDIENTE`).
- Índices en `company_id` de cada tabla (todas las consultas filtran por tenant) y en `status` de `orders`/`deliveries` para dashboards.

## 4. Regla de aislamiento multiempresa a nivel de datos

Ninguna tabla de negocio permite relaciones cruzadas entre `company_id` distintos (validado tanto por el Global Scope de Eloquent como por constraints de aplicación en los Services, ya que una FK por sí sola no impide que dos registros de la misma empresa A referencien un registro de empresa B con el mismo tipo — se valida explícitamente en los Services antes de persistir).
