import 'order.dart';

abstract class OrdersRepository {
  Future<List<Order>> list({String? status});
  Future<Order> get(int id);
  Future<Order> create({
    required int customerId,
    required String orderDate,
    required List<Map<String, dynamic>> items,
  });
  Future<Order> assign(int orderId, int preparerId);
  Future<Order> cancel(int orderId, String reason);
}
