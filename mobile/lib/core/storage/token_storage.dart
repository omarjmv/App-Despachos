import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Guarda el token de Sanctum en almacenamiento seguro del dispositivo
/// (Keystore/Keychain), nunca en SharedPreferences ni en el código.
class TokenStorage {
  TokenStorage(this._storage);

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'auth_token';

  Future<void> save(String token) => _storage.write(key: _tokenKey, value: token);

  Future<String?> read() => _storage.read(key: _tokenKey);

  Future<void> clear() => _storage.delete(key: _tokenKey);
}
