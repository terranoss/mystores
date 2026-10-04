import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../api/api_error.dart';
import '../models/order.dart';
import '../models/store.dart';

class OrderFilterState {
  final int? storeId;
  final String? status;
  final String? urgency;
  final MarketplaceType? platform;
  final String search;

  const OrderFilterState({
    this.storeId,
    this.status,
    this.urgency,
    this.platform,
    this.search = '',
  });

  OrderFilterState copyWith({
    int? storeId,
    String? status,
    String? urgency,
    MarketplaceType? platform,
    String? search,
    bool clearStore = false,
    bool clearStatus = false,
    bool clearUrgency = false,
    bool clearPlatform = false,
  }) {
    return OrderFilterState(
      storeId: clearStore ? null : storeId ?? this.storeId,
      status: clearStatus ? null : status ?? this.status,
      urgency: clearUrgency ? null : urgency ?? this.urgency,
      platform: clearPlatform ? null : platform ?? this.platform,
      search: search ?? this.search,
    );
  }
}

class OrderState {
  final List<Order> orders;
  final bool isLoading;
  final bool isLoadingMore;
  final String? error;
  final int currentPage;
  final int lastPage;
  final OrderFilterState filters;

  const OrderState({
    this.orders = const [],
    this.isLoading = false,
    this.isLoadingMore = false,
    this.error,
    this.currentPage = 1,
    this.lastPage = 1,
    this.filters = const OrderFilterState(),
  });

  bool get hasMore => currentPage < lastPage;

  OrderState copyWith({
    List<Order>? orders,
    bool? isLoading,
    bool? isLoadingMore,
    String? error,
    int? currentPage,
    int? lastPage,
    OrderFilterState? filters,
    bool clearError = false,
  }) {
    return OrderState(
      orders: orders ?? this.orders,
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      error: clearError ? null : error ?? this.error,
      currentPage: currentPage ?? this.currentPage,
      lastPage: lastPage ?? this.lastPage,
      filters: filters ?? this.filters,
    );
  }
}

class OrderNotifier extends StateNotifier<OrderState> {
  OrderNotifier() : super(const OrderState());

  Future<void> fetchOrders({bool reset = true}) async {
    if (reset) {
      state = state.copyWith(isLoading: true, clearError: true, orders: []);
    } else {
      if (!state.hasMore || state.isLoadingMore) return;
      state = state.copyWith(isLoadingMore: true);
    }

    final page = reset ? 1 : state.currentPage + 1;
    final f = state.filters;

    String? platformStr;
    if (f.platform == MarketplaceType.shopee) platformStr = 'shopee';
    if (f.platform == MarketplaceType.lazada) platformStr = 'lazada';

    try {
      final res = await apiClient.get(
        ApiEndpoints.orders,
        queryParameters: {
          if (f.storeId != null) 'store_id': f.storeId,
          if (f.status != null && f.status!.isNotEmpty) 'status': f.status,
          if (f.urgency != null && f.urgency!.isNotEmpty) 'urgency': f.urgency,
          if (platformStr != null) 'platform': platformStr,
          if (f.search.isNotEmpty) 'search': f.search,
          'page': page,
          'sort_by': 'ordered_at',
          'sort_dir': 'desc',
        },
      );
      final paginated =
          PaginatedOrders.fromJson(res.data as Map<String, dynamic>);
      final newOrders =
          reset ? paginated.data : [...state.orders, ...paginated.data];
      state = state.copyWith(
        orders: newOrders,
        isLoading: false,
        isLoadingMore: false,
        currentPage: paginated.currentPage,
        lastPage: paginated.lastPage,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        isLoadingMore: false,
        error: e.toString(),
      );
    }
  }

  void updateFilters(OrderFilterState filters) {
    state = state.copyWith(filters: filters);
  }

  Future<ApiResult<void>> cancelOrder(int orderId, String reason,
      {bool isLazada = false}) async {
    try {
      final endpoint = isLazada
          ? ApiEndpoints.lazadaOrderCancel(orderId)
          : ApiEndpoints.orderCancel(orderId);
      final data = isLazada
          ? {'reason_id': reason}
          : {'cancel_reason': reason};
      await apiClient.post(endpoint, data: data);
      await fetchOrders();
      return ApiResult.ok();
    } catch (e) {
      return ApiResult.fail(
        apiErrorMessage(e, fallback: 'Failed to cancel order. Please try again.'),
      );
    }
  }

  Future<ShippingParameters?> getShippingParameters(int orderId) async {
    try {
      final res =
          await apiClient.get(ApiEndpoints.orderShippingParams(orderId));
      return ShippingParameters.fromJson(
          res.data['params'] as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  Future<bool> shipOrder(
      int orderId, Map<String, dynamic> params) async {
    try {
      await apiClient.post(ApiEndpoints.orderShip(orderId), data: params);
      await fetchOrders();
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> syncOrders() async {
    final platform = state.filters.platform;
    var anyOk = false;
    if (platform != MarketplaceType.lazada) {
      try {
        await apiClient
            .post(ApiEndpoints.ordersSync, data: {'platform': 'shopee'});
        anyOk = true;
      } catch (_) {}
    }
    if (platform != MarketplaceType.shopee) {
      try {
        await apiClient.post(ApiEndpoints.lazadaOrdersSync);
        anyOk = true;
      } catch (_) {}
    }
    if (anyOk) {
      await fetchOrders();
    }
    return anyOk;
  }
}

final orderProvider =
    StateNotifierProvider<OrderNotifier, OrderState>(
  (ref) => OrderNotifier(),
);
