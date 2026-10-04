import 'store.dart';

class OrderItem {
  final int id;
  final int itemId;
  final int? modelId;
  final String? itemName;
  final String? sku;
  final int quantity;
  final double price;

  const OrderItem({
    required this.id,
    required this.itemId,
    this.modelId,
    this.itemName,
    this.sku,
    required this.quantity,
    required this.price,
  });

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return OrderItem(
      id: parseNum(json['id']).toInt(),
      itemId: parseNum(json['item_id']).toInt(),
      modelId: json['model_id'] != null ? parseNum(json['model_id']).toInt() : null,
      itemName: json['item_name'] as String?,
      sku: (json['sku'] ?? json['item_sku'] ?? json['model_sku']) as String?,
      quantity: parseNum(json['quantity']).toInt(),
      price: parseNum(json['price']).toDouble(),
    );
  }
}

class OrderStore {
  final int id;
  final String? shopName;
  final int shopId;
  final String? region;
  final MarketplaceType platform;

  const OrderStore({
    required this.id,
    this.shopName,
    required this.shopId,
    this.region,
    required this.platform,
  });

  factory OrderStore.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return OrderStore(
      id: parseNum(json['id']).toInt(),
      shopName: json['shop_name'] as String?,
      shopId: parseNum(json['shop_id']).toInt(),
      region: json['region'] as String?,
      platform: marketplaceFromString(json['platform'] as String?),
    );
  }
}

class Order {
  final int id;
  final int storeId;
  final String orderSn;
  final String orderStatus;
  final double totalAmount;
  final String? currency;
  final String? buyerUsername;
  final String? shippingAddress;
  final String? orderedAt;
  final String? paidTime;
  final String? shipByDate;
  final String? cancelReason;
  final String? syncedAt;
  final List<OrderItem> items;
  final OrderStore? store;

  const Order({
    required this.id,
    required this.storeId,
    required this.orderSn,
    required this.orderStatus,
    required this.totalAmount,
    this.currency,
    this.buyerUsername,
    this.shippingAddress,
    this.orderedAt,
    this.paidTime,
    this.shipByDate,
    this.cancelReason,
    this.syncedAt,
    required this.items,
    this.store,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return Order(
      id: parseNum(json['id']).toInt(),
      storeId: parseNum(json['store_id']).toInt(),
      orderSn: json['order_sn']?.toString() ?? '',
      orderStatus: json['order_status']?.toString() ?? '',
      totalAmount: parseNum(json['total_amount']).toDouble(),
      currency: json['currency'] as String?,
      buyerUsername: json['buyer_username'] as String?,
      shippingAddress: json['shipping_address'] as String?,
      orderedAt: json['ordered_at'] as String?,
      paidTime: json['paid_time'] as String?,
      shipByDate: _normalizeShipByDate(json['ship_by_date'] as String?),
      cancelReason: json['cancel_reason'] as String?,
      syncedAt: json['synced_at'] as String?,
      items: (json['items'] as List<dynamic>? ?? [])
          .map((i) => OrderItem.fromJson(i as Map<String, dynamic>))
          .toList(),
      store: json['store'] != null
          ? OrderStore.fromJson(json['store'] as Map<String, dynamic>)
          : null,
    );
  }

  /// Treats missing / epoch (1970-01-01) placeholders as no ship-by date.
  static String? _normalizeShipByDate(String? raw) {
    if (raw == null || raw.trim().isEmpty) return null;
    try {
      final dt = DateTime.parse(raw);
      if (dt.year < 1971) return null;
      return raw;
    } catch (_) {
      return null;
    }
  }

  bool get isOverdue {
    if (shipByDate == null) return false;
    try {
      return DateTime.parse(shipByDate!).isBefore(DateTime.now());
    } catch (_) {
      return false;
    }
  }

  bool get isDueToday {
    if (shipByDate == null) return false;
    try {
      final due = DateTime.parse(shipByDate!);
      final now = DateTime.now();
      return due.year == now.year &&
          due.month == now.month &&
          due.day == now.day;
    } catch (_) {
      return false;
    }
  }
}

class PaginatedOrders {
  final List<Order> data;
  final int currentPage;
  final int lastPage;
  final int total;
  final int perPage;

  const PaginatedOrders({
    required this.data,
    required this.currentPage,
    required this.lastPage,
    required this.total,
    required this.perPage,
  });

  factory PaginatedOrders.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return PaginatedOrders(
      data: (json['data'] as List<dynamic>)
          .map((o) => Order.fromJson(o as Map<String, dynamic>))
          .toList(),
      currentPage: parseNum(json['current_page']).toInt(),
      lastPage: parseNum(json['last_page']).toInt(),
      total: parseNum(json['total']).toInt(),
      perPage: parseNum(json['per_page']).toInt(),
    );
  }
}

class ShippingTimeSlot {
  final String date;
  final String timeText;
  final String? pickupTimeId;
  final String? dropOffTimeId;

  const ShippingTimeSlot({
    required this.date,
    required this.timeText,
    this.pickupTimeId,
    this.dropOffTimeId,
  });

  factory ShippingTimeSlot.fromJson(Map<String, dynamic> json) {
    return ShippingTimeSlot(
      date: json['date'] as String,
      timeText: json['time_text'] as String,
      pickupTimeId: json['pickup_time_id'] as String?,
      dropOffTimeId: json['drop_off_time_id'] as String?,
    );
  }
}

class PickupAddress {
  final int addressId;
  final String address;
  final String city;
  final String state;
  final String zipcode;
  final bool? defaultAddress;
  final List<ShippingTimeSlot> timeSlotList;

  const PickupAddress({
    required this.addressId,
    required this.address,
    required this.city,
    required this.state,
    required this.zipcode,
    this.defaultAddress,
    required this.timeSlotList,
  });

  factory PickupAddress.fromJson(Map<String, dynamic> json) {
    return PickupAddress(
      addressId: json['address_id'] as int,
      address: json['address'] as String,
      city: json['city'] as String,
      state: json['state'] as String,
      zipcode: json['zipcode'] as String,
      defaultAddress: json['default_address'] as bool?,
      timeSlotList: (json['time_slot_list'] as List<dynamic>? ?? [])
          .map((s) => ShippingTimeSlot.fromJson(s as Map<String, dynamic>))
          .toList(),
    );
  }
}

class ShippingParameters {
  final List<PickupAddress> pickupAddresses;

  const ShippingParameters({required this.pickupAddresses});

  factory ShippingParameters.fromJson(Map<String, dynamic> json) {
    final pickup = json['pickup'] as Map<String, dynamic>?;
    final addressList = pickup?['address_list'] as List<dynamic>? ?? [];
    return ShippingParameters(
      pickupAddresses: addressList
          .map((a) => PickupAddress.fromJson(a as Map<String, dynamic>))
          .toList(),
    );
  }
}
