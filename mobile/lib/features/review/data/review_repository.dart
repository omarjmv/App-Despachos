import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers/core_providers.dart';
import '../../orders/domain/order.dart';

final reviewRepositoryProvider = Provider((ref) => ReviewRepository(ref.watch(apiClientProvider)));

final pendingReviewsProvider = FutureProvider.autoDispose((ref) {
  return ref.watch(reviewRepositoryProvider).pending();
});

class ReviewRepository {
  ReviewRepository(this._api);

  final ApiClient _api;

  Future<List<Order>> pending() async {
    final response = await _api.get('/reviews/pending');
    final items = (response.data as Map<String, dynamic>)['data'] as List;
    return items.map((e) => Order.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> approve(int orderId) => _api.post('/orders/$orderId/review/approve');

  Future<void> reject(int orderId, String reason) =>
      _api.post('/orders/$orderId/review/reject', data: {'reason': reason});
}
