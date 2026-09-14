# Documento 4 — Estructura del Proyecto Flutter

## 1. Principios

- **Clean Architecture ligera + separación por features** (regla 18: "no crear una arquitectura innecesariamente compleja").
- Cada feature es independiente y testeable: `data → domain → presentation`.
- **Riverpod** para estado e inyección de dependencias (providers), sin `Provider`/`GetIt` adicional.
- **Repository Pattern**: la capa `domain` no sabe si los datos vienen de la API o de SQLite local; decide el repositorio según conectividad.
- **Offline-first** desde el diseño, no como parche: todo lo que el motorista necesita puede leerse y escribirse local primero.

## 2. Árbol de carpetas

```
lib/
  core/
    config/            # Environment (dev/staging/prod), endpoints base
    network/
      dio_client.dart          # Dio configurado (interceptors, timeouts)
      auth_interceptor.dart    # inyecta Bearer token, refresca sesión, maneja 401
      connectivity_service.dart
    storage/
      secure_storage.dart      # token, refresh token (flutter_secure_storage)
      app_database.dart        # Drift: definición de la BD local
      sync_queue.dart          # cola de operaciones pendientes de sincronizar
    error/
      failures.dart            # Failure sealed class (Network, Validation, Server, Unknown)
      exceptions.dart
    routing/
      app_router.dart          # go_router, guards por rol
    theme/
      app_theme.dart           # colores/tipografía, botones grandes (sección 23)
    widgets/                   # widgets compartidos (EstadoBadge, EmptyState, LoadingButton...)
    utils/

  features/
    auth/
      data/        (AuthRepositoryImpl, AuthApi, AuthLocalDataSource)
      domain/      (User, AuthRepository interface)
      presentation/ (LoginScreen, AuthController[Riverpod Notifier])

    dashboard/
      data/ domain/ presentation/   # KPIs del día

    customers/
      data/ domain/ presentation/   # CRUD clientes (rol admin)

    products/
      data/ domain/ presentation/   # CRUD productos (rol admin)

    orders/
      data/ domain/ presentation/
        # Lista de pedidos, detalle, creación (supervisor/admin), asignación

    preparation/
      data/ domain/ presentation/
        # Pantalla de preparación: lista solicitado/preparado, escáner integrado

    review/
      data/ domain/ presentation/
        # Bandeja de revisión, aprobar/rechazar

    dispatch/
      data/ domain/ presentation/
        # Crear despacho, asignar vehículo/motorista

    routes/            # Fase 2
      data/ domain/ presentation/

    delivery/          # Fase 2 (con soporte offline desde su diseño)
      data/
        delivery_repository_impl.dart   # decide remoto vs local+cola
        delivery_local_datasource.dart  # Drift
        delivery_remote_datasource.dart # Dio
      domain/
        delivery.dart
        delivery_repository.dart
      presentation/
        delivery_list_screen.dart
        delivery_detail_screen.dart
        signature_capture_widget.dart
        photo_capture_widget.dart

    scanner/           # Fase 3 — código de barras/QR reutilizable por preparation
      presentation/ barcode_scanner_widget.dart

  main.dart
  bootstrap.dart        # inicialización: storage, DB local, providers globales
```

## 3. Manejo de estado (Riverpod)

Cada feature expone:
- `xxxRepositoryProvider` (Provider) → implementación concreta del repositorio.
- `xxxControllerProvider` (NotifierProvider/AsyncNotifier) → estado de UI + casos de uso simples inline (no se crea una clase `UseCase` por cada acción trivial; el método del repositorio ya expresa la intención — regla de simplicidad 29.2). Cuando una operación combina varias fuentes (ej. "finalizar preparación" valida localmente y dispara sync), sí se extrae un método de dominio explícito en el repositorio, no en el controller, para mantenerlo testeable sin Widgets.

Ejemplo (preparación):
```dart
final preparationControllerProvider =
    AsyncNotifierProvider.family<PreparationController, PreparationState, int>(
  PreparationController.new,
);

class PreparationController extends FamilyAsyncNotifier<PreparationState, int> {
  @override
  Future<PreparationState> build(int orderId) => ref
      .read(preparationRepositoryProvider)
      .getPreparation(orderId);

  Future<void> registerScan(String code) async { ... }
  Future<void> finish() async { ... }
}
```

## 4. Offline: Local DB + Sync Queue + Server

```
┌────────────┐   escribe    ┌───────────────┐   encola     ┌──────────────┐
│  UI Motorista │ ───────────▶│  Drift (SQLite) │────────────▶│ sync_queue   │
└────────────┘              └───────────────┘              └──────┬───────┘
                                                                    │ hay red?
                                                                    ▼
                                                            ┌──────────────┐
                                                            │ SyncService  │
                                                            │ (background, │
                                                            │  WorkManager/ │
                                                            │  Timer)       │
                                                            └──────┬───────┘
                                                                    ▼
                                                            POST /api/v1/sync/deliveries
                                                            (idempotente por client_uuid)
```

- Tablas Drift espejo de lo mínimo necesario para trabajar offline: `local_routes`, `local_deliveries`, `local_delivery_items`, `local_evidence`, `sync_queue`.
- Cada operación de escritura (crear/actualizar entrega) genera una fila en `sync_queue` con `client_uuid`, `payload` (JSON), `status (PENDING/SYNCED/ERROR)`, `attempts`.
- `SyncService` corre cuando `ConnectivityService` detecta red: toma pendientes en orden, los envía, marca `SYNCED` con la respuesta del servidor o `ERROR` con reintento exponencial.
- Las fotos/firma se guardan primero en el filesystem local del dispositivo (`path_provider`), se comprimen (`flutter_image_compress`) antes de subir, y el `sync_queue` sube el archivo y luego el registro que lo referencia.
- **Idempotencia**: el `client_uuid` (UUID v4 generado al crear la entrega en el dispositivo) es la clave que el backend usa para upsert — reintentar el mismo POST nunca duplica (ver Documento 2, `deliveries.client_uuid` y `sync_operations`).

## 5. Dependencias principales (pubspec.yaml)

| Paquete | Uso |
|---|---|
| flutter_riverpod | estado / DI |
| go_router | navegación y guards por rol |
| dio | cliente HTTP |
| drift + sqlite3_flutter_libs | base de datos local |
| flutter_secure_storage | token seguro |
| connectivity_plus | detección de red |
| geolocator | GPS |
| image_picker / camera | fotografías |
| signature | firma digital |
| mobile_scanner | código de barras / QR (Fase 3) |
| flutter_image_compress | compresión de evidencias |
| freezed + json_serializable | modelos inmutables y serialización |
| intl | formato de fecha/moneda configurable por empresa |

## 6. Navegación y UX (sección 23)

- `go_router` con **guards por rol**: un motorista no puede navegar (ni por deep link) a pantallas de administración; se resuelve leyendo el rol del usuario autenticado desde `authControllerProvider`.
- Pantallas de trabajo (preparación, entrega) usan **una sola pantalla por tarea** con acciones grandes y visibles, evitando navegación anidada — cumpliendo "el preparador debe poder trabajar rápidamente sin entrar en múltiples pantallas".
- Estados con color/ícono consistente (`EstadoBadge`) reutilizado en toda la app.

## 7. Testing

- `domain` y `data` con tests unitarios (mocks de Dio/Drift vía `mocktail`).
- Widget tests para pantallas críticas (login, preparación, entrega).
- No se persigue 100% de cobertura en el MVP; se prioriza cubrir las reglas críticas de negocio también en el cliente (ej. no permitir cantidad negativa, exigir motivo de diferencia) como primera línea de UX, sabiendo que el servidor es la autoridad final (regla 21).
