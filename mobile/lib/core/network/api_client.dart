import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../error/app_exception.dart';
import '../error/failure.dart';
import '../storage/token_storage.dart';

/// Cliente Dio único de la app. Inyecta el token en cada petición y
/// traduce errores HTTP a [Failure] para que la capa de dominio nunca
/// tenga que conocer Dio.
class ApiClient {
  ApiClient(this._tokenStorage, {this.onUnauthorized}) {
    _dio = Dio(
      BaseOptions(
        baseUrl: AppConfig.apiBaseUrl,
        connectTimeout: AppConfig.connectTimeout,
        receiveTimeout: AppConfig.receiveTimeout,
        headers: {'Accept': 'application/json'},
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStorage.read();
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (error, handler) {
          if (error.response?.statusCode == 401) {
            onUnauthorized?.call();
          }
          handler.next(error);
        },
      ),
    );
  }

  late final Dio _dio;
  final TokenStorage _tokenStorage;

  /// Se invoca cuando el servidor responde 401: la sesión ya no es válida
  /// (token revocado o expirado) y la app debe volver a login.
  final void Function()? onUnauthorized;

  Future<Response<dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _run(() => _dio.get(path, queryParameters: query));

  Future<Response<dynamic>> post(String path, {Object? data}) =>
      _run(() => _dio.post(path, data: data));

  Future<Response<dynamic>> put(String path, {Object? data}) =>
      _run(() => _dio.put(path, data: data));

  Future<Response<dynamic>> delete(String path) => _run(() => _dio.delete(path));

  Future<Response<dynamic>> _run(Future<Response<dynamic>> Function() request) async {
    try {
      return await request();
    } on DioException catch (e) {
      throw AppException(_mapError(e));
    }
  }

  Failure _mapError(DioException e) {
    if (e.type == DioExceptionType.connectionError ||
        e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout) {
      return const NetworkFailure();
    }

    final status = e.response?.statusCode;
    final body = e.response?.data;
    final message = (body is Map && body['message'] is String)
        ? body['message'] as String
        : 'Ocurrió un error inesperado.';

    switch (status) {
      case 401:
        return AuthFailure(message);
      case 403:
        return ForbiddenFailure(message);
      case 404:
        return NotFoundFailure(message);
      case 409:
        return ConflictFailure(message);
      case 422:
        final rawErrors = (body is Map && body['errors'] is Map) ? body['errors'] as Map : const {};
        final errors = rawErrors.map(
          (key, value) => MapEntry(key.toString(), List<String>.from(value as List)),
        );
        return ValidationFailure(message, errors);
      default:
        return UnknownFailure(message);
    }
  }
}
