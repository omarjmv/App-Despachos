import 'dart:io';

import 'package:drift/drift.dart';
import 'package:drift/native.dart';
import 'package:meta/meta.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

part 'app_database.g.dart';

/// Entregas registradas por el motorista sin conexión, pendientes de
/// sincronizar (Documento 4 §4: LOCAL DATABASE + SYNC QUEUE + SERVER).
///
/// El [clientUuid] es la clave de idempotencia: el mismo valor viaja al
/// servidor en cada intento de sincronización, así que reintentar nunca
/// crea una entrega duplicada (regla 21 del brief).
class PendingDeliveries extends Table {
  IntColumn get id => integer().autoIncrement()();
  TextColumn get clientUuid => text().unique()();
  IntColumn get routeStopId => integer()();
  TextColumn get customerName => text()();
  TextColumn get itemsJson => text()();
  TextColumn get notes => text().nullable()();
  RealColumn get latitude => real().nullable()();
  RealColumn get longitude => real().nullable()();
  TextColumn get deliveredAt => text()();
  TextColumn get signaturePath => text().nullable()();
  TextColumn get photoPath => text().nullable()();
  // pending | synced | error
  TextColumn get status => text().withDefault(const Constant('pending'))();
  TextColumn get errorMessage => text().nullable()();
  DateTimeColumn get createdAt => dateTime().withDefault(currentDateAndTime)();
}

@DriftDatabase(tables: [PendingDeliveries])
class AppDatabase extends _$AppDatabase {
  AppDatabase() : super(_openConnection());

  @visibleForTesting
  AppDatabase.forTesting(super.executor);

  @override
  int get schemaVersion => 1;

  static QueryExecutor _openConnection() {
    return LazyDatabase(() async {
      final dir = await getApplicationDocumentsDirectory();
      final file = File(p.join(dir.path, 'despachos_offline.sqlite'));
      return NativeDatabase.createInBackground(file);
    });
  }
}
