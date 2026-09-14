import 'package:flutter/material.dart';

/// Tema único de la app: botones grandes, colores de estado consistentes
/// (sección 23 del brief: interfaz empresarial pero sencilla).
class AppTheme {
  const AppTheme._();

  static const seedColor = Color(0xFF1565C0);

  static ThemeData get light => ThemeData(
        useMaterial3: true,
        colorSchemeSeed: seedColor,
        brightness: Brightness.light,
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            minimumSize: const Size.fromHeight(52),
            textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
          ),
        ),
        inputDecorationTheme: const InputDecorationTheme(
          border: OutlineInputBorder(),
          filled: true,
        ),
        appBarTheme: const AppBarTheme(centerTitle: true),
      );
}

/// Color consistente por estado de pedido en toda la app.
class OrderStatusColors {
  const OrderStatusColors._();

  static Color of(String status) {
    switch (status) {
      case 'PENDIENTE':
        return Colors.grey;
      case 'PREPARANDO':
        return Colors.blue;
      case 'PREPARADO':
        return Colors.indigo;
      case 'EN_REVISION':
        return Colors.orange;
      case 'APROBADO':
        return Colors.teal;
      case 'DESPACHADO':
        return Colors.purple;
      case 'EN_RUTA':
        return Colors.deepPurple;
      case 'ENTREGADO':
        return Colors.green;
      case 'PARCIAL':
        return Colors.amber.shade800;
      case 'CANCELADO':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }
}
