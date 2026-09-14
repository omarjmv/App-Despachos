import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/dashboard/presentation/dashboard_screen.dart';
import '../../features/orders/presentation/create_order_screen.dart';
import '../../features/orders/presentation/order_detail_screen.dart';
import '../../features/orders/presentation/orders_list_screen.dart';
import '../../features/preparation/presentation/preparation_screen.dart';
import '../../features/review/presentation/review_list_screen.dart';
import 'splash_screen.dart';

/// Se reconstruye cuando cambia el estado de sesión (login/logout), lo
/// cual es infrecuente, así que recrear el GoRouter aquí es aceptable y
/// evita mecanismos extra (refreshListenable + StreamController) para un
/// caso de uso simple.
final appRouterProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authControllerProvider);
  final isAuthenticated = authState.value != null;
  final isCheckingSession = authState.isLoading && !authState.hasValue;

  return GoRouter(
    initialLocation: '/splash',
    redirect: (context, state) {
      final onSplash = state.matchedLocation == '/splash';

      // Aún resolviendo si hay una sesión guardada (bootstrap.dart -> authControllerProvider.build()).
      if (isCheckingSession) return onSplash ? null : '/splash';

      final loggingIn = state.matchedLocation == '/login';
      if (!isAuthenticated) return loggingIn ? null : '/login';
      if (loggingIn || onSplash) return '/';
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (context, state) => const SplashScreen()),
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(path: '/', builder: (context, state) => const OrdersListScreen()),
      GoRoute(path: '/dashboard', builder: (context, state) => const DashboardScreen()),
      GoRoute(path: '/review', builder: (context, state) => const ReviewListScreen()),
      GoRoute(path: '/orders/new', builder: (context, state) => const CreateOrderScreen()),
      GoRoute(
        path: '/orders/:id',
        builder: (context, state) =>
            OrderDetailScreen(orderId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(
        path: '/orders/:id/preparation',
        builder: (context, state) =>
            PreparationScreen(orderId: int.parse(state.pathParameters['id']!)),
      ),
    ],
  );
});
