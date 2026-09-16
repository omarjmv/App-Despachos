import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_value_view.dart';
import '../data/routes_repository.dart';

class RoutesListScreen extends ConsumerWidget {
  const RoutesListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final routesAsync = ref.watch(routesListProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Rutas')),
      body: AsyncValueView(
        value: routesAsync,
        onRetry: () => ref.invalidate(routesListProvider),
        builder: (context, routes) {
          if (routes.isEmpty) {
            return const Center(child: Text('No hay rutas creadas todavía.'));
          }
          return ListView.separated(
            itemCount: routes.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (context, index) {
              final route = routes[index];
              final completed = route.stops.where((s) => s.status == 'COMPLETADA').length;
              return ListTile(
                title: Text('${route.code} · ${route.vehicle?.plate ?? '-'}'),
                subtitle: Text('${route.driver?.name ?? '-'} · $completed/${route.stops.length} entregadas'),
                trailing: Chip(label: Text(route.status)),
                onTap: () => context.push('/routes/${route.id}'),
              );
            },
          );
        },
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => context.push('/routes/new'),
        icon: const Icon(Icons.add),
        label: const Text('Nueva ruta'),
      ),
    );
  }
}
