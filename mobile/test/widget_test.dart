import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_secure_storage_platform_interface/flutter_secure_storage_platform_interface.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:despachos_app/core/providers/core_providers.dart';
import 'package:despachos_app/main.dart';

/// Fake in-memory: sin esto, FlutterSecureStorage intenta usar el
/// MethodChannel real, que no existe en el entorno de test.
class _FakeSecureStorage extends FlutterSecureStoragePlatform {
  final Map<String, String> _store = {};

  @override
  Future<void> write({
    required String key,
    required String value,
    required Map<String, String> options,
  }) async {
    _store[key] = value;
  }

  @override
  Future<String?> read({
    required String key,
    required Map<String, String> options,
  }) async =>
      _store[key];

  @override
  Future<void> delete({required String key, required Map<String, String> options}) async {
    _store.remove(key);
  }

  @override
  Future<bool> containsKey({required String key, required Map<String, String> options}) async =>
      _store.containsKey(key);

  @override
  Future<Map<String, String>> readAll({required Map<String, String> options}) async => _store;

  @override
  Future<void> deleteAll({required Map<String, String> options}) async => _store.clear();
}

void main() {
  testWidgets('sin sesión guardada, la app muestra la pantalla de login', (tester) async {
    FlutterSecureStoragePlatform.instance = _FakeSecureStorage();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [secureStorageProvider.overrideWithValue(const FlutterSecureStorage())],
        child: const DespachosApp(),
      ),
    );

    // Deja resolver el splash (chequeo de sesión) y la navegación a /login.
    await tester.pumpAndSettle();

    expect(find.text('Control de Despachos'), findsOneWidget);
    expect(find.widgetWithText(ElevatedButton, 'Ingresar'), findsOneWidget);
  });
}
