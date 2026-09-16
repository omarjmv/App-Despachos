import 'dart:convert';

import 'package:drift/drift.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../../../core/storage/local/app_database.dart';
import '../domain/delivery.dart';

final offlineDeliveryQueueProvider = Provider((ref) => OfflineDeliveryQueue(ref.watch(appDatabaseProvider)));

/// Para mostrarle al motorista cuántas entregas locales faltan por subir.
final pendingDeliveriesProvider = StreamProvider<List<PendingDelivery>>((ref) {
  return ref.watch(offlineDeliveryQueueProvider).watchPending();
});

/// Cola local de entregas registradas sin conexión (Documento 4 §4).
/// Cada fila sobrevive un reinicio de la app hasta que se sincroniza.
class OfflineDeliveryQueue {
  OfflineDeliveryQueue(this._db);

  final AppDatabase _db;

  Future<void> enqueue({
    required String clientUuid,
    required int routeStopId,
    required String customerName,
    required List<DeliveryItemPayload> items,
    required String deliveredAt,
    String? notes,
    double? latitude,
    double? longitude,
    String? signaturePath,
    String? photoPath,
  }) {
    return _db.into(_db.pendingDeliveries).insert(
          PendingDeliveriesCompanion.insert(
            clientUuid: clientUuid,
            routeStopId: routeStopId,
            customerName: customerName,
            itemsJson: jsonEncode(items.map((e) => e.toJson()).toList()),
            deliveredAt: deliveredAt,
            notes: Value(notes),
            latitude: Value(latitude),
            longitude: Value(longitude),
            signaturePath: Value(signaturePath),
            photoPath: Value(photoPath),
          ),
        );
  }

  Future<List<PendingDelivery>> pending() {
    return (_db.select(_db.pendingDeliveries)..where((t) => t.status.equals('pending'))).get();
  }

  Stream<List<PendingDelivery>> watchPending() {
    return (_db.select(_db.pendingDeliveries)..where((t) => t.status.equals('pending'))).watch();
  }

  Future<void> markSynced(int id) {
    return (_db.update(_db.pendingDeliveries)..where((t) => t.id.equals(id))).write(
      const PendingDeliveriesCompanion(status: Value('synced')),
    );
  }

  Future<void> markError(int id, String message) {
    return (_db.update(_db.pendingDeliveries)..where((t) => t.id.equals(id))).write(
      PendingDeliveriesCompanion(status: const Value('error'), errorMessage: Value(message)),
    );
  }

  Future<void> delete(int id) {
    return (_db.delete(_db.pendingDeliveries)..where((t) => t.id.equals(id))).go();
  }
}
