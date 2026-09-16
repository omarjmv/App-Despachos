import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../auth/presentation/auth_controller.dart';
import '../data/routes_repository.dart';

class RouteDetailScreen extends ConsumerWidget {
  const RouteDetailScreen({required this.routeId, super.key});

  final int routeId;

  Future<void> _start(BuildContext context, WidgetRef ref) async {
    try {
      await ref.read(routesRepositoryProvider).start(routeId);
      ref.invalidate(routeDetailProvider(routeId));
      ref.invalidate(myRouteProvider);
    } catch (e) {
      if (context.mounted) _showError(context, e);
    }
  }

  Future<void> _finish(BuildContext context, WidgetRef ref) async {
    try {
      await ref.read(routesRepositoryProvider).finish(routeId);
      ref.invalidate(routeDetailProvider(routeId));
      ref.invalidate(routesListProvider);
      ref.invalidate(myRouteProvider);
    } catch (e) {
      if (context.mounted) _showError(context, e);
    }
  }

  void _showError(BuildContext context, Object e) {
    final message = errorMessageOf(e);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final routeAsync = ref.watch(routeDetailProvider(routeId));
    final user = ref.watch(authControllerProvider).value;
    final isDriver = user?.role.name == 'motorista';

    return Scaffold(
      appBar: AppBar(title: const Text('Detalle de ruta')),
      body: AsyncValueView(
        value: routeAsync,
        onRetry: () => ref.invalidate(routeDetailProvider(routeId)),
        builder: (context, route) {
          final allCompleted = route.stops.isNotEmpty && route.stops.every((s) => s.status == 'COMPLETADA');

          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(route.code, style: Theme.of(context).textTheme.titleLarge),
                        Text('${route.vehicle?.plate ?? '-'} · ${route.driver?.name ?? '-'}'),
                      ],
                    ),
                    Chip(label: Text(route.status)),
                  ],
                ),
              ),
              const Divider(height: 1),
              Expanded(
                child: ListView.separated(
                  itemCount: route.stops.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final stop = route.stops[index];
                    return ListTile(
                      leading: CircleAvatar(child: Text('${stop.sequence}')),
                      title: Text(stop.dispatch.customer.name),
                      subtitle: Text('${stop.dispatch.number} · ${stop.dispatch.items.length} producto(s)'),
                      trailing: Text(stop.deliveryResult ?? stop.status),
                      onTap: isDriver && route.status == 'EN_CURSO' && stop.status != 'COMPLETADA'
                          ? () => context.push('/delivery/${stop.id}')
                          : null,
                    );
                  },
                ),
              ),
              if (!isDriver)
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    children: [
                      if (route.status == 'PLANIFICADA')
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: () => _start(context, ref),
                            icon: const Icon(Icons.play_arrow),
                            label: const Text('Iniciar ruta'),
                          ),
                        ),
                      if (route.status == 'EN_CURSO')
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: allCompleted ? () => _finish(context, ref) : null,
                            icon: const Icon(Icons.flag),
                            label: const Text('Finalizar ruta'),
                          ),
                        ),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
