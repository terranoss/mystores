import 'store.dart';

class ProductVariant {
  final int id;
  final int modelId;
  final String? name;
  final String? modelSku;
  final double price;
  final int stock;

  const ProductVariant({
    required this.id,
    required this.modelId,
    this.name,
    this.modelSku,
    required this.price,
    required this.stock,
  });

  factory ProductVariant.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return ProductVariant(
      id: parseNum(json['id']).toInt(),
      modelId: parseNum(json['model_id']).toInt(),
      name: json['name'] as String?,
      modelSku: json['model_sku'] as String?,
      price: parseNum(json['price']).toDouble(),
      stock: parseNum(json['stock']).toInt(),
    );
  }

  ProductVariant copyWith({int? stock, double? price}) {
    return ProductVariant(
      id: id,
      modelId: modelId,
      name: name,
      modelSku: modelSku,
      price: price ?? this.price,
      stock: stock ?? this.stock,
    );
  }
}

class ProductStore {
  final int id;
  final MarketplaceType platform;
  final String? shopName;

  const ProductStore({
    required this.id,
    required this.platform,
    this.shopName,
  });

  factory ProductStore.fromJson(Map<String, dynamic> json) {
    return ProductStore(
      id: json['id'] as int,
      platform: marketplaceFromString(json['platform'] as String?),
      shopName: json['shop_name'] as String?,
    );
  }
}

class Product {
  final int id;
  final int storeId;
  final int itemId;
  final String? itemName;
  final String? itemSku;
  final int? categoryId;
  final double price;
  final int stock;
  final bool hasVariants;
  final String? syncedAt;
  final bool? needsPush;
  final List<ProductVariant> variants;
  final ProductStore? store;

  const Product({
    required this.id,
    required this.storeId,
    required this.itemId,
    this.itemName,
    this.itemSku,
    this.categoryId,
    required this.price,
    required this.stock,
    required this.hasVariants,
    this.syncedAt,
    this.needsPush,
    required this.variants,
    this.store,
  });

  factory Product.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return Product(
      id: parseNum(json['id']).toInt(),
      storeId: parseNum(json['store_id']).toInt(),
      itemId: parseNum(json['item_id']).toInt(),
      itemName: json['item_name'] as String?,
      itemSku: json['item_sku'] as String?,
      categoryId: json['category_id'] != null
          ? parseNum(json['category_id']).toInt()
          : null,
      price: parseNum(json['price']).toDouble(),
      stock: parseNum(json['stock']).toInt(),
      hasVariants: json['has_variants'] as bool? ?? false,
      syncedAt: json['synced_at'] as String?,
      needsPush: json['needs_push'] as bool?,
      variants: (json['variants'] as List<dynamic>? ?? [])
          .map((v) => ProductVariant.fromJson(v as Map<String, dynamic>))
          .toList(),
      store: json['store'] != null
          ? ProductStore.fromJson(json['store'] as Map<String, dynamic>)
          : null,
    );
  }
}

class PaginatedProducts {
  final List<Product> data;
  final int currentPage;
  final int lastPage;
  final int total;
  final int perPage;

  const PaginatedProducts({
    required this.data,
    required this.currentPage,
    required this.lastPage,
    required this.total,
    required this.perPage,
  });

  factory PaginatedProducts.fromJson(Map<String, dynamic> json) {
    num parseNum(dynamic val) {
      if (val is num) return val;
      if (val is String) return num.tryParse(val) ?? 0;
      return 0;
    }

    return PaginatedProducts(
      data: (json['data'] as List<dynamic>)
          .map((p) => Product.fromJson(p as Map<String, dynamic>))
          .toList(),
      currentPage: parseNum(json['current_page']).toInt(),
      lastPage: parseNum(json['last_page']).toInt(),
      total: parseNum(json['total']).toInt(),
      perPage: parseNum(json['per_page']).toInt(),
    );
  }
}

class GroupedProductGroup {
  final String sku;
  final List<Product> products;
  final int totalStock;

  const GroupedProductGroup({
    required this.sku,
    required this.products,
    required this.totalStock,
  });

  factory GroupedProductGroup.fromJson(Map<String, dynamic> json) {
    return GroupedProductGroup(
      sku: json['sku'] as String,
      products: (json['products'] as List<dynamic>)
          .map((p) => Product.fromJson(p as Map<String, dynamic>))
          .toList(),
      totalStock: json['total_stock'] as int,
    );
  }
}

class GroupedResponse {
  final List<GroupedProductGroup> grouped;
  final List<Product> ungrouped;
  final int total;

  const GroupedResponse({
    required this.grouped,
    required this.ungrouped,
    required this.total,
  });

  factory GroupedResponse.fromJson(Map<String, dynamic> json) {
    return GroupedResponse(
      grouped: (json['grouped'] as List<dynamic>)
          .map((g) =>
              GroupedProductGroup.fromJson(g as Map<String, dynamic>))
          .toList(),
      ungrouped: (json['ungrouped'] as List<dynamic>)
          .map((p) => Product.fromJson(p as Map<String, dynamic>))
          .toList(),
      total: json['total'] as int,
    );
  }
}
