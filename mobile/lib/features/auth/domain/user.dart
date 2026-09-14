enum UserRole { administrador, supervisor, preparador, revisor, motorista, desconocido }

UserRole roleFromName(String? name) {
  switch (name) {
    case 'ADMINISTRADOR':
      return UserRole.administrador;
    case 'SUPERVISOR':
      return UserRole.supervisor;
    case 'PREPARADOR':
      return UserRole.preparador;
    case 'REVISOR':
      return UserRole.revisor;
    case 'MOTORISTA':
      return UserRole.motorista;
    default:
      return UserRole.desconocido;
  }
}

class AppUser {
  const AppUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    this.phone,
  });

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String?,
      role: roleFromName((json['role'] as Map<String, dynamic>?)?['name'] as String?),
    );
  }

  final int id;
  final String name;
  final String email;
  final String? phone;
  final UserRole role;

  bool get canManageCatalogs => role == UserRole.administrador;
  bool get canCreateOrders => role == UserRole.administrador || role == UserRole.supervisor;
  bool get canReview => role == UserRole.administrador || role == UserRole.revisor;
  bool get canPrepare => role == UserRole.preparador || role == UserRole.administrador;
  bool get canDispatch => role == UserRole.administrador || role == UserRole.supervisor;
}
