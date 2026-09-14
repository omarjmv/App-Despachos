class Product {
  const Product({required this.id, required this.sku, required this.name, required this.unit});

  factory Product.fromJson(Map<String, dynamic> json) => Product(
        id: json['id'] as int,
        sku: json['sku'] as String,
        name: json['name'] as String,
        unit: json['unit'] as String,
      );

  final int id;
  final String sku;
  final String name;
  final String unit;

  @override
  String toString() => name;
}
