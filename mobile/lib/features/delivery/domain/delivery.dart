class DeliveryItemPayload {
  DeliveryItemPayload({
    required this.dispatchItemId,
    required this.quantityDelivered,
    this.quantityRejected = 0,
    this.rejectionReason,
  });

  final int dispatchItemId;
  final double quantityDelivered;
  final double quantityRejected;
  final String? rejectionReason;

  Map<String, dynamic> toJson() => {
        'dispatch_item_id': dispatchItemId,
        'quantity_delivered': quantityDelivered,
        'quantity_rejected': quantityRejected,
        if (rejectionReason != null) 'rejection_reason': rejectionReason,
      };
}

class Delivery {
  const Delivery({required this.id, required this.result});

  factory Delivery.fromJson(Map<String, dynamic> json) =>
      Delivery(id: json['id'] as int, result: json['result'] as String);

  final int id;
  final String result;
}
