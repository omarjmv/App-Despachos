import 'package:despachos_app/core/storage/local/app_database.dart';
import 'package:despachos_app/features/delivery/data/offline_delivery_queue.dart';
import 'package:despachos_app/features/delivery/domain/delivery.dart';
import 'package:drift/native.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late AppDatabase db;
  late OfflineDeliveryQueue queue;

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    queue = OfflineDeliveryQueue(db);
  });

  tearDown(() => db.close());

  test('enqueue stores a delivery that shows up as pending', () async {
    await queue.enqueue(
      clientUuid: 'uuid-1',
      routeStopId: 5,
      customerName: 'Cliente Uno',
      items: [DeliveryItemPayload(dispatchItemId: 1, quantityDelivered: 10)],
      deliveredAt: DateTime.now().toIso8601String(),
    );

    final pending = await queue.pending();

    expect(pending, hasLength(1));
    expect(pending.first.clientUuid, 'uuid-1');
    expect(pending.first.status, 'pending');
  });

  test('markSynced removes the item from the pending list', () async {
    await queue.enqueue(
      clientUuid: 'uuid-2',
      routeStopId: 7,
      customerName: 'Cliente Dos',
      items: [DeliveryItemPayload(dispatchItemId: 2, quantityDelivered: 5)],
      deliveredAt: DateTime.now().toIso8601String(),
    );

    final [pending] = await queue.pending();
    await queue.markSynced(pending.id);

    expect(await queue.pending(), isEmpty);
  });

  test('markError keeps the item out of pending but records the message', () async {
    await queue.enqueue(
      clientUuid: 'uuid-3',
      routeStopId: 9,
      customerName: 'Cliente Tres',
      items: [DeliveryItemPayload(dispatchItemId: 3, quantityDelivered: 1)],
      deliveredAt: DateTime.now().toIso8601String(),
    );

    final [pending] = await queue.pending();
    await queue.markError(pending.id, 'No autorizado');

    expect(await queue.pending(), isEmpty);
  });

  test('a second enqueue with the same client_uuid is rejected (unique constraint)', () async {
    Future<void> insert() => queue.enqueue(
          clientUuid: 'uuid-dup',
          routeStopId: 1,
          customerName: 'Cliente',
          items: [DeliveryItemPayload(dispatchItemId: 1, quantityDelivered: 1)],
          deliveredAt: DateTime.now().toIso8601String(),
        );

    await insert();

    expect(insert(), throwsA(anything));
  });
}
