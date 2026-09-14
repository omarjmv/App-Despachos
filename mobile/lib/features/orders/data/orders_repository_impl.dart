import '../../../core/network/api_client.dart';
import '../domain/order.dart';
import '../domain/orders_repository.dart';

class OrdersRepositoryImpl implements OrdersRepository {
  OrdersRepositoryImpl(this._api);

  final ApiClient _api;

  @override
  Future<List<Order>> list({String? status}) async {
    final response = await _api.get('/orders', query: {
      if (status != null) 'status': status,
    });
    final items = (response.data as Map<String, dynamic>)['data'] as List;
    return items.map((e) => Order.fromJson(e as Map<String, dynamic>)).toList();
  }

  @override
  Future<Order> get(int id) async {
    final response = await _api.get('/orders/$id');
    return Order.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  @override
  Future<Order> create({
    required int customerId,
    required String orderDate,
    required List<Map<String, dynamic>> items,
  }) async {
    final response = await _api.post('/orders', data: {
      'customer_id': customerId,
      'order_date': orderDate,
      'items': items,
    });
    return Order.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  @override
  Future<Order> assign(int orderId, int preparerId) async {
    final response = await _api.post('/orders/$orderId/assign', data: {'preparer_id': preparerId});
    return Order.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  @override
  Future<Order> cancel(int orderId, String reason) async {
    final response = await _api.post('/orders/$orderId/cancel', data: {'reason': reason});
    return Order.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }
}
