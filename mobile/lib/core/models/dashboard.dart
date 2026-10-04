class AppUser {
  final int id;
  final String name;
  final String email;

  const AppUser({required this.id, required this.name, required this.email});

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
      };
}

class StoreOrderBreakdown {
  final int storeId;
  final String? shopName;
  final String platform;
  final int count;
  final double? gmv;

  const StoreOrderBreakdown({
    required this.storeId,
    this.shopName,
    required this.platform,
    required this.count,
    this.gmv,
  });

  factory StoreOrderBreakdown.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return StoreOrderBreakdown(
      storeId: parseNum(json['store_id']).toInt(),
      shopName: json['shop_name'] as String?,
      platform: json['platform'] as String,
      count: parseNum(json['count']).toInt(),
      gmv: json['gmv'] != null ? parseNum(json['gmv']).toDouble() : null,
    );
  }
}

class SalesTrendDay {
  final String date;
  final int orders;
  final double gmv;

  const SalesTrendDay({
    required this.date,
    required this.orders,
    required this.gmv,
  });

  factory SalesTrendDay.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return SalesTrendDay(
      date: json['date'] as String,
      orders: parseNum(json['orders']).toInt(),
      gmv: parseNum(json['gmv']).toDouble(),
    );
  }
}

class SalesGrowthPeriod {
  final double? salesPct;
  final int ordersDelta;
  final double? salesToday;
  final double? salesYesterday;
  final int? ordersToday;
  final int? ordersYesterday;

  const SalesGrowthPeriod({
    this.salesPct,
    required this.ordersDelta,
    this.salesToday,
    this.salesYesterday,
    this.ordersToday,
    this.ordersYesterday,
  });

  factory SalesGrowthPeriod.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return SalesGrowthPeriod(
      salesPct: json['sales_pct'] != null
          ? parseNum(json['sales_pct']).toDouble()
          : null,
      ordersDelta: json['orders_delta'] != null
          ? parseNum(json['orders_delta']).toInt()
          : 0,
      salesToday: json['sales_today'] != null
          ? parseNum(json['sales_today']).toDouble()
          : null,
      salesYesterday: json['sales_yesterday'] != null
          ? parseNum(json['sales_yesterday']).toDouble()
          : null,
      ordersToday: json['orders_today'] != null
          ? parseNum(json['orders_today']).toInt()
          : null,
      ordersYesterday: json['orders_yesterday'] != null
          ? parseNum(json['orders_yesterday']).toInt()
          : null,
    );
  }
}

class SalesGrowth {
  final SalesGrowthPeriod todayVsYesterday;
  final SalesGrowthPeriod periodVsPrior;

  const SalesGrowth({
    required this.todayVsYesterday,
    required this.periodVsPrior,
  });

  factory SalesGrowth.fromJson(Map<String, dynamic> json) {
    return SalesGrowth(
      todayVsYesterday: json['today_vs_yesterday'] != null
          ? SalesGrowthPeriod.fromJson(
              json['today_vs_yesterday'] as Map<String, dynamic>)
          : const SalesGrowthPeriod(ordersDelta: 0),
      periodVsPrior: json['period_vs_prior'] != null
          ? SalesGrowthPeriod.fromJson(
              json['period_vs_prior'] as Map<String, dynamic>)
          : const SalesGrowthPeriod(ordersDelta: 0),
    );
  }
}

class DashboardStats {
  final int storesCount;
  final int ordersToday;
  final double? gmvToday;
  final int pendingOrdersCount;
  final List<StoreOrderBreakdown> pendingOrdersByStore;
  final List<SalesTrendDay> salesTrend;
  final SalesGrowth? salesGrowth;
  final List<CancelReasonStat> cancelReasons;
  final int cancelReasonsTotal;
  final int lowStockItems;
  final int? trendDays;

  const DashboardStats({
    required this.storesCount,
    required this.ordersToday,
    this.gmvToday,
    required this.pendingOrdersCount,
    required this.pendingOrdersByStore,
    required this.salesTrend,
    this.salesGrowth,
    this.cancelReasons = const [],
    this.cancelReasonsTotal = 0,
    required this.lowStockItems,
    this.trendDays,
  });

  factory DashboardStats.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return DashboardStats(
      storesCount: parseNum(json['stores_count']).toInt(),
      ordersToday: parseNum(json['orders_today']).toInt(),
      gmvToday: json['gmv_today'] != null
          ? parseNum(json['gmv_today']).toDouble()
          : null,
      pendingOrdersCount: parseNum(json['pending_orders_count']).toInt(),
      pendingOrdersByStore:
          (json['pending_orders_by_store'] as List<dynamic>? ?? [])
              .map((s) =>
                  StoreOrderBreakdown.fromJson(s as Map<String, dynamic>))
              .toList(),
      salesTrend: (json['sales_trend'] as List<dynamic>? ?? [])
          .map((d) => SalesTrendDay.fromJson(d as Map<String, dynamic>))
          .toList(),
      salesGrowth: json['sales_growth'] != null
          ? SalesGrowth.fromJson(
              json['sales_growth'] as Map<String, dynamic>)
          : null,
      cancelReasons: (json['cancel_reasons'] as List<dynamic>? ?? [])
          .map((r) => CancelReasonStat.fromJson(r as Map<String, dynamic>))
          .toList(),
      cancelReasonsTotal: parseNum(json['cancel_reasons_total'] ?? 0).toInt(),
      lowStockItems: parseNum(json['low_stock_items']).toInt(),
      trendDays: json['trend_days'] != null
          ? parseNum(json['trend_days']).toInt()
          : null,
    );
  }
}

class CancelReasonStat {
  final String reason;
  final String label;
  final int count;

  const CancelReasonStat({
    required this.reason,
    required this.label,
    required this.count,
  });

  factory CancelReasonStat.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return CancelReasonStat(
      reason: (json['reason'] as String?) ?? 'Unknown',
      label: (json['label'] as String?) ??
          ((json['reason'] as String?) ?? 'Unknown'),
      count: parseNum(json['count']).toInt(),
    );
  }
}

class PickListComment {
  final int id;
  final String body;
  final DateTime? createdAt;
  final int userId;
  final String userName;

  const PickListComment({
    required this.id,
    required this.body,
    this.createdAt,
    required this.userId,
    required this.userName,
  });

  factory PickListComment.fromJson(Map<String, dynamic> json) {
    final user = json['user'] as Map<String, dynamic>? ?? {};
    DateTime? createdAt;
    final rawAt = json['created_at'];
    if (rawAt is String && rawAt.isNotEmpty) {
      createdAt = DateTime.tryParse(rawAt);
    }
    return PickListComment(
      id: (json['id'] as num).toInt(),
      body: json['body'] as String? ?? '',
      createdAt: createdAt,
      userId: (user['id'] as num?)?.toInt() ?? 0,
      userName: user['name'] as String? ?? 'Unknown',
    );
  }
}

class PickListCompletionInfo {
  final int id;
  final int pickedById;
  final String pickedByName;
  final DateTime? pickedAt;
  final String photoUrl;

  const PickListCompletionInfo({
    required this.id,
    required this.pickedById,
    required this.pickedByName,
    this.pickedAt,
    required this.photoUrl,
  });

  factory PickListCompletionInfo.fromJson(Map<String, dynamic> json) {
    final pickedBy = json['picked_by'] as Map<String, dynamic>? ?? {};
    DateTime? pickedAt;
    final rawAt = json['picked_at'];
    if (rawAt is String && rawAt.isNotEmpty) {
      pickedAt = DateTime.tryParse(rawAt);
    }
    return PickListCompletionInfo(
      id: (json['id'] as num).toInt(),
      pickedById: (pickedBy['id'] as num?)?.toInt() ?? 0,
      pickedByName: pickedBy['name'] as String? ?? 'Unknown',
      pickedAt: pickedAt,
      photoUrl: json['photo_url'] as String? ?? '',
    );
  }
}

class PickListItem {
  final String itemKey;
  final String? itemId;
  final String? modelId;
  final String itemName;
  final int quantity;
  final List<String> storeNames;
  final List<String> platforms;
  final PickListCompletionInfo? completion;
  final List<PickListComment> comments;
  final int commentCount;
  final bool hasUnreadComments;
  final int unreadCommentCount;

  const PickListItem({
    required this.itemKey,
    this.itemId,
    this.modelId,
    required this.itemName,
    required this.quantity,
    required this.storeNames,
    required this.platforms,
    this.completion,
    this.comments = const [],
    this.commentCount = 0,
    this.hasUnreadComments = false,
    this.unreadCommentCount = 0,
  });

  bool get isPicked => completion != null;

  PickListItem copyWith({
    PickListCompletionInfo? completion,
    List<PickListComment>? comments,
    int? commentCount,
    bool? hasUnreadComments,
    int? unreadCommentCount,
  }) {
    return PickListItem(
      itemKey: itemKey,
      itemId: itemId,
      modelId: modelId,
      itemName: itemName,
      quantity: quantity,
      storeNames: storeNames,
      platforms: platforms,
      completion: completion ?? this.completion,
      comments: comments ?? this.comments,
      commentCount: commentCount ?? this.commentCount,
      hasUnreadComments: hasUnreadComments ?? this.hasUnreadComments,
      unreadCommentCount: unreadCommentCount ?? this.unreadCommentCount,
    );
  }

  factory PickListItem.fromJson(Map<String, dynamic> json) {
    String? asString(dynamic v) {
      if (v == null) return null;
      return v.toString();
    }

    final itemId = asString(json['item_id']);
    final modelId = asString(json['model_id']);
    final itemName = json['item_name'] as String? ?? '';
    final itemKey = json['item_key'] as String? ??
        '${itemId ?? itemName}_${modelId ?? ''}';
    PickListCompletionInfo? completion;
    final rawCompletion = json['completion'];
    if (rawCompletion is Map<String, dynamic>) {
      completion = PickListCompletionInfo.fromJson(rawCompletion);
    }
    final comments = (json['comments'] as List<dynamic>? ?? [])
        .whereType<Map<String, dynamic>>()
        .map(PickListComment.fromJson)
        .toList();
    final commentCount = (json['comment_count'] as num?)?.toInt() ??
        comments.length;
    final unreadCommentCount =
        (json['unread_comment_count'] as num?)?.toInt() ?? 0;
    final hasUnreadComments =
        json['has_unread_comments'] as bool? ?? unreadCommentCount > 0;

    return PickListItem(
      itemKey: itemKey,
      itemId: itemId,
      modelId: modelId,
      itemName: itemName,
      quantity: (json['quantity'] as num).toInt(),
      storeNames: (json['store_names'] as List<dynamic>? ?? [])
          .map((s) => s as String)
          .toList(),
      platforms: (json['platforms'] as List<dynamic>? ?? [])
          .map((s) => s as String)
          .toList(),
      completion: completion,
      comments: comments,
      commentCount: commentCount,
      hasUnreadComments: hasUnreadComments,
      unreadCommentCount: unreadCommentCount,
    );
  }
}

class PickList {
  final String date;
  final int totalOrders;
  final int totalSkus;
  final int totalUnits;
  final List<PickListItem> aggregatedItems;

  const PickList({
    required this.date,
    required this.totalOrders,
    required this.totalSkus,
    required this.totalUnits,
    required this.aggregatedItems,
  });

  factory PickList.fromJson(Map<String, dynamic> json) {
    return PickList(
      date: json['date'] as String,
      totalOrders: json['total_orders'] as int,
      totalSkus: json['total_skus'] as int,
      totalUnits: json['total_units'] as int,
      aggregatedItems: (json['aggregated_items'] as List<dynamic>? ?? [])
          .map((i) => PickListItem.fromJson(i as Map<String, dynamic>))
          .toList(),
    );
  }
}

class DashboardData {
  final AppUser user;
  final DashboardStats stats;

  const DashboardData({required this.user, required this.stats});

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    return DashboardData(
      user: AppUser.fromJson(json['user'] as Map<String, dynamic>),
      stats: DashboardStats.fromJson(json['stats'] as Map<String, dynamic>),
    );
  }
}
