import 'user.dart';

abstract class AuthRepository {
  Future<AppUser> login({required String email, required String password});
  Future<void> logout();
  Future<AppUser?> currentUser();
  Future<bool> hasSession();
}
