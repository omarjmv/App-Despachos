import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

import '../../../core/error/app_exception.dart';
import '../../../core/error/failure.dart';
import '../../../core/network/connectivity_service.dart';
import '../domain/delivery.dart';
import 'delivery_repository.dart';
import 'delivery_sync_service.dart';
import 'offline_delivery_queue.dart';

final deliveryCoordinatorProvider = Provider((ref) {
  return DeliveryCoordinator(
    ref.watch(deliveryRepositoryProvider),
    ref.watch(offlineDeliveryQueueProvider),
    ref.watch(connectivityServiceProvider),
    ref.watch(deliverySyncServiceProvider),
  );
});

/// Resultado de intentar registrar una entrega: o bien quedó creada en el
/// servidor de inmediato, o quedó guardada localmente para sincronizarse
/// después (Documento 4 §4).
class DeliverySubmission {
  const DeliverySubmission.synced(this.delivery) : queuedOffline = false;
  const DeliverySubmission.queued()
      : delivery = null,
        queuedOffline = true;

  final Delivery? delivery;
  final bool queuedOffline;
}

/// Decide entre enviar al servidor de inmediato o encolar localmente,
/// según haya o no conexión — el resto de la app (la pantalla de
/// entrega) no necesita saber cuál de las dos rutas se tomó.
class DeliveryCoordinator {
  DeliveryCoordinator(this._repository, this._queue, this._connectivity, this._sync);

  final DeliveryRepository _repository;
  final OfflineDeliveryQueue _queue;
  final ConnectivityService _connectivity;
  final DeliverySyncService _sync;

  Future<DeliverySubmission> submit({
    required int stopId,
    required String customerName,
    required String clientUuid,
    required List<DeliveryItemPayload> items,
    String? notes,
    double? latitude,
    double? longitude,
    File? signatureFile,
    File? photoFile,
  }) async {
    final deliveredAt = DateTime.now().toIso8601String();

    if (await _connectivity.isOnline()) {
      try {
        final delivery = await _repository.submit(
          stopId: stopId,
          clientUuid: clientUuid,
          items: items,
          notes: notes,
          latitude: latitude,
          longitude: longitude,
        );

        if (signatureFile != null) {
          await _repository.uploadEvidence(deliveryId: delivery.id, type: 'FIRMA', file: signatureFile);
        }
        if (photoFile != null) {
          await _repository.uploadEvidence(deliveryId: delivery.id, type: 'FOTO', file: photoFile);
        }

        return DeliverySubmission.synced(delivery);
      } on AppException catch (e) {
        if (e.failure is! NetworkFailure) rethrow;
        // La red se cortó justo durante el envío: cae al mismo camino
        // offline de abajo en vez de perder el registro.
      }
    }

    await _enqueueOffline(
      stopId: stopId,
      customerName: customerName,
      clientUuid: clientUuid,
      items: items,
      deliveredAt: deliveredAt,
      notes: notes,
      latitude: latitude,
      longitude: longitude,
      signatureFile: signatureFile,
      photoFile: photoFile,
    );

    return const DeliverySubmission.queued();
  }

  Future<void> _enqueueOffline({
    required int stopId,
    required String customerName,
    required String clientUuid,
    required List<DeliveryItemPayload> items,
    required String deliveredAt,
    String? notes,
    double? latitude,
    double? longitude,
    File? signatureFile,
    File? photoFile,
  }) async {
    // Copia la firma/foto a un directorio estable: los archivos temporales
    // de la cámara o del canvas de firma pueden borrarse antes de que
    // llegue la conexión para sincronizar.
    final signaturePath = signatureFile != null
        ? await _persist(signatureFile, 'firma_$clientUuid.png')
        : null;
    final photoPath = photoFile != null ? await _persist(photoFile, 'foto_$clientUuid.jpg') : null;

    await _queue.enqueue(
      clientUuid: clientUuid,
      routeStopId: stopId,
      customerName: customerName,
      items: items,
      deliveredAt: deliveredAt,
      notes: notes,
      latitude: latitude,
      longitude: longitude,
      signaturePath: signaturePath,
      photoPath: photoPath,
    );
  }

  Future<String> _persist(File source, String fileName) async {
    final dir = await getApplicationSupportDirectory();
    final pendingDir = Directory(p.join(dir.path, 'pending_evidence'))..createSync(recursive: true);
    final destination = p.join(pendingDir.path, fileName);
    await source.copy(destination);
    return destination;
  }

  /// Se puede llamar sin riesgo desde cualquier pantalla (por ejemplo al
  /// recuperar conexión o al entrar a "Mi ruta"): si no hay nada
  /// pendiente o no hay red, simplemente no hace nada.
  Future<void> trySyncPending() => _sync.syncPending();
}
