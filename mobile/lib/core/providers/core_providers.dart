import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../network/api_client.dart';
import '../storage/local/app_database.dart';
import '../storage/token_storage.dart';

final secureStorageProvider = Provider((ref) => const FlutterSecureStorage());

final tokenStorageProvider = Provider((ref) => TokenStorage(ref.watch(secureStorageProvider)));

/// El callback de 401 se conecta al AuthController en bootstrap.dart para
/// evitar una dependencia circular directa entre ApiClient y el feature auth.
final unauthorizedCallbackProvider = StateProvider<void Function()?>((ref) => null);

final apiClientProvider = Provider((ref) {
  return ApiClient(
    ref.watch(tokenStorageProvider),
    onUnauthorized: () => ref.read(unauthorizedCallbackProvider)?.call(),
  );
});

/// Una única instancia por toda la vida de la app: abrir/cerrar el
/// archivo sqlite repetidamente no tiene sentido para una cola local.
final appDatabaseProvider = Provider<AppDatabase>((ref) {
  final db = AppDatabase();
  ref.onDispose(db.close);
  return db;
});
