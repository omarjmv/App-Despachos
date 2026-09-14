import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../../core/widgets/async_value_view.dart';
import '../../../core/widgets/status_badge.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../dispatch/data/dispatch_repository.dart';
import '../../dispatch/domain/vehicle.dart';
import 'orders_providers.dart';

class OrderDetailScreen extends ConsumerWidget {
  const OrderDetailScreen({required this.orderId, super.key});

  final int orderId;

  Future<void> _assign(BuildContext context, WidgetRef ref) async {
    final preparers = await ref.read(preparersProvider.future);
    if (!context.mounted) return;

    if (preparers.isEmpty) {
      ScaffoldMessenger.of(context)
          .showSnackBar(const SnackBar(content: Text('No hay preparadores disponibles.')));
      return;
    }

    UserOption selected = preparers.first;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setState) => AlertDialog(
          title: const Text('Asignar preparador'),
          content: DropdownButtonFormField<UserOption>(
            initialValue: selected,
            items: preparers.map((p) => DropdownMenuItem(value: p, child: Text(p.name))).toList(),
            onChanged: (value) => setState(() => selected = value!),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancelar')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Asignar')),
          ],
        ),
      ),
    );

    if (confirmed != true) return;

    try {
      await ref.read(ordersRepositoryProvider).assign(orderId, selected.id);
      ref.invalidate(orderDetailProvider(orderId));
      ref.invalidate(ordersListProvider);
    } catch (e) {
      if (context.mounted) _showError(context, e);
    }
  }

  Future<void> _dispatch(BuildContext context, WidgetRef ref) async {
    final vehicles = await ref.read(vehiclesProvider.future);
    final drivers = await ref.read(driversProvider.future);
    if (!context.mounted) return;

    Vehicle? vehicle = vehicles.isNotEmpty ? vehicles.first : null;
    UserOption? driver = drivers.isNotEmpty ? drivers.first : null;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setState) => AlertDialog(
          title: const Text('Crear despacho'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<Vehicle>(
                initialValue: vehicle,
                items: vehicles.map((v) => DropdownMenuItem(value: v, child: Text(v.plate))).toList(),
                onChanged: (value) => setState(() => vehicle = value),
                decoration: const InputDecoration(labelText: 'Vehículo'),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<UserOption>(
                initialValue: driver,
                items: drivers.map((d) => DropdownMenuItem(value: d, child: Text(d.name))).toList(),
                onChanged: (value) => setState(() => driver = value),
                decoration: const InputDecoration(labelText: 'Motorista'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancelar')),
            FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Despachar')),
          ],
        ),
      ),
    );

    if (confirmed != true) return;

    try {
      await ref.read(dispatchRepositoryProvider).create(
            orderId,
            vehicleId: vehicle?.id,
            driverId: driver?.id,
          );
      ref.invalidate(orderDetailProvider(orderId));
      ref.invalidate(ordersListProvider);
    } catch (e) {
      if (context.mounted) _showError(context, e);
    }
  }

  void _showError(BuildContext context, Object e) {
    final message = e is Failure ? e.message : e.toString();
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orderAsync = ref.watch(orderDetailProvider(orderId));
    final user = ref.watch(authControllerProvider).value;

    return Scaffold(
      appBar: AppBar(title: const Text('Detalle del pedido')),
      body: AsyncValueView(
        value: orderAsync,
        onRetry: () => ref.invalidate(orderDetailProvider(orderId)),
        builder: (context, order) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(order.number, style: Theme.of(context).textTheme.titleLarge),
                  StatusBadge(status: order.status),
                ],
              ),
              const SizedBox(height: 4),
              Text(order.customer.name, style: Theme.of(context).textTheme.bodyLarge),
              Text('Fecha: ${order.orderDate}'),
              if (order.assignedToName != null) Text('Asignado a: ${order.assignedToName}'),
              const Divider(height: 32),
              Text('Productos', style: Theme.of(context).textTheme.titleMedium),
              ...order.items.map((item) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(item.product.name),
                    trailing: Text('${item.quantityRequested} ${item.unit}'),
                  )),
              const SizedBox(height: 24),
              if (order.status == 'PENDIENTE' && (user?.canCreateOrders ?? false))
                ElevatedButton.icon(
                  onPressed: () => _assign(context, ref),
                  icon: const Icon(Icons.assignment_ind),
                  label: const Text('Asignar preparador'),
                ),
              if (order.status == 'PREPARANDO' && (user?.canPrepare ?? false))
                ElevatedButton.icon(
                  onPressed: () => context.push('/orders/$orderId/preparation'),
                  icon: const Icon(Icons.inventory_2_outlined),
                  label: const Text('Ir a preparar'),
                ),
              if (order.status == 'APROBADO' && (user?.canDispatch ?? false))
                ElevatedButton.icon(
                  onPressed: () => _dispatch(context, ref),
                  icon: const Icon(Icons.local_shipping),
                  label: const Text('Crear despacho'),
                ),
            ],
          );
        },
      ),
    );
  }
}
