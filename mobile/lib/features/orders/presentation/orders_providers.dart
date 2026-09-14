import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';

import '../../../core/providers/core_providers.dart';
import '../../dispatch/domain/vehicle.dart';
import '../data/orders_repository_impl.dart';
import '../domain/order.dart';
import '../domain/orders_repository.dart';

final ordersRepositoryProvider = Provider<OrdersRepository>((ref) {
  return OrdersRepositoryImpl(ref.watch(apiClientProvider));
});

/// Filtro de estado seleccionado en la bandeja de pedidos (null = todos).
final orderStatusFilterProvider = StateProvider.autoDispose<String?>((ref) => null);

final ordersListProvider = FutureProvider.autoDispose<List<Order>>((ref) {
  final status = ref.watch(orderStatusFilterProvider);
  return ref.watch(ordersRepositoryProvider).list(status: status);
});

final orderDetailProvider = FutureProvider.autoDispose.family<Order, int>((ref, orderId) {
  return ref.watch(ordersRepositoryProvider).get(orderId);
});

/// Preparadores disponibles para asignar un pedido (Documento 3 §2).
final preparersProvider = FutureProvider.autoDispose<List<UserOption>>((ref) async {
  final response = await ref.watch(apiClientProvider).get('/users', query: {'role': 'PREPARADOR'});
  final items = (response.data as Map<String, dynamic>)['data'] as List;
  return items.map((e) => UserOption.fromJson(e as Map<String, dynamic>)).toList();
});
