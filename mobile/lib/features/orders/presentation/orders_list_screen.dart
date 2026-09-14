import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/status_badge.dart';
import '../../auth/presentation/auth_controller.dart';
import 'orders_providers.dart';

const _statusFilters = [
  null, 'PENDIENTE', 'PREPARANDO', 'PREPARADO', 'APROBADO', 'DESPACHADO',
];

class OrdersListScreen extends ConsumerWidget {
  const OrdersListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(ordersListProvider);
    final statusFilter = ref.watch(orderStatusFilterProvider);
    final user = ref.watch(authControllerProvider).value;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pedidos'),
        actions: [
          if (user?.canReview ?? false)
            IconButton(
              icon: const Icon(Icons.fact_check_outlined),
              tooltip: 'Revisión pendiente',
              onPressed: () => context.push('/review'),
            ),
          if (user?.canCreateOrders ?? false)
            IconButton(
              icon: const Icon(Icons.dashboard_outlined),
              tooltip: 'Dashboard',
              onPressed: () => context.push('/dashboard'),
            ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(authControllerProvider.notifier).logout(),
          ),
        ],
      ),
      body: Column(
        children: [
          SizedBox(
            height: 48,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              children: _statusFilters.map((status) {
                final selected = status == statusFilter;
                return Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: ChoiceChip(
                    label: Text(status ?? 'Todos'),
                    selected: selected,
                    onSelected: (_) => ref.read(orderStatusFilterProvider.notifier).state = status,
                  ),
                );
              }).toList(),
            ),
          ),
          Expanded(
            child: AsyncValueView(
              value: ordersAsync,
              onRetry: () => ref.invalidate(ordersListProvider),
              builder: (context, orders) {
                if (orders.isEmpty) {
                  return const Center(child: Text('No hay pedidos para mostrar.'));
                }
                return RefreshIndicator(
                  onRefresh: () => ref.refresh(ordersListProvider.future),
                  child: ListView.separated(
                    itemCount: orders.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (context, index) {
                      final order = orders[index];
                      return ListTile(
                        title: Text('${order.number} · ${order.customer.name}'),
                        subtitle: Text(
                          order.assignedToName != null
                              ? 'Asignado a ${order.assignedToName}'
                              : order.orderDate,
                        ),
                        trailing: StatusBadge(status: order.status),
                        onTap: () => context.push('/orders/${order.id}'),
                      );
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
      floatingActionButton: (user?.canCreateOrders ?? false)
          ? FloatingActionButton.extended(
              onPressed: () => context.push('/orders/new'),
              icon: const Icon(Icons.add),
              label: const Text('Nuevo pedido'),
            )
          : null,
    );
  }
}
