import 'app_exception.dart';

/// Representa un error de negocio o de red ya traducido a algo que la UI
/// puede mostrar, en vez de propagar excepciones crudas de Dio.
sealed class Failure {
  const Failure(this.message);

  final String message;
}

/// Único punto para convertir cualquier error atrapado en un `catch` a un
/// mensaje mostrable. Los repositorios lanzan [AppException] (que envuelve
/// un [Failure]), así que comparar directamente `e is Failure` nunca es
/// cierto y termina mostrando "Instance of 'AppException'" en vez del
/// mensaje real — este helper evita repetir (y repetir mal) ese chequeo
/// en cada pantalla.
String errorMessageOf(Object error) {
  final failure = switch (error) {
    AppException(:final failure) => failure,
    Failure f => f,
    _ => null,
  };

  return failure?.message ?? error.toString();
}

class NetworkFailure extends Failure {
  const NetworkFailure([super.message = 'No hay conexión con el servidor.']);
}

class ValidationFailure extends Failure {
  const ValidationFailure(super.message, this.errors);

  final Map<String, List<String>> errors;
}

class AuthFailure extends Failure {
  const AuthFailure([super.message = 'Sesión inválida o expirada.']);
}

class ForbiddenFailure extends Failure {
  const ForbiddenFailure([super.message = 'No tiene permisos para esta acción.']);
}

class NotFoundFailure extends Failure {
  const NotFoundFailure([super.message = 'Recurso no encontrado.']);
}

class ConflictFailure extends Failure {
  const ConflictFailure(super.message);
}

class UnknownFailure extends Failure {
  const UnknownFailure([super.message = 'Ocurrió un error inesperado.']);
}
