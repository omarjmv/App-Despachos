import '../../products/domain/product.dart';

const differenceReasons = [
  'FALTANTE_INVENTARIO',
  'PRODUCTO_DANADO',
  'ERROR_PEDIDO',
  'SUSTITUCION',
  'OTRO',
];

class PreparationItem {
  const PreparationItem({
    required this.id,
    required this.orderItemId,
    required this.product,
    required this.quantityRequested,
    required this.quantityPrepared,
    required this.difference,
    this.differenceReason,
  });

  factory PreparationItem.fromJson(Map<String, dynamic> json) => PreparationItem(
        id: json['id'] as int,
        orderItemId: json['order_item_id'] as int,
        product: Product.fromJson(json['product'] as Map<String, dynamic>),
        quantityRequested: (json['quantity_requested'] as num).toDouble(),
        quantityPrepared: (json['quantity_prepared'] as num).toDouble(),
        difference: (json['difference'] as num).toDouble(),
        differenceReason: json['difference_reason'] as String?,
      );

  final int id;
  final int orderItemId;
  final Product product;
  final double quantityRequested;
  final double quantityPrepared;
  final double difference;
  final String? differenceReason;

  bool get isComplete => difference <= 0;
}

class Preparation {
  const Preparation({
    required this.id,
    required this.orderId,
    required this.status,
    required this.totalRequested,
    required this.totalPrepared,
    required this.items,
  });

  factory Preparation.fromJson(Map<String, dynamic> json) => Preparation(
        id: json['id'] as int,
        orderId: json['order_id'] as int,
        status: json['status'] as String,
        totalRequested: (json['total_requested'] as num).toDouble(),
        totalPrepared: (json['total_prepared'] as num).toDouble(),
        items: (json['items'] as List)
            .map((e) => PreparationItem.fromJson(e as Map<String, dynamic>))
            .toList(),
      );

  final int id;
  final int orderId;
  final String status;
  final double totalRequested;
  final double totalPrepared;
  final List<PreparationItem> items;
}
