import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/connectivity_service.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../routes/data/routes_repository.dart';
import '../data/delivery_coordinator.dart';
import '../data/offline_delivery_queue.dart';

/// Home del motorista: solo ve su ruta activa y sus paradas
/// (Documento 3 §5 - nunca las de otro motorista).
class MyRouteScreen extends ConsumerStatefulWidget {
  const MyRouteScreen({super.key});

  @override
  ConsumerState<MyRouteScreen> createState() => _MyRouteScreenState();
}

class _MyRouteScreenState extends ConsumerState<MyRouteScreen> {
  @override
  void initState() {
    super.initState();
    // Intento silencioso al abrir la pantalla: si hay red y algo
    // pendiente de una sesión offline anterior, se sube solo.
    Future.microtask(_trySync);
  }

  Future<void> _trySync() async {
    await ref.read(deliveryCoordinatorProvider).trySyncPending();
    ref.invalidate(myRouteProvider);
  }

  @override
  Widget build(BuildContext context) {
    final routeAsync = ref.watch(myRouteProvider);
    final pendingAsync = ref.watch(pendingDeliveriesProvider);

    // Sincroniza automáticamente en cuanto vuelve la conexión.
    ref.listen(connectivityOnlineStreamProvider, (previous, next) {
      if (next.value == true) _trySync();
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mi ruta'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
          ),
        ],
      ),
      body: Column(
        children: [
          pendingAsync.maybeWhen(
            data: (pending) => pending.isEmpty
                ? const SizedBox.shrink()
                : MaterialBanner(
                    backgroundColor: Colors.amber.shade100,
                    content: Text(
                      '${pending.length} entrega(s) guardadas en el dispositivo, pendientes de enviar.',
                    ),
                    leading: const Icon(Icons.cloud_off, color: Colors.orange),
                    actions: [
                      TextButton(onPressed: _trySync, child: const Text('Sincronizar ahora')),
                    ],
                  ),
            orElse: () => const SizedBox.shrink(),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => ref.refresh(myRouteProvider.future),
              child: AsyncValueView(
                value: routeAsync,
                onRetry: () => ref.invalidate(myRouteProvider),
                builder: (context, route) {
                  if (route == null) {
                    return ListView(
                      children: const [
                        Padding(
                          padding: EdgeInsets.all(32),
                          child: Center(child: Text('No tiene una ruta asignada por ahora.')),
                        ),
                      ],
                    );
                  }

                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(16),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(route.code, style: Theme.of(context).textTheme.titleLarge),
                            Chip(label: Text(route.status)),
                          ],
                        ),
                      ),
                      Expanded(
                        child: ListView.separated(
                          itemCount: route.stops.length,
                          separatorBuilder: (_, __) => const Divider(height: 1),
                          itemBuilder: (context, index) {
                            final stop = route.stops[index];
                            final done = stop.status == 'COMPLETADA';
                            return ListTile(
                              leading: CircleAvatar(
                                backgroundColor: done ? Colors.green : null,
                                child: Icon(done ? Icons.check : null, color: done ? Colors.white : null),
                              ),
                              title: Text(stop.dispatch.customer.name),
                              subtitle: Text('${stop.dispatch.number} · ${stop.dispatch.items.length} producto(s)'),
                              trailing: Text(stop.deliveryResult ?? stop.status),
                              enabled: route.status == 'EN_CURSO' && !done,
                              onTap: route.status == 'EN_CURSO' && !done
                                  ? () => context.push('/delivery/${stop.id}')
                                  : null,
                            );
                          },
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}
