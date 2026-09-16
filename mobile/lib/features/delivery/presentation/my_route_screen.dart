import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../routes/data/routes_repository.dart';

/// Home del motorista: solo ve su ruta activa y sus paradas
/// (Documento 3 §5 - nunca las de otro motorista).
class MyRouteScreen extends ConsumerWidget {
  const MyRouteScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final routeAsync = ref.watch(myRouteProvider);

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
      body: RefreshIndicator(
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
    );
  }
}
