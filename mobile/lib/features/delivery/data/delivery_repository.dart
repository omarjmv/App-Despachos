import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/providers/core_providers.dart';
import '../domain/delivery.dart';

final deliveryRepositoryProvider = Provider((ref) => DeliveryRepository(ref.watch(apiClientProvider)));

class DeliveryRepository {
  DeliveryRepository(this._api);

  final ApiClient _api;

  Future<void> startStop(int stopId) => _api.post('/route-stops/$stopId/start');

  /// Idempotente por [clientUuid]: reintentar con el mismo valor nunca
  /// duplica la entrega (regla 21 y Documento 4, modo offline).
  Future<Delivery> submit({
    required int stopId,
    required String clientUuid,
    required List<DeliveryItemPayload> items,
    String? notes,
    double? latitude,
    double? longitude,
  }) async {
    final response = await _api.post('/route-stops/$stopId/deliveries', data: {
      'client_uuid': clientUuid,
      'notes': notes,
      'latitude': latitude,
      'longitude': longitude,
      'delivered_at': DateTime.now().toIso8601String(),
      'items': items.map((e) => e.toJson()).toList(),
    });
    return Delivery.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  Future<void> uploadEvidence({
    required int deliveryId,
    required String type,
    required File file,
  }) async {
    final formData = FormData.fromMap({
      'type': type,
      'file': await MultipartFile.fromFile(file.path),
    });
    await _api.post('/deliveries/$deliveryId/evidence', data: formData);
  }
}
