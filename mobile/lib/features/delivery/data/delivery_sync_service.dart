import 'dart:convert';
import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/connectivity_service.dart';
import '../../../core/providers/core_providers.dart';
import '../../../core/storage/local/app_database.dart';
import 'delivery_repository.dart';
import 'offline_delivery_queue.dart';

final deliverySyncServiceProvider = Provider((ref) {
  return DeliverySyncService(
    ref.watch(apiClientProvider),
    ref.watch(deliveryRepositoryProvider),
    ref.watch(offlineDeliveryQueueProvider),
    ref.watch(connectivityServiceProvider),
  );
});

/// Sube en lote lo que el motorista registró sin conexión
/// (Documento 4 §4: LOCAL -> SYNC -> SERVER), usando el mismo
/// client_uuid con el que se guardó localmente, así que un reintento
/// —incluso a medio subir evidencia— nunca duplica la entrega en el
/// servidor (regla 21).
class DeliverySyncService {
  DeliverySyncService(this._api, this._deliveries, this._queue, this._connectivity);

  final ApiClient _api;
  final DeliveryRepository _deliveries;
  final OfflineDeliveryQueue _queue;
  final ConnectivityService _connectivity;

  bool _running = false;

  /// Intenta sincronizar todo lo pendiente. Seguro de llamar varias veces
  /// seguidas (por ejemplo, en cada cambio de conectividad): si ya hay una
  /// sincronización en curso, la llamada no hace nada.
  Future<void> syncPending() async {
    if (_running) return;
    if (!await _connectivity.isOnline()) return;

    final pending = await _queue.pending();
    if (pending.isEmpty) return;

    _running = true;
    try {
      final response = await _api.post('/sync/deliveries/batch', data: {
        'operations': pending.map((p) => {
              'route_stop_id': p.routeStopId,
              'client_uuid': p.clientUuid,
              'notes': p.notes,
              'latitude': p.latitude,
              'longitude': p.longitude,
              'delivered_at': p.deliveredAt,
              'items': jsonDecode(p.itemsJson),
            }).toList(),
      });

      final results = (response.data as Map<String, dynamic>)['results'] as List;

      for (final result in results) {
        final map = result as Map<String, dynamic>;
        final match = pending.firstWhere((p) => p.clientUuid == map['client_uuid']);

        if (map['status'] == 'creada' || map['status'] == 'ya_sincronizada') {
          final deliveryId = map['delivery_id'] as int?;
          if (deliveryId != null) {
            await _uploadEvidence(deliveryId, match);
          }
          await _queue.delete(match.id);
        } else {
          await _queue.markError(match.id, map['message']?.toString() ?? 'Error desconocido');
        }
      }
    } catch (_) {
      // Falla de red a mitad de la sincronización: se reintenta en el
      // próximo cambio de conectividad, las filas quedan como 'pending'.
    } finally {
      _running = false;
    }
  }

  Future<void> _uploadEvidence(int deliveryId, PendingDelivery pending) async {
    try {
      if (pending.signaturePath != null && File(pending.signaturePath!).existsSync()) {
        await _deliveries.uploadEvidence(
          deliveryId: deliveryId,
          type: 'FIRMA',
          file: File(pending.signaturePath!),
        );
      }
      if (pending.photoPath != null && File(pending.photoPath!).existsSync()) {
        await _deliveries.uploadEvidence(
          deliveryId: deliveryId,
          type: 'FOTO',
          file: File(pending.photoPath!),
        );
      }
    } catch (_) {
      // La entrega ya quedó registrada; si la evidencia falla se pierde
      // esa foto/firma puntual, pero no bloquea el resto del lote.
    }
  }
}
