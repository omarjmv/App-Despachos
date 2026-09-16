import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/error/failure.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../orders/presentation/orders_providers.dart';
import '../data/review_repository.dart';

class ReviewListScreen extends ConsumerWidget {
  const ReviewListScreen({super.key});

  Future<void> _approve(BuildContext context, WidgetRef ref, int orderId) async {
    try {
      await ref.read(reviewRepositoryProvider).approve(orderId);
      ref.invalidate(pendingReviewsProvider);
      ref.invalidate(ordersListProvider);
    } catch (e) {
      if (!context.mounted) return;
      _showError(context, e);
    }
  }

  Future<void> _reject(BuildContext context, WidgetRef ref, int orderId) async {
    final controller = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Rechazar despacho'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(labelText: 'Motivo del rechazo'),
          autofocus: true,
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancelar')),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text),
            child: const Text('Rechazar'),
          ),
        ],
      ),
    );

    if (reason == null || reason.trim().isEmpty) return;

    try {
      await ref.read(reviewRepositoryProvider).reject(orderId, reason);
      ref.invalidate(pendingReviewsProvider);
      ref.invalidate(ordersListProvider);
    } catch (e) {
      if (!context.mounted) return;
      _showError(context, e);
    }
  }

  void _showError(BuildContext context, Object e) {
    final message = errorMessageOf(e);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final pendingAsync = ref.watch(pendingReviewsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Revisión pendiente')),
      body: AsyncValueView(
        value: pendingAsync,
        onRetry: () => ref.invalidate(pendingReviewsProvider),
        builder: (context, orders) {
          if (orders.isEmpty) {
            return const Center(child: Text('No hay pedidos pendientes de revisión.'));
          }
          return ListView.separated(
            itemCount: orders.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (context, index) {
              final order = orders[index];
              return ListTile(
                title: Text('${order.number} · ${order.customer.name}'),
                subtitle: Text('${order.items.length} producto(s)'),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    IconButton(
                      icon: const Icon(Icons.close, color: Colors.red),
                      tooltip: 'Rechazar',
                      onPressed: () => _reject(context, ref, order.id),
                    ),
                    IconButton(
                      icon: const Icon(Icons.check, color: Colors.green),
                      tooltip: 'Aprobar',
                      onPressed: () => _approve(context, ref, order.id),
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}
