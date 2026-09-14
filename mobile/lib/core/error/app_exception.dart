import 'failure.dart';

/// Excepción interna que las fuentes de datos lanzan y que los
/// repositorios traducen a [Failure] antes de llegar a la UI.
class AppException implements Exception {
  const AppException(this.failure);

  final Failure failure;
}
