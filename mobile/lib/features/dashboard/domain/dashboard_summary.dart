class DashboardSummary {
  const DashboardSummary({
    required this.pedidosHoy,
    required this.preparados,
    required this.enRevision,
    required this.despachados,
    required this.enRuta,
    required this.entregados,
    required this.parciales,
    required this.cancelados,
  });

  factory DashboardSummary.fromJson(Map<String, dynamic> json) => DashboardSummary(
        pedidosHoy: json['pedidos_hoy'] as int,
        preparados: json['preparados'] as int,
        enRevision: json['en_revision'] as int,
        despachados: json['despachados'] as int,
        enRuta: json['en_ruta'] as int,
        entregados: json['entregados'] as int,
        parciales: json['parciales'] as int,
        cancelados: json['cancelados'] as int,
      );

  final int pedidosHoy;
  final int preparados;
  final int enRevision;
  final int despachados;
  final int enRuta;
  final int entregados;
  final int parciales;
  final int cancelados;
}
