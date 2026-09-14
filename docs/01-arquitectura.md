# Documento 1 — Arquitectura General

## Sistema de Control de Despachos y Entregas

### 1. Visión general

El producto controla el ciclo completo:

```
PEDIDO → PREPARACIÓN → REVISIÓN → DESPACHO → RUTA → ENTREGA → CONFIRMACIÓN
```

Está diseñado desde el día uno para dos consumidores de la misma API:

- **App móvil (Flutter)**: preparadores, revisores, motoristas y supervisores en campo/almacén (Android primero, iOS/Web/Tablet después).
- **Backend API REST (Laravel)**: única fuente de verdad, versionada (`/api/v1`), stateless, consumible también por un futuro panel web de administración sin cambios de contrato.

El principio rector es la **trazabilidad**: toda cantidad (solicitada, preparada, revisada, despachada, entregada) y todo cambio de estado queda registrado con usuario, fecha/hora y motivo, de forma que el sistema pueda responder en cualquier momento las preguntas de la sección 30 del brief (qué se pidió, qué se preparó, quién lo hizo, qué faltó, por qué, cuándo).

### 2. Por qué Laravel (justificación backend)

El PDF exige API REST + PostgreSQL, autenticación segura, multiempresa, auditoría, versionado, almacenamiento de archivos. Laravel cubre todo esto con herramientas de primera clase y reduce el trabajo "de fontanería" para poder concentrarnos en el dominio:

| Requisito | Solución en Laravel |
|---|---|
| API REST versionada | Rutas agrupadas `routes/api_v1.php`, prefijo `/api/v1`, API Resources para contratos estables |
| Autenticación segura para apps móviles | **Laravel Sanctum** (tokens personales, no cookies, ideal para Flutter) |
| Autorización por roles | Policies + Gates + un modelo `Role`/`Permission` propio (simple, sin dependencias pesadas tipo Spatie para no acoplar el MVP a un paquete externo, pero con la misma forma de tabla pivote) |
| Multiempresa / aislamiento | Global Scope `CompanyScope` aplicado automáticamente a todos los modelos tenant-aware + middleware `EnsureCompanyContext` |
| Auditoría | Tabla `audit_logs` inmutable poblada vía `Observer` genérico (`Auditable` trait) — no depende del estado actual del registro |
| Almacenamiento de archivos (evidencias) | Laravel Filesystem (`local`/`s3` intercambiable), discos por empresa |
| Validación de negocio en servidor | Form Requests + Services de dominio (nunca confiar solo en Flutter) |
| Escalabilidad a SaaS | Misma base de datos con `company_id` (arquitectura *shared database, shared schema* con Row Level Security lógica vía scopes) — migrable a esquemas separados si el negocio lo requiere sin rediseñar el dominio |

PostgreSQL se usa en producción (constraints fuertes, JSONB para configuración flexible por empresa, índices parciales). Para desarrollo/pruebas locales se admite SQLite (mismo esquema, sin extensiones específicas de Postgres en la lógica de negocio) para acelerar el ciclo de feedback.

### 3. Capas del backend (arquitectura por capas + servicios de dominio)

```
HTTP Layer        → Controllers (delgados) + Form Requests (validación) + API Resources (serialización)
Application Layer → Services (OrderService, PreparationService, DispatchService, DeliveryService...)
                     Cada transición de estado es un método de servicio, no lógica en el controller.
Domain Layer       → Models + Enums de estado + Policies (autorización) + Events/Listeners (auditoría, notificaciones)
Infrastructure     → Eloquent (Postgres), Filesystem (evidencias), Sanctum (auth)
```

No se usa Clean Architecture "a la Flutter" (casos de uso + interactors) en el backend porque Laravel ya aporta una convención madura (MVC + Services); imponer una capa extra sin necesidad violaría la regla de "no crear arquitectura innecesariamente compleja" (sección 18/29).

### 4. Multiempresa (Tenant)

- Toda tabla de negocio tiene `company_id` (FK a `companies`).
- Un **Global Scope** (`BelongsToCompany`) filtra automáticamente por `company_id` del usuario autenticado en cada query.
- El `company_id` del usuario viaja en el token/sesión, nunca se acepta desde el cliente en el body/query — se resuelve siempre en el servidor a partir del usuario autenticado.
- Un usuario `SUPER_ADMIN` (futuro, Fase 4) podrá operar sin scope para soporte, pero está fuera del alcance del MVP.
- Esto permite pasar a SaaS (Fase 4) sin migrar datos: ya nacen aislados por tenant.

### 5. Seguridad

- Auth: Sanctum (tokens Bearer, revocables, con expiración configurable, uno por dispositivo).
- Autorización: Policies por modelo (`OrderPolicy`, `DispatchPolicy`, etc.) + roles fijos definidos en el módulo Auth.
- Validación de entradas: Form Requests con reglas explícitas (nunca `$request->all()` sin validar).
- Reglas críticas de negocio (cantidades no negativas, no exceder lo despachado al entregar, no reaprobar un rechazo silenciosamente, etc.) se validan en el `Service`, no solo en el Request — así ninguna vía de entrada (API, job, comando) puede saltárselas.
- Secretos (DB, tokens de terceros, claves de storage) solo en variables de entorno (`.env`, fuera de git). Nunca hardcodeados en Flutter ni en el repo.
- Archivos de evidencia servidos mediante rutas firmadas/autorizadas, no un disco público abierto.

### 6. Offline-first (app móvil)

El backend no necesita "saber" que el cliente estuvo offline: expone endpoints **idempotentes** (la app envía un `client_uuid` generado en el dispositivo al crear una entrega; el servidor hace upsert por ese UUID) para que reintentos de sincronización nunca dupliquen una entrega. El detalle de la estrategia offline (Local DB + Sync Queue) se documenta en el Documento 4.

### 7. Por qué Flutter con Clean Architecture "ligera"

- **Riverpod** como state management moderno, testeable, sin `BuildContext` acoplado, con buen soporte para inyección de dependencias (sustituye a un DI manual).
- **Repository Pattern**: cada feature tiene una interfaz de repositorio con dos implementaciones posibles (remota vía Dio, local vía Drift/SQLite) resueltas por disponibilidad de red — así el offline no es un parche sino parte del contrato.
- Separación por **features** (no por capas técnicas a nivel raíz) para que el código relacionado con "preparación" viva junto y sea fácil de mantener/testear de forma aislada.
- Se evita sobre-ingeniería: no hay capas de "use cases" individuales por cada acción trivial cuando un método de repositorio ya expresa la intención con claridad (regla 29.2 "prioriza simplicidad").

### 8. Entorno de ejecución y despliegue (referencial)

- Backend: PHP 8.4 + Laravel 11 + PostgreSQL 15+ + Redis (colas/caché, opcional en Fase 1, recomendado desde Fase 2 para jobs de sincronización/reportes).
- Móvil: Flutter (canal stable), Android API 24+ como mínimo soportado.
- CI sugerido (no incluido en el MVP): lint + tests en cada push.

### 9. Decisiones menores tomadas por defecto (regla 29)

| Decisión | Elección | Motivo |
|---|---|---|
| Auth API | Sanctum, no Passport/OAuth2 | Passport es para terceros/OAuth; Sanctum es el estándar para SPA/mobile propios, más simple |
| Roles/permisos | Tablas propias `roles`/`permissions` | Evita dependencia externa para un modelo de permisos fijo y conocido de antemano |
| State management Flutter | Riverpod | Moderno, sin ceremonia excesiva, buen soporte de testing |
| Local DB Flutter | Drift (SQLite) | Tipado, migraciones, ideal para sync queue |
| Base de datos dev/test | SQLite | Ciclo de feedback rápido sin infraestructura extra; producción usa Postgres |

Cualquier decisión que afecte significativamente el modelo de datos o el contrato de API se marca explícitamente en los documentos siguientes para tu confirmación.
