import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../../../core/widgets/async_value_view.dart';
import '../domain/dashboard_summary.dart';

final dashboardSummaryProvider = FutureProvider.autoDispose<DashboardSummary>((ref) async {
  final response = await ref.watch(apiClientProvider).get('/dashboard/summary');
  return DashboardSummary.fromJson(response.data as Map<String, dynamic>);
});

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final summaryAsync = ref.watch(dashboardSummaryProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Dashboard')),
      body: AsyncValueView(
        value: summaryAsync,
        onRetry: () => ref.invalidate(dashboardSummaryProvider),
        builder: (context, summary) {
          final tiles = [
            ('Pedidos hoy', summary.pedidosHoy, Colors.blueGrey),
            ('Preparados', summary.preparados, Colors.indigo),
            ('En revisión', summary.enRevision, Colors.orange),
            ('Despachados', summary.despachados, Colors.purple),
            ('En ruta', summary.enRuta, Colors.deepPurple),
            ('Entregados', summary.entregados, Colors.green),
            ('Parciales', summary.parciales, Colors.amber),
            ('Cancelados', summary.cancelados, Colors.red),
          ];

          return GridView.count(
            padding: const EdgeInsets.all(16),
            crossAxisCount: 2,
            mainAxisSpacing: 16,
            crossAxisSpacing: 16,
            childAspectRatio: 1.4,
            children: tiles.map((tile) {
              return Card(
                color: (tile.$3 as Color).withValues(alpha: 0.1),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        '${tile.$2}',
                        style: Theme.of(context)
                            .textTheme
                            .headlineMedium
                            ?.copyWith(color: tile.$3, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 4),
                      Text(tile.$1, style: Theme.of(context).textTheme.bodyMedium),
                    ],
                  ),
                ),
              );
            }).toList(),
          );
        },
      ),
    );
  }
}
