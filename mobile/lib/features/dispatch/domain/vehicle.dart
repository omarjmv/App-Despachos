class Vehicle {
  const Vehicle({required this.id, required this.plate});

  factory Vehicle.fromJson(Map<String, dynamic> json) =>
      Vehicle(id: json['id'] as int, plate: json['plate'] as String);

  final int id;
  final String plate;

  @override
  String toString() => plate;
}

class UserOption {
  const UserOption({required this.id, required this.name});

  factory UserOption.fromJson(Map<String, dynamic> json) =>
      UserOption(id: json['id'] as int, name: json['name'] as String);

  final int id;
  final String name;

  @override
  String toString() => name;
}
