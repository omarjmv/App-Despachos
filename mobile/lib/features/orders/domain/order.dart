import '../../customers/domain/customer.dart';
import '../../products/domain/product.dart';

class OrderItem {
  const OrderItem({
    required this.id,
    required this.product,
    required this.quantityRequested,
    required this.unit,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) => OrderItem(
        id: json['id'] as int,
        product: Product.fromJson(json['product'] as Map<String, dynamic>),
        quantityRequested: (json['quantity_requested'] as num).toDouble(),
        unit: json['unit'] as String,
      );

  final int id;
  final Product product;
  final double quantityRequested;
  final String unit;
}

class Order {
  const Order({
    required this.id,
    required this.number,
    required this.customer,
    required this.status,
    required this.orderDate,
    this.assignedToName,
    this.items = const [],
  });

  factory Order.fromJson(Map<String, dynamic> json) => Order(
        id: json['id'] as int,
        number: json['number'] as String,
        customer: Customer.fromJson(json['customer'] as Map<String, dynamic>),
        status: json['status'] as String,
        orderDate: json['order_date'] as String,
        assignedToName: (json['assigned_to'] as Map<String, dynamic>?)?['name'] as String?,
        items: (json['items'] as List? ?? [])
            .map((e) => OrderItem.fromJson(e as Map<String, dynamic>))
            .toList(),
      );

  final int id;
  final String number;
  final Customer customer;
  final String status;
  final String orderDate;
  final String? assignedToName;
  final List<OrderItem> items;
}
