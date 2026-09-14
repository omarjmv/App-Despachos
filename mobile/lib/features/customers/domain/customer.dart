class Customer {
  const Customer({required this.id, required this.code, required this.name});

  factory Customer.fromJson(Map<String, dynamic> json) => Customer(
        id: json['id'] as int,
        code: json['code'] as String,
        name: json['name'] as String,
      );

  final int id;
  final String code;
  final String name;

  @override
  String toString() => name;
}
