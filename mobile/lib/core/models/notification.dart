class AppNotificationData {
  final String category;
  final String title;
  final String message;
  final int? storeId;
  final int? orderId;
  final String? orderSn;
  final String? platform;
  final String? actionType;
  final String? kind;

  const AppNotificationData({
    required this.category,
    required this.title,
    required this.message,
    this.storeId,
    this.orderId,
    this.orderSn,
    this.platform,
    this.actionType,
    this.kind,
  });

  factory AppNotificationData.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return AppNotificationData(
      category: json['category'] as String? ?? 'general',
      title: json['title'] as String? ?? '',
      message: json['message'] as String? ?? '',
      storeId: json['store_id'] != null ? parseNum(json['store_id']).toInt() : null,
      orderId: json['order_id'] != null ? parseNum(json['order_id']).toInt() : null,
      orderSn: json['order_sn'] as String?,
      platform: json['platform'] as String?,
      actionType: json['action_type'] as String?,
      kind: json['kind'] as String?,
    );
  }
}

class AppNotification {
  final String id;
  final String type;
  final AppNotificationData data;
  final String? readAt;
  final String createdAt;

  const AppNotification({
    required this.id,
    required this.type,
    required this.data,
    this.readAt,
    required this.createdAt,
  });

  bool get isRead => readAt != null;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: json['id'] as String,
      type: json['type'] as String? ?? '',
      data: AppNotificationData.fromJson(json['data'] as Map<String, dynamic>? ?? {}),
      readAt: json['read_at'] as String?,
      createdAt: json['created_at'] as String? ?? '',
    );
  }
}
