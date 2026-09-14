# Documento 6 — Plan de Implementación por Fases

Cada fase debe dejar una versión funcional y verificable (sección 24/28: no se declara terminada una fase solo porque compila; debe probarse el flujo completo).

## Fase 1 — MVP operativo (alcance de esta sesión)
**Backend (Laravel)**
- Auth (Sanctum), roles/permisos, empresa, usuarios, clientes, productos/categorías.
- Pedidos (CRUD + estados), preparación (registro manual de cantidades, sin escáner aún), revisión (aprobar/rechazar), despacho.
- Auditoría genérica activa desde el primer módulo.
- Aislamiento multiempresa (Global Scope) y policies por rol.
- Dashboard (contadores) y reportes básicos (pedidos, productos) en JSON.
- Seeders con datos demo (empresa, roles, usuarios de cada rol, clientes, productos, pedidos en distintos estados).

**Flutter**
- Login/logout, guardado seguro de token, guard de rutas por rol.
- Dashboard con contadores.
- Listado/detalle de pedidos (según rol).
- Pantalla de preparación (registro manual de cantidades + motivo de diferencia).
- Bandeja de revisión (aprobar/rechazar).
- Creación de despacho.

**Criterio de aceptación Fase 1**: un pedido puede recorrer manualmente `PENDIENTE → PREPARANDO → PREPARADO → APROBADO → DESPACHADO` desde la app, contra el backend real, con los cuatro roles operando cada uno su parte y viendo solo lo que le corresponde.

## Fase 2 — Rutas y entregas con evidencia
- Backend: `vehicles`, `routes`, `route_stops`, `deliveries`, `delivery_items`, `delivery_evidence`, endpoints de rutas/entregas, almacenamiento de archivos.
- Flutter: gestión de rutas (supervisor), pantalla de entrega del motorista con firma, fotografía y GPS.
- **Criterio de aceptación**: un despacho puede agruparse en una ruta, asignarse a un motorista, y completarse como entrega COMPLETA/PARCIAL/RECHAZADA con evidencia visible desde el backend.

## Fase 3 — Offline y escáner
- Backend: `sync_operations`, endpoint idempotente de sync en lote, `products.barcode` ya explotado por `/products/lookup`.
- Flutter: Drift (BD local), `sync_queue`, `ConnectivityService`, integración de `mobile_scanner` en preparación y entrega.
- **Criterio de aceptación**: el motorista completa una entrega en modo avión y, al recuperar señal, se sincroniza sin duplicados (probado reintentando el envío manualmente).

## Fase 4 — SaaS
- Panel de gestión de empresas (alta de tenants), suscripciones/facturación (integración a definir con el negocio — **requiere tu confirmación** antes de elegir proveedor de pagos, por ser una decisión de alto impacto arquitectónico), API pública documentada (OpenAPI) para integraciones de terceros.
- Estas piezas se diseñan pero no se implementan en esta sesión salvo que lo pidas explícitamente.

## Alcance de esta entrega concreta

Dado el tamaño del proyecto (equivalente a varias semanas de un equipo), en esta sesión se completa **Fase 1 de extremo a extremo y funcional** (backend corriendo con migraciones/seeders/pruebas reales, app Flutter compilando y navegando contra ese backend), dejando Fases 2-4 completamente especificadas en estos documentos y con el modelo de datos ya preparado para no requerir migraciones destructivas después.

## Checklist de calidad por fase (sección 28), aplicado a Fase 1
- [ ] Migraciones corren limpio (`php artisan migrate:fresh --seed`).
- [ ] Rutas API responden con los códigos y payloads esperados (probado con tests de Feature/HTTP).
- [ ] Policies bloquean cruce de datos entre empresas (test dedicado).
- [ ] `flutter analyze` sin errores; `flutter test` verde.
- [ ] Flujo manual verificado: login por cada rol → acción permitida a ese rol → 403 en acción no permitida.
- [ ] Estados de pedido siguen la máquina de estados definida (no hay saltos inválidos).
