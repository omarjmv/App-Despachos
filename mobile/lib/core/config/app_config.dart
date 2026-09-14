/// Configuración de entorno. En un build real esto vendría de
/// --dart-define, no hardcodeado (sección 22: nunca secretos en el código).
class AppConfig {
  const AppConfig._();

  /// 10.0.2.2 es el alias del host desde el emulador de Android hacia
  /// localhost de la máquina que corre el backend Laravel en desarrollo.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 20);
}
