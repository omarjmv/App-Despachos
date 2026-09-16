import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../dispatch/data/dispatch_repository.dart';
import '../../dispatch/domain/vehicle.dart';
import '../data/routes_repository.dart';
import '../domain/route_stop.dart';

class CreateRouteScreen extends ConsumerStatefulWidget {
  const CreateRouteScreen({super.key});

  @override
  ConsumerState<CreateRouteScreen> createState() => _CreateRouteScreenState();
}

class _CreateRouteScreenState extends ConsumerState<CreateRouteScreen> {
  Vehicle? _vehicle;
  UserOption? _driver;
  final List<DispatchInfo> _selected = [];
  bool _submitting = false;

  Future<void> _submit() async {
    if (_vehicle == null || _driver == null || _selected.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Seleccione vehículo, motorista y al menos un despacho.')),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final route = await ref.read(routesRepositoryProvider).create(
            vehicleId: _vehicle!.id,
            driverId: _driver!.id,
            dispatchIds: _selected.map((d) => d.id).toList(),
          );
      ref.invalidate(routesListProvider);
      if (!mounted) return;
      context.pushReplacement('/routes/${route.id}');
    } catch (e) {
      final message = e is Failure ? e.message : e.toString();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final vehiclesAsync = ref.watch(vehiclesProvider);
    final driversAsync = ref.watch(driversProvider);
    final dispatchesAsync = ref.watch(unroutedDispatchesProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Nueva ruta')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          vehiclesAsync.when(
            loading: () => const LinearProgressIndicator(),
            error: (e, _) => Text('Error: $e'),
            data: (vehicles) => DropdownButtonFormField<Vehicle>(
              initialValue: _vehicle,
              items: vehicles.map((v) => DropdownMenuItem(value: v, child: Text(v.plate))).toList(),
              onChanged: (v) => setState(() => _vehicle = v),
              decoration: const InputDecoration(labelText: 'Vehículo'),
            ),
          ),
          const SizedBox(height: 16),
          driversAsync.when(
            loading: () => const LinearProgressIndicator(),
            error: (e, _) => Text('Error: $e'),
            data: (drivers) => DropdownButtonFormField<UserOption>(
              initialValue: _driver,
              items: drivers.map((d) => DropdownMenuItem(value: d, child: Text(d.name))).toList(),
              onChanged: (d) => setState(() => _driver = d),
              decoration: const InputDecoration(labelText: 'Motorista'),
            ),
          ),
          const SizedBox(height: 24),
          Text('Despachos a incluir (en orden de entrega)', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          dispatchesAsync.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Text('Error: $e'),
            data: (dispatches) {
              final available = dispatches.where((d) => !_selected.any((s) => s.id == d.id)).toList();
              if (dispatches.isEmpty) {
                return const Text('No hay despachos disponibles para agregar a una ruta.');
              }
              return Wrap(
                spacing: 8,
                children: available
                    .map((d) => ActionChip(
                          label: Text('${d.number} · ${d.customer.name}'),
                          onPressed: () => setState(() => _selected.add(d)),
                        ))
                    .toList(),
              );
            },
          ),
          const SizedBox(height: 16),
          if (_selected.isNotEmpty) ...[
            Text('Orden de entrega', style: Theme.of(context).textTheme.titleMedium),
            ReorderableListView(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              onReorder: (oldIndex, newIndex) {
                setState(() {
                  if (newIndex > oldIndex) newIndex--;
                  final item = _selected.removeAt(oldIndex);
                  _selected.insert(newIndex, item);
                });
              },
              children: [
                for (final d in _selected)
                  ListTile(
                    key: ValueKey(d.id),
                    leading: const Icon(Icons.drag_handle),
                    title: Text('${d.number} · ${d.customer.name}'),
                    trailing: IconButton(
                      icon: const Icon(Icons.close),
                      onPressed: () => setState(() => _selected.remove(d)),
                    ),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _submitting ? null : _submit,
            child: _submitting
                ? const CircularProgressIndicator(color: Colors.white)
                : const Text('Crear ruta'),
          ),
        ],
      ),
    );
  }
}
