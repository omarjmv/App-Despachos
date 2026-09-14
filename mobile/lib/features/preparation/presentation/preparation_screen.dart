import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../orders/presentation/orders_providers.dart';
import '../data/preparation_repository.dart';
import '../domain/preparation.dart';

class PreparationScreen extends ConsumerWidget {
  const PreparationScreen({required this.orderId, super.key});

  final int orderId;

  Future<void> _registerQuantity(
    BuildContext context,
    WidgetRef ref,
    PreparationItem item,
  ) async {
    final qtyController = TextEditingController(
      text: item.quantityPrepared == 0 ? '' : item.quantityPrepared.toString(),
    );
    String? reason;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setState) => AlertDialog(
          title: Text(item.product.name),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('Solicitado: ${item.quantityRequested} ${item.product.unit}'),
              const SizedBox(height: 12),
              TextField(
                controller: qtyController,
                autofocus: true,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Cantidad preparada'),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: reason,
                items: differenceReasons
                    .map((r) => DropdownMenuItem(value: r, child: Text(r.replaceAll('_', ' '))))
                    .toList(),
                onChanged: (value) => setState(() => reason = value),
                decoration: const InputDecoration(labelText: 'Motivo si hay diferencia'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancelar')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Guardar')),
          ],
        ),
      ),
    );

    if (confirmed != true) return;
    final qty = double.tryParse(qtyController.text);
    if (qty == null || qty < 0) return;

    try {
      await ref.read(preparationRepositoryProvider).registerItem(
            orderId,
            orderItemId: item.orderItemId,
            quantityPrepared: qty,
            differenceReason: reason,
          );
      ref.invalidate(preparationProvider(orderId));
    } catch (e) {
      if (!context.mounted) return;
      final message = e is Failure ? e.message : e.toString();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  Future<void> _finish(BuildContext context, WidgetRef ref) async {
    try {
      await ref.read(preparationRepositoryProvider).finish(orderId);
      ref.invalidate(preparationProvider(orderId));
      ref.invalidate(orderDetailProvider(orderId));
      ref.invalidate(ordersListProvider);
      if (context.mounted) context.pop();
    } catch (e) {
      if (!context.mounted) return;
      final message = e is Failure ? e.message : e.toString();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final preparationAsync = ref.watch(preparationProvider(orderId));

    return Scaffold(
      appBar: AppBar(title: const Text('Preparación')),
      body: AsyncValueView(
        value: preparationAsync,
        onRetry: () => ref.invalidate(preparationProvider(orderId)),
        builder: (context, preparation) {
          final isFinished = preparation.status == 'FINALIZADA';

          return Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    _Metric(label: 'Solicitado', value: preparation.totalRequested),
                    _Metric(label: 'Preparado', value: preparation.totalPrepared),
                    _Metric(
                      label: 'Diferencia',
                      value: preparation.totalRequested - preparation.totalPrepared,
                      highlight: preparation.totalRequested != preparation.totalPrepared,
                    ),
                  ],
                ),
              ),
              const Divider(height: 1),
              Expanded(
                child: ListView.separated(
                  itemCount: preparation.items.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final item = preparation.items[index];
                    return ListTile(
                      leading: Icon(
                        item.isComplete ? Icons.check_circle : Icons.error_outline,
                        color: item.isComplete ? Colors.green : Colors.orange,
                      ),
                      title: Text(item.product.name),
                      subtitle: Text(
                        '${item.quantityPrepared} / ${item.quantityRequested} ${item.product.unit}'
                        '${item.differenceReason != null ? ' · ${item.differenceReason}' : ''}',
                      ),
                      trailing: isFinished ? null : const Icon(Icons.edit),
                      onTap: isFinished ? null : () => _registerQuantity(context, ref, item),
                    );
                  },
                ),
              ),
              if (!isFinished)
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: ElevatedButton.icon(
                    onPressed: () => _finish(context, ref),
                    icon: const Icon(Icons.check),
                    label: const Text('Finalizar preparación'),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value, this.highlight = false});

  final String label;
  final double value;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          value.toStringAsFixed(value.truncateToDouble() == value ? 0 : 2),
          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                color: highlight ? Colors.orange.shade800 : null,
                fontWeight: FontWeight.bold,
              ),
        ),
        Text(label, style: Theme.of(context).textTheme.bodySmall),
      ],
    );
  }
}
