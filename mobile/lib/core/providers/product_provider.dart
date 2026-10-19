import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../ads/interstitial_ad_manager.dart';
import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../models/product.dart';

class ProductFilterState {
  final int? storeId;
  final String search;
  final String sortBy;
  final String sortDir;
  final bool grouped;

  const ProductFilterState({
    this.storeId,
    this.search = '',
    this.sortBy = 'item_name',
    this.sortDir = 'asc',
    this.grouped = false,
  });

  ProductFilterState copyWith({
    int? storeId,
    String? search,
    String? sortBy,
    String? sortDir,
    bool? grouped,
    bool clearStore = false,
  }) {
    return ProductFilterState(
      storeId: clearStore ? null : storeId ?? this.storeId,
      search: search ?? this.search,
      sortBy: sortBy ?? this.sortBy,
      sortDir: sortDir ?? this.sortDir,
      grouped: grouped ?? this.grouped,
    );
  }
}

class ProductState {
  final List<Product> products;
  final GroupedResponse? groupedData;
  final bool isLoading;
  final bool isLoadingMore;
  final String? error;
  final int currentPage;
  final int lastPage;
  final ProductFilterState filters;

  const ProductState({
    this.products = const [],
    this.groupedData,
    this.isLoading = false,
    this.isLoadingMore = false,
    this.error,
    this.currentPage = 1,
    this.lastPage = 1,
    this.filters = const ProductFilterState(),
  });

  bool get hasMore => currentPage < lastPage;

  ProductState copyWith({
    List<Product>? products,
    GroupedResponse? groupedData,
    bool? isLoading,
    bool? isLoadingMore,
    String? error,
    int? currentPage,
    int? lastPage,
    ProductFilterState? filters,
    bool clearError = false,
  }) {
    return ProductState(
      products: products ?? this.products,
      groupedData: groupedData ?? this.groupedData,
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      error: clearError ? null : error ?? this.error,
      currentPage: currentPage ?? this.currentPage,
      lastPage: lastPage ?? this.lastPage,
      filters: filters ?? this.filters,
    );
  }
}

class ProductNotifier extends StateNotifier<ProductState> {
  ProductNotifier() : super(const ProductState());

  Future<void> fetchProducts({bool reset = true}) async {
    if (reset) {
      state = state.copyWith(isLoading: true, clearError: true, products: []);
    } else {
      if (!state.hasMore || state.isLoadingMore) return;
      state = state.copyWith(isLoadingMore: true);
    }

    final page = reset ? 1 : state.currentPage + 1;
    final filters = state.filters;

    try {
      final res = await apiClient.get(
        ApiEndpoints.products,
        queryParameters: {
          if (filters.storeId != null) 'store_id': filters.storeId,
          if (filters.search.isNotEmpty) 'search': filters.search,
          'sort_by': filters.sortBy,
          'sort_dir': filters.sortDir,
          'page': page,
        },
      );
      final paginated =
          PaginatedProducts.fromJson(res.data as Map<String, dynamic>);
      final newProducts = reset
          ? paginated.data
          : [...state.products, ...paginated.data];
      state = state.copyWith(
        products: newProducts,
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

  Future<void> fetchGrouped() async {
    state = state.copyWith(isLoading: true, clearError: true);
    final filters = state.filters;
    try {
      final res = await apiClient.get(
        ApiEndpoints.productsGrouped,
        queryParameters: {
          if (filters.storeId != null) 'store_id': filters.storeId,
          if (filters.search.isNotEmpty) 'search': filters.search,
          'sort_by': filters.sortBy,
          'sort_dir': filters.sortDir,
        },
      );
      final grouped =
          GroupedResponse.fromJson(res.data as Map<String, dynamic>);
      state = state.copyWith(groupedData: grouped, isLoading: false);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  void updateFilters(ProductFilterState filters) {
    state = state.copyWith(filters: filters);
  }

  Future<bool> updateStockAndPrice({
    required int productId,
    List<Map<String, dynamic>>? stockList,
    List<Map<String, dynamic>>? priceList,
    String mode = 'push',
  }) async {
    try {
      await apiClient.put(
        ApiEndpoints.productStockPrice(productId),
        data: {
          if (stockList != null) 'stock_list': stockList,
          if (priceList != null) 'price_list': priceList,
          'mode': mode,
        },
      );
      InterstitialAdManager.instance.suppressForRecentAction();
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<bool> pullProducts({int? storeId}) async {
    try {
      await apiClient.post(
        ApiEndpoints.productsPull,
        data: {if (storeId != null) 'store_id': storeId},
      );
      return true;
    } catch (_) {
      return false;
    }
  }
}

final productProvider =
    StateNotifierProvider<ProductNotifier, ProductState>(
  (ref) => ProductNotifier(),
);
