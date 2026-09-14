import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers/core_providers.dart';
import '../domain/preparation.dart';

final preparationRepositoryProvider = Provider((ref) => PreparationRepository(ref.watch(apiClientProvider)));

final preparationProvider = FutureProvider.autoDispose.family<Preparation, int>((ref, orderId) {
  return ref.watch(preparationRepositoryProvider).get(orderId);
});

class PreparationRepository {
  PreparationRepository(this._api);

  final ApiClient _api;

  Future<Preparation> get(int orderId) async {
    final response = await _api.get('/orders/$orderId/preparation');
    return Preparation.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  Future<PreparationItem> registerItem(
    int orderId, {
    required int orderItemId,
    required double quantityPrepared,
    String? differenceReason,
    String? differenceNotes,
  }) async {
    final response = await _api.post('/orders/$orderId/preparation/items', data: {
      'order_item_id': orderItemId,
      'quantity_prepared': quantityPrepared,
      if (differenceReason != null) 'difference_reason': differenceReason,
      if (differenceNotes != null) 'difference_notes': differenceNotes,
    });
    return PreparationItem.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  Future<PreparationItem> scan(int orderId, String barcode) async {
    final response = await _api.post('/orders/$orderId/preparation/scan', data: {'barcode': barcode});
    return PreparationItem.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  Future<Preparation> finish(int orderId) async {
    final response = await _api.post('/orders/$orderId/preparation/finish');
    return Preparation.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }
}
