import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/error/failure.dart';
import '../../customers/data/customer_repository.dart';
import '../../customers/domain/customer.dart';
import '../../products/data/product_repository.dart';
import '../../products/domain/product.dart';
import 'orders_providers.dart';

class _DraftItem {
  _DraftItem({required this.product, required this.quantity});
  Product product;
  double quantity;
}

class CreateOrderScreen extends ConsumerStatefulWidget {
  const CreateOrderScreen({super.key});

  @override
  ConsumerState<CreateOrderScreen> createState() => _CreateOrderScreenState();
}

class _CreateOrderScreenState extends ConsumerState<CreateOrderScreen> {
  Customer? _customer;
  final List<_DraftItem> _items = [];
  bool _submitting = false;

  Future<void> _addItem(List<Product> products) async {
    Product? product = products.isNotEmpty ? products.first : null;
    final qtyController = TextEditingController(text: '1');

    final result = await showDialog<_DraftItem>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Agregar producto'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<Product>(
                initialValue: product,
                items: products
                    .map((p) => DropdownMenuItem(value: p, child: Text('${p.name} (${p.sku})')))
                    .toList(),
                onChanged: (value) => setDialogState(() => product = value),
                decoration: const InputDecoration(labelText: 'Producto'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: qtyController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Cantidad solicitada'),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancelar')),
            FilledButton(
              onPressed: () {
                final qty = double.tryParse(qtyController.text);
                if (product == null || qty == null || qty <= 0) return;
                Navigator.pop(context, _DraftItem(product: product!, quantity: qty));
              },
              child: const Text('Agregar'),
            ),
          ],
        ),
      ),
    );

    if (result != null) {
      setState(() => _items.add(result));
    }
  }

  Future<void> _submit() async {
    if (_customer == null || _items.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Seleccione un cliente y al menos un producto.')),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final order = await ref.read(ordersRepositoryProvider).create(
            customerId: _customer!.id,
            orderDate: DateTime.now().toIso8601String().split('T').first,
            items: _items
                .map((item) => {
                      'product_id': item.product.id,
                      'quantity_requested': item.quantity,
                      'unit': item.product.unit,
                    })
                .toList(),
          );

      ref.invalidate(ordersListProvider);
      if (!mounted) return;
      context.pushReplacement('/orders/${order.id}');
    } catch (e) {
      final message = e is Failure ? e.message : (e.toString());
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final customersAsync = ref.watch(customersProvider);
    final productsAsync = ref.watch(productsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Nuevo pedido')),
      body: customersAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('Error cargando clientes: $e')),
        data: (customers) => productsAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(child: Text('Error cargando productos: $e')),
          data: (products) => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              DropdownButtonFormField<Customer>(
                initialValue: _customer,
                items: customers
                    .map((c) => DropdownMenuItem(value: c, child: Text(c.name)))
                    .toList(),
                onChanged: (value) => setState(() => _customer = value),
                decoration: const InputDecoration(labelText: 'Cliente'),
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Productos', style: Theme.of(context).textTheme.titleMedium),
                  TextButton.icon(
                    onPressed: products.isEmpty ? null : () => _addItem(products),
                    icon: const Icon(Icons.add),
                    label: const Text('Agregar'),
                  ),
                ],
              ),
              ..._items.map((item) => ListTile(
                    title: Text(item.product.name),
                    subtitle: Text('${item.quantity} ${item.product.unit}'),
                    trailing: IconButton(
                      icon: const Icon(Icons.delete_outline),
                      onPressed: () => setState(() => _items.remove(item)),
                    ),
                  )),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: _submitting ? null : _submit,
                child: _submitting
                    ? const CircularProgressIndicator(color: Colors.white)
                    : const Text('Crear pedido'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
