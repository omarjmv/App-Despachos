import 'dart:io';

import '../../../core/network/api_client.dart';
import '../../../core/storage/token_storage.dart';
import '../domain/auth_repository.dart';
import '../domain/user.dart';

class AuthRepositoryImpl implements AuthRepository {
  AuthRepositoryImpl(this._api, this._tokenStorage);

  final ApiClient _api;
  final TokenStorage _tokenStorage;

  @override
  Future<AppUser> login({required String email, required String password}) async {
    final response = await _api.post('/auth/login', data: {
      'email': email,
      'password': password,
      'device_name': Platform.isAndroid ? 'android' : 'app',
    });

    final data = response.data as Map<String, dynamic>;
    await _tokenStorage.save(data['token'] as String);

    return AppUser.fromJson(data['user'] as Map<String, dynamic>);
  }

  @override
  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } finally {
      await _tokenStorage.clear();
    }
  }

  @override
  Future<AppUser?> currentUser() async {
    if (!await hasSession()) return null;

    final response = await _api.get('/auth/me');
    return AppUser.fromJson((response.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  @override
  Future<bool> hasSession() async => (await _tokenStorage.read()) != null;
}
