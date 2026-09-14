import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers/core_providers.dart';
import '../domain/vehicle.dart';

final dispatchRepositoryProvider = Provider((ref) => DispatchRepository(ref.watch(apiClientProvider)));

final vehiclesProvider = FutureProvider.autoDispose((ref) => ref.watch(dispatchRepositoryProvider).vehicles());

final driversProvider = FutureProvider.autoDispose((ref) => ref.watch(dispatchRepositoryProvider).drivers());

class DispatchRepository {
  DispatchRepository(this._api);

  final ApiClient _api;

  Future<void> create(int orderId, {int? vehicleId, int? driverId}) {
    return _api.post('/orders/$orderId/dispatch', data: {
      if (vehicleId != null) 'vehicle_id': vehicleId,
      if (driverId != null) 'driver_id': driverId,
    });
  }

  Future<List<Vehicle>> vehicles() async {
    final response = await _api.get('/vehicles');
    final items = (response.data as Map<String, dynamic>)['data'] as List;
    return items.map((e) => Vehicle.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<List<UserOption>> drivers() async {
    final response = await _api.get('/users', query: {'role': 'MOTORISTA'});
    final items = (response.data as Map<String, dynamic>)['data'] as List;
    return items.map((e) => UserOption.fromJson(e as Map<String, dynamic>)).toList();
  }
}
