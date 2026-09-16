import '../../dispatch/domain/vehicle.dart';
import 'route_stop.dart';

class RouteModel {
  const RouteModel({
    required this.id,
    required this.code,
    required this.status,
    required this.stops,
    this.vehicle,
    this.driver,
  });

  factory RouteModel.fromJson(Map<String, dynamic> json) => RouteModel(
        id: json['id'] as int,
        code: json['code'] as String,
        status: json['status'] as String,
        vehicle: json['vehicle'] != null ? Vehicle.fromJson(json['vehicle'] as Map<String, dynamic>) : null,
        driver: json['driver'] != null ? UserOption.fromJson(json['driver'] as Map<String, dynamic>) : null,
        stops: (json['stops'] as List? ?? [])
            .map((e) => RouteStop.fromJson(e as Map<String, dynamic>))
            .toList(),
      );

  final int id;
  final String code;
  final String status;
  final Vehicle? vehicle;
  final UserOption? driver;
  final List<RouteStop> stops;
}
