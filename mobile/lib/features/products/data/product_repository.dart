import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers/core_providers.dart';
import '../domain/product.dart';

final productRepositoryProvider = Provider((ref) => ProductRepository(ref.watch(apiClientProvider)));

final productsProvider = FutureProvider.autoDispose((ref) {
  return ref.watch(productRepositoryProvider).list();
});

class ProductRepository {
  ProductRepository(this._api);

  final ApiClient _api;

  Future<List<Product>> list() async {
    final response = await _api.get('/products', query: {'per_page': 100});
    final items = (response.data as Map<String, dynamic>)['data'] as List;
    return items.map((e) => Product.fromJson(e as Map<String, dynamic>)).toList();
  }
}
