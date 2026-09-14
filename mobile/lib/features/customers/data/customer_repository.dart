import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers/core_providers.dart';
import '../domain/customer.dart';

final customerRepositoryProvider = Provider((ref) => CustomerRepository(ref.watch(apiClientProvider)));

final customersProvider = FutureProvider.autoDispose((ref) {
  return ref.watch(customerRepositoryProvider).list();
});

class CustomerRepository {
  CustomerRepository(this._api);

  final ApiClient _api;

  Future<List<Customer>> list() async {
    final response = await _api.get('/customers', query: {'per_page': 100});
    final items = (response.data as Map<String, dynamic>)['data'] as List;
    return items.map((e) => Customer.fromJson(e as Map<String, dynamic>)).toList();
  }
}
