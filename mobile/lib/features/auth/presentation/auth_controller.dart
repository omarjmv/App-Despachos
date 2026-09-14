import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers/core_providers.dart';
import '../data/auth_repository_impl.dart';
import '../domain/auth_repository.dart';
import '../domain/user.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepositoryImpl(ref.watch(apiClientProvider), ref.watch(tokenStorageProvider));
});

/// Estado de sesión global: null = sin sesión, AppUser = autenticado.
/// Se consulta desde el router para decidir a qué pantalla ir.
final authControllerProvider = AsyncNotifierProvider<AuthController, AppUser?>(AuthController.new);

class AuthController extends AsyncNotifier<AppUser?> {
  @override
  Future<AppUser?> build() async {
    // Conecta el 401 global del ApiClient con el cierre de sesión. Se difiere
    // con un microtask porque Riverpod no permite que un provider escriba en
    // otro mientras todavía se está inicializando (este build()).
    Future.microtask(() {
      ref.read(unauthorizedCallbackProvider.notifier).state = forceLogout;
    });

    final repo = ref.read(authRepositoryProvider);
    if (!await repo.hasSession()) return null;

    try {
      return await repo.currentUser();
    } catch (_) {
      return null;
    }
  }

  Future<void> login(String email, String password) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(
      () => ref.read(authRepositoryProvider).login(email: email, password: password),
    );
  }

  Future<void> logout() async {
    await ref.read(authRepositoryProvider).logout();
    state = const AsyncData(null);
  }

  void forceLogout() {
    state = const AsyncData(null);
  }
}
