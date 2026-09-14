/// Representa un error de negocio o de red ya traducido a algo que la UI
/// puede mostrar, en vez de propagar excepciones crudas de Dio.
sealed class Failure {
  const Failure(this.message);

  final String message;
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
