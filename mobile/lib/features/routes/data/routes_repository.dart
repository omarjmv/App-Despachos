import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers/core_providers.dart';
import '../domain/route_model.dart';
import '../domain/route_stop.dart';

final routesRepositoryProvider = Provider((ref) => RoutesRepository(ref.watch(apiClientProvider)));

final myRouteProvider = FutureProvider.autoDispose<RouteModel?>((ref) {
  return ref.watch(routesRepositoryProvider).mine();
});

final routesListProvider = FutureProvider.autoDispose<List<RouteModel>>((ref) {
  return ref.watch(routesRepositoryProvider).list();
});

final routeDetailProvider = FutureProvider.autoDispose.family<RouteModel, int>((ref, routeId) {
  return ref.watch(routesRepositoryProvider).get(routeId);
});

/// Despachos que aún no pertenecen a ninguna ruta, candidatos para agrupar en una nueva.
final unroutedDispatchesProvider = FutureProvider.autoDispose<List<DispatchInfo>>((ref) async {
  final response = await ref.watch(apiClientProvider).get('/dispatches', query: {'unrouted': 1});
  final items = (response.data as Map<String, dynamic>)['data'] as List;
  return items.map((e) => DispatchInfo.fromJson(e as Map<String, dynamic>)).toList();
});

class RoutesRepository {
  RoutesRepository(this._api);

  final ApiClient _api;

  Future<RouteModel> create({
    required int vehicleId,
    required int driverId,
    required List<int> dispatchIds,
  }) async {
    final response = await _api.post('/routes', data: {
      'vehicle_id': vehicleId,
      'driver_id': driverId,
      'dispatch_ids': dispatchIds,
    });
    return RouteModel.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  Future<RouteModel> start(int routeId) async {
    final response = await _api.post('/routes/$routeId/start');
    return RouteModel.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  Future<RouteModel> finish(int routeId) async {
    final response = await _api.post('/routes/$routeId/finish');
    return RouteModel.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  /// La ruta activa del motorista autenticado, o null si no tiene ninguna.
  Future<RouteModel?> mine() async {
    try {
      final response = await _api.get('/routes/mine');
      return RouteModel.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  Future<List<RouteModel>> list() async {
    final response = await _api.get('/routes');
    final items = (response.data as Map<String, dynamic>)['data'] as List;
    return items.map((e) => RouteModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<RouteModel> get(int routeId) async {
    final response = await _api.get('/routes/$routeId');
    return RouteModel.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }
}
