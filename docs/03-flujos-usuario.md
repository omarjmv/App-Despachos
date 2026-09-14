# Documento 3 — Flujos de Usuario

Convención de estados de `orders.status`:
`PENDIENTE → PREPARANDO → PREPARADO → EN_REVISION → APROBADO → DESPACHADO → EN_RUTA → ENTREGADO | PARCIAL` (o `CANCELADO` desde cualquier estado previo a `DESPACHADO`).

## 1. ADMINISTRADOR
1. Configura la empresa (nombre, logo, moneda, formato de fecha, parámetros como "permitir sobre-preparación").
2. Da de alta usuarios y les asigna un rol.
3. Mantiene catálogos: clientes, productos, categorías, vehículos.
4. Consulta el Dashboard y todos los reportes de la empresa, sin restricción.
5. No participa operativamente en pedidos (puede, pero no es su flujo típico); puede reasignar cualquier cosa en caso de soporte.

## 2. SUPERVISOR
1. Ve la bandeja de **pedidos pendientes**.
2. Crea o recibe un pedido con su detalle (producto + cantidad solicitada).
3. **Asigna** el pedido a un preparador → `orders.status = PREPARANDO`.
4. Supervisa el avance de preparación en tiempo real (progreso por producto).
5. Cuando el revisor aprueba, el supervisor **crea el despacho** (asocia vehículo y motorista) → `orders.status = DESPACHADO`.
6. Agrupa uno o varios despachos en una **ruta**, ordena las paradas y la asigna a un motorista/vehículo → inicia la ruta → `orders.status = EN_RUTA`.
7. Consulta reportes de su operación (pedidos, despachos, entregas, diferencias).

## 3. PREPARADOR
1. Ve **solo los pedidos que le fueron asignados** (no ve el resto de la operación).
2. Abre un pedido y ve la lista de productos con `solicitado` vs `preparado` (este último inicia en 0).
3. Registra cantidades preparadas:
   - Manualmente, o
   - Escaneando código de barras/QR (ver flujo de escáner abajo).
4. El sistema calcula automáticamente totales y diferencia (`solicitado − preparado`).
5. Si hay diferencia, el sistema **exige un motivo** por producto (`FALTANTE_INVENTARIO, PRODUCTO_DANADO, ERROR_PEDIDO, SUSTITUCION, OTRO`).
6. **No puede** modificar `order_items` (cantidades solicitadas) — solo registra lo preparado.
7. Finaliza preparación → `orders.status = PREPARADO` → queda disponible para revisión. No puede reabrir una preparación finalizada (debe pedir a un supervisor/admin si hubo un error).

### Flujo de escáner (detalle sección 8)
```
Escanear código → identificar producto en el pedido
   → si el producto no pertenece al pedido: mostrar error, no incrementar nada
   → incrementar cantidad preparada en +1 (o cantidad configurada por lectura)
   → comparar contra cantidad solicitada
        == solicitado → marcar producto como "completo" (visual verde)
        >  solicitado → bloquear el incremento y pedir autorización explícita
                         (un supervisor/admin confirma en pantalla o el propio
                          preparador confirma con un segundo tap, según
                          settings.allow_overpack de la empresa) antes de aplicar
```
Esto evita incrementos accidentales por doble lectura del mismo código.

## 4. REVISOR
1. Ve pedidos en estado `PREPARADO` pendientes de revisión.
2. Revisa producto por producto: cantidades, diferencias y sus motivos, observaciones del preparador.
3. Decide:
   - **Aprobar despacho** → crea `reviews(result=APROBADO)` → `orders.status = EN_REVISION → APROBADO`.
   - **Rechazar** → el sistema **obliga** a indicar `rejection_reason` → `orders.status` vuelve a `PREPARANDO` (o queda en un estado de corrección) para que el preparador ajuste.
4. Cada acción registra usuario, fecha, hora, resultado y observación (tabla `reviews` + `audit_logs`).
5. Una revisión ya `APROBADO` **no puede modificarse silenciosamente**: cualquier corrección posterior genera una nueva revisión enlazada, nunca un `UPDATE` que borre el resultado anterior.

## 5. MOTORISTA / ENTREGADOR
1. Al iniciar sesión ve **únicamente su ruta asignada del día** y las entregas que le corresponden (nunca las de otro motorista).
2. Puede **descargar la ruta completa para trabajar offline** (pedidos, clientes, cantidades despachadas).
3. Inicia una entrega (`route_stop → EN_CURSO`).
4. Registra: cantidad entregada por producto, cantidad rechazada + motivo si aplica, observaciones.
5. Captura evidencia: firma digital (canvas), fotografía(s), ubicación GPS automática, fecha/hora del dispositivo.
6. Finaliza la entrega → el sistema calcula el resultado automáticamente:
   - `entregado == despachado` → `COMPLETA`
   - `0 < entregado < despachado` → `PARCIAL`
   - `entregado == 0` (rechazo total) → `RECHAZADA`
7. Si no hay conexión, todo el paso 3-6 ocurre contra la base local; al recuperar señal, se sincroniza automáticamente en segundo plano sin bloquear al motorista (ver Documento 4, offline).
8. Al finalizar todas las paradas, puede finalizar la ruta → `routes.status = FINALIZADA`.

## 6. Trazabilidad transversal (para cualquier rol con permiso de consulta)

Dado un pedido, el sistema debe poder mostrar la línea de tiempo completa:

```
PEDIDO #1052 creado por Juan (12:00)
  → Asignado a preparador María (12:05, por Supervisor Carlos)
  → Preparación finalizada por María (12:40) — Agua 1L: preparado 55/60, motivo: FALTANTE_INVENTARIO
  → Revisado y APROBADO por Luis (12:50)
  → Despacho DES-000210 creado por Carlos (13:00) — Vehículo HN-1234, Motorista Pedro
  → Ruta RUTA-000024 iniciada por Pedro (13:30)
  → Entregado por Pedro (14:10) — 140/145, PARCIAL, motivo cliente rechazó 5 unidades dañadas
     Evidencia: firma.png, foto1.jpg, GPS 14.10,-87.20
```

Esta vista se construye uniendo `orders → preparations → preparation_items → reviews → dispatches → dispatch_items → route_stops → deliveries → delivery_items → delivery_evidence`, más las entradas correspondientes de `audit_logs`. Es el endpoint `GET /api/v1/orders/{id}/trace` (ver Documento 5).

## 7. Regla de decisión sobre rechazo de revisión (menor, decidido)

El brief no especifica a qué estado exacto vuelve un pedido rechazado en revisión. Decisión: vuelve a `PREPARANDO` (no a `PENDIENTE`, para no perder lo ya preparado) y queda visible de nuevo en la bandeja del preparador original con la observación del revisor. Es una decisión menor reversible sin impacto arquitectónico.
