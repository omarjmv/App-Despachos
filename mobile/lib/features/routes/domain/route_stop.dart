import '../../customers/domain/customer.dart';
import '../../products/domain/product.dart';

class DispatchItemInfo {
  const DispatchItemInfo({
    required this.id,
    required this.product,
    required this.quantityDispatched,
  });

  factory DispatchItemInfo.fromJson(Map<String, dynamic> json) => DispatchItemInfo(
        id: json['id'] as int,
        product: Product.fromJson(json['product'] as Map<String, dynamic>),
        quantityDispatched: (json['quantity_dispatched'] as num).toDouble(),
      );

  final int id;
  final Product product;
  final double quantityDispatched;
}

class DispatchInfo {
  const DispatchInfo({
    required this.id,
    required this.number,
    required this.customer,
    required this.items,
  });

  factory DispatchInfo.fromJson(Map<String, dynamic> json) => DispatchInfo(
        id: json['id'] as int,
        number: json['number'] as String,
        customer: Customer.fromJson(
          (json['order'] as Map<String, dynamic>)['customer'] as Map<String, dynamic>,
        ),
        items: (json['items'] as List? ?? [])
            .map((e) => DispatchItemInfo.fromJson(e as Map<String, dynamic>))
            .toList(),
      );

  final int id;
  final String number;
  final Customer customer;
  final List<DispatchItemInfo> items;
}

class RouteStop {
  const RouteStop({
    required this.id,
    required this.sequence,
    required this.status,
    required this.dispatch,
    this.deliveryResult,
  });

  factory RouteStop.fromJson(Map<String, dynamic> json) => RouteStop(
        id: json['id'] as int,
        sequence: json['sequence'] as int,
        status: json['status'] as String,
        dispatch: DispatchInfo.fromJson(json['dispatch'] as Map<String, dynamic>),
        deliveryResult: (json['delivery'] as Map<String, dynamic>?)?['result'] as String?,
      );

  final int id;
  final int sequence;
  final String status;
  final DispatchInfo dispatch;
  final String? deliveryResult;
}
