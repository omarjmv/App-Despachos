import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final connectivityServiceProvider = Provider((ref) => ConnectivityService());

/// true cuando hay red, false cuando no — para disparar una sincronización
/// automática apenas el dispositivo recupera conexión.
final connectivityOnlineStreamProvider = StreamProvider<bool>((ref) {
  return ref.watch(connectivityServiceProvider).onStatusChange;
});

/// Punto único para saber si hay red antes de decidir entre enviar al
/// servidor o encolar localmente (Documento 4 §4).
class ConnectivityService {
  final _connectivity = Connectivity();

  Future<bool> isOnline() async {
    final results = await _connectivity.checkConnectivity();
    return results.any((r) => r != ConnectivityResult.none);
  }

  Stream<bool> get onStatusChange => _connectivity.onConnectivityChanged.map(
        (results) => results.any((r) => r != ConnectivityResult.none),
      );
}
