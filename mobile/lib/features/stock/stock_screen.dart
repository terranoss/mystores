import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/models/product.dart';
import '../../core/models/store.dart';
import '../../core/providers/product_provider.dart';
import '../../core/providers/store_provider.dart';
import '../../shared/theme/app_theme.dart';
import '../../shared/widgets/app_snackbar.dart';
import '../../shared/widgets/error_retry.dart';
import '../../shared/widgets/hamburger_button.dart';
import '../../shared/widgets/status_badge.dart';

class StockScreen extends ConsumerStatefulWidget {
  const StockScreen({super.key});

  @override
  ConsumerState<StockScreen> createState() => _StockScreenState();
}

class _StockScreenState extends ConsumerState<StockScreen> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();
  Timer? _debounce;
  bool _grouped = false;

  final List<String> _sortOptions = [
    'Name A–Z',
    'Name Z–A',
    'Stock ↑',
    'Stock ↓',
    'Price ↑',
    'Price ↓',
  ];
  int _sortIndex = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(productProvider.notifier).fetchProducts();
      ref.read(storeProvider.notifier).fetchStores();
    });
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(productProvider.notifier).fetchProducts(reset: false);
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  void _onSearch(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () {
      final current = ref.read(productProvider).filters;
      ref.read(productProvider.notifier).updateFilters(
            current.copyWith(search: value),
          );
      ref.read(productProvider.notifier).fetchProducts();
    });
  }

  void _onStoreFilter(int? storeId) {
    final current = ref.read(productProvider).filters;
    ref.read(productProvider.notifier).updateFilters(
          current.copyWith(
              storeId: storeId, clearStore: storeId == null),
        );
    ref.read(productProvider.notifier).fetchProducts();
  }

  void _onSort(int index) {
    setState(() => _sortIndex = index);
    final map = [
      ('item_name', 'asc'),
      ('item_name', 'desc'),
      ('stock', 'asc'),
      ('stock', 'desc'),
      ('price', 'asc'),
      ('price', 'desc'),
    ];
    final (sortBy, sortDir) = map[index];
    final current = ref.read(productProvider).filters;
    ref.read(productProvider.notifier).updateFilters(
          current.copyWith(sortBy: sortBy, sortDir: sortDir),
        );
    ref.read(productProvider.notifier).fetchProducts();
  }

  Future<void> _pullProducts() async {
    final ok = await ref.read(productProvider.notifier).pullProducts();
    if (mounted) {
      AppSnackbar.show(
        context,
        ok ? 'Sync queued successfully' : 'Sync failed',
        success: ok,
      );
    }
    if (ok) ref.read(productProvider.notifier).fetchProducts();
  }

  @override
  Widget build(BuildContext context) {
    final productState = ref.watch(productProvider);
    final storeState = ref.watch(storeProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const HamburgerButton(),
        title: const Text('Stock & Price'),
        actions: [
          IconButton(
            icon: const Icon(Icons.sync_rounded, color: Colors.white),
            tooltip: 'Sync from Shopee',
            onPressed: _pullProducts,
          ),
          IconButton(
            icon: Icon(
              _grouped ? Icons.view_list_rounded : Icons.grid_view_rounded,
              color: Colors.white,
            ),
            tooltip: _grouped ? 'Flat view' : 'Grouped by SKU',
            onPressed: () {
              setState(() => _grouped = !_grouped);
              if (_grouped) {
                ref.read(productProvider.notifier).fetchGrouped();
              } else {
                ref.read(productProvider.notifier).fetchProducts();
              }
            },
          ),
        ],
      ),
      body: Column(
        children: [
          // Search + Sort bar
          Container(
            color: AppColors.surface,
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _searchController,
                    onChanged: _onSearch,
                    decoration: const InputDecoration(
                      hintText: 'Search products…',
                      prefixIcon: Icon(Icons.search_rounded,
                          size: 20, color: AppColors.textMuted),
                      isDense: true,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                PopupMenuButton<int>(
                  initialValue: _sortIndex,
                  onSelected: _onSort,
                  icon: const Icon(Icons.sort_rounded,
                      color: AppColors.textSecondary),
                  itemBuilder: (_) => _sortOptions
                      .asMap()
                      .entries
                      .map((e) => PopupMenuItem(
                            value: e.key,
                            child: Text(e.value),
                          ))
                      .toList(),
                ),
              ],
            ),
          ),
          // Store filter chips
          storeState.when(
            data: (stores) => _StoreFilterChips(
              stores: stores,
              selectedId:
                  ref.watch(productProvider).filters.storeId,
              onSelect: _onStoreFilter,
            ),
            loading: () => const SizedBox(height: 40),
            error: (_, __) => const SizedBox(height: 8),
          ),
          const Divider(height: 1),
          // Product list
          Expanded(
            child: productState.isLoading
                ? const LoadingWidget(message: 'Loading products…')
                : productState.error != null && productState.products.isEmpty
                    ? ErrorRetryWidget(
                        message: productState.error!,
                        onRetry: () =>
                            ref.read(productProvider.notifier).fetchProducts(),
                      )
                    : productState.products.isEmpty
                        ? const EmptyStateWidget(
                            icon: Icons.inventory_2_outlined,
                            title: 'No products found',
                            subtitle:
                                'Try adjusting your search or filters.',
                          )
                        : RefreshIndicator(
                            color: AppColors.accent,
                            onRefresh: () => ref
                                .read(productProvider.notifier)
                                .fetchProducts(),
                            child: ListView.builder(
                              controller: _scrollController,
                              padding: const EdgeInsets.only(
                                  top: 8, bottom: 80),
                              itemCount: productState.products.length +
                                  (productState.isLoadingMore ? 1 : 0),
                              itemBuilder: (ctx, i) {
                                if (i == productState.products.length) {
                                  return const Padding(
                                    padding: EdgeInsets.all(16),
                                    child: Center(
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: AppColors.accent,
                                      ),
                                    ),
                                  );
                                }
                                return _ProductTile(
                                  product: productState.products[i],
                                  onTap: () => _showEditSheet(
                                      productState.products[i]),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }

  void _showEditSheet(Product product) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _EditStockSheet(product: product),
    );
  }
}

// ── Store filter chips ────────────────────────────────────────

class _StoreFilterChips extends StatelessWidget {
  final List<ShopeeStore> stores;
  final int? selectedId;
  final void Function(int?) onSelect;

  const _StoreFilterChips({
    required this.stores,
    required this.selectedId,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 44,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        children: [
          Padding(
            padding: const EdgeInsets.only(right: 6),
            child: FilterChip(
              label: Text(
                'All Stores',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: selectedId == null
                      ? AppColors.primaryDark
                      : AppColors.textPrimary,
                ),
              ),
              selected: selectedId == null,
              onSelected: (_) => onSelect(null),
              checkmarkColor: AppColors.primaryDark,
              selectedColor: AppColors.accentLight,
              backgroundColor: AppColors.surface,
              side: BorderSide(
                color: selectedId == null ? AppColors.accent : AppColors.border,
                width: selectedId == null ? 1.5 : 1.0,
              ),
            ),
          ),
          ...stores.map(
            (s) => Padding(
              padding: const EdgeInsets.only(right: 6),
              child: FilterChip(
                label: Text(
                  s.shopName ?? 'Store #${s.shopId}',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: selectedId == s.id
                        ? AppColors.primaryDark
                        : AppColors.textPrimary,
                  ),
                ),
                selected: selectedId == s.id,
                onSelected: (_) => onSelect(s.id),
                checkmarkColor: AppColors.primaryDark,
                selectedColor: AppColors.accentLight,
                backgroundColor: AppColors.surface,
                side: BorderSide(
                  color: selectedId == s.id ? AppColors.accent : AppColors.border,
                  width: selectedId == s.id ? 1.5 : 1.0,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Product tile ──────────────────────────────────────────────

class _ProductTile extends StatelessWidget {
  final Product product;
  final VoidCallback onTap;

  const _ProductTile({required this.product, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: const BoxDecoration(
          border: Border(
              bottom: BorderSide(color: AppColors.divider)),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product.itemName ?? '(No name)',
                    style: Theme.of(context).textTheme.titleSmall,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      if (product.itemSku != null) ...[
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: AppColors.surfaceVariant,
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            product.itemSku!,
                            style:
                                Theme.of(context).textTheme.labelSmall,
                          ),
                        ),
                        const SizedBox(width: 6),
                      ],
                      Text(
                        product.store?.platform.name.toUpperCase() ?? '',
                        style: Theme.of(context)
                            .textTheme
                            .labelSmall
                            ?.copyWith(
                              color: product.store?.platform ==
                                      MarketplaceType.shopee
                                  ? const Color(0xFFFF5722)
                                  : const Color(0xFF1A73E8),
                            ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                StockBadge(stock: product.stock),
                const SizedBox(height: 4),
                Text(
                  'RM ${product.price.toStringAsFixed(2)}',
                  style: Theme.of(context).textTheme.labelMedium,
                ),
              ],
            ),
            const SizedBox(width: 8),
            const Icon(Icons.chevron_right_rounded,
                size: 20, color: AppColors.textMuted),
          ],
        ),
      ),
    );
  }
}

// ── Edit Stock & Price bottom sheet ──────────────────────────

class _EditStockSheet extends ConsumerStatefulWidget {
  final Product product;
  const _EditStockSheet({required this.product});

  @override
  ConsumerState<_EditStockSheet> createState() => _EditStockSheetState();
}

class _EditStockSheetState extends ConsumerState<_EditStockSheet> {
  late List<TextEditingController> _stockControllers;
  late List<TextEditingController> _priceControllers;
  String _mode = 'push';
  bool _isSaving = false;

  @override
  void initState() {
    super.initState();
    if (widget.product.hasVariants) {
      _stockControllers = widget.product.variants
          .map((v) => TextEditingController(text: v.stock.toString()))
          .toList();
      _priceControllers = widget.product.variants
          .map((v) =>
              TextEditingController(text: v.price.toStringAsFixed(2)))
          .toList();
    } else {
      _stockControllers = [
        TextEditingController(
            text: widget.product.stock.toString())
      ];
      _priceControllers = [
        TextEditingController(
            text: widget.product.price.toStringAsFixed(2))
      ];
    }
  }

  @override
  void dispose() {
    for (final c in _stockControllers) {
      c.dispose();
    }
    for (final c in _priceControllers) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _isSaving = true);

    List<Map<String, dynamic>>? stockList;
    List<Map<String, dynamic>>? priceList;

    if (widget.product.hasVariants) {
      stockList = widget.product.variants.asMap().entries.map((e) {
        return {
          'model_id': e.value.modelId,
          'seller_stock': [
            {'stock': int.tryParse(_stockControllers[e.key].text) ?? 0}
          ],
        };
      }).toList();
      priceList = widget.product.variants.asMap().entries.map((e) {
        return {
          'model_id': e.value.modelId,
          'original_price':
              double.tryParse(_priceControllers[e.key].text) ?? 0,
        };
      }).toList();
    } else {
      stockList = [
        {
          'seller_stock': [
            {
              'stock':
                  int.tryParse(_stockControllers[0].text) ?? 0
            }
          ]
        }
      ];
      priceList = [
        {
          'original_price':
              double.tryParse(_priceControllers[0].text) ?? 0
        }
      ];
    }

    final ok = await ref.read(productProvider.notifier).updateStockAndPrice(
          productId: widget.product.id,
          stockList: stockList,
          priceList: priceList,
          mode: _mode,
        );

    setState(() => _isSaving = false);

    if (mounted) {
      AppSnackbar.show(
        context,
        ok ? 'Updated successfully' : 'Update failed',
        success: ok,
      );
    }

    if (ok && mounted) {
      Navigator.pop(context);
      ref.read(productProvider.notifier).fetchProducts();
    }
  }

  @override
  Widget build(BuildContext context) {
    final product = widget.product;
    return DraggableScrollableSheet(
      initialChildSize: 0.65,
      maxChildSize: 0.95,
      minChildSize: 0.4,
      builder: (ctx, scrollController) => Container(
        decoration: const BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          children: [
            const SizedBox(height: 8),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: AppColors.border,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    product.itemName ?? 'Edit Product',
                    style: Theme.of(context).textTheme.titleMedium,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (product.itemSku != null)
                    Text(
                      'SKU: ${product.itemSku}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: SingleChildScrollView(
                controller: scrollController,
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Mode toggle
                    Row(
                      children: [
                        Text('Save mode:',
                            style:
                                Theme.of(context).textTheme.labelMedium),
                        const SizedBox(width: 12),
                        SegmentedButton<String>(
                          segments: const [
                            ButtonSegment(
                                value: 'push',
                                label: Text('Push to Shopee')),
                            ButtonSegment(
                                value: 'local',
                                label: Text('Local only')),
                          ],
                          selected: {_mode},
                          onSelectionChanged: (s) =>
                              setState(() => _mode = s.first),
                          style: SegmentedButton.styleFrom(
                            textStyle: const TextStyle(fontSize: 12),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                    if (!product.hasVariants) ...[
                      // Simple product fields
                      _buildSimpleFields(context),
                    ] else ...[
                      // Variant fields
                      Text('Variants',
                          style:
                              Theme.of(context).textTheme.titleSmall),
                      const SizedBox(height: 12),
                      ...product.variants.asMap().entries.map(
                        (e) => _buildVariantRow(context, e.key, e.value),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            Padding(
              padding: EdgeInsets.fromLTRB(
                  16, 8, 16, 16 + MediaQuery.of(context).viewInsets.bottom),
              child: SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  onPressed: _isSaving ? null : _save,
                  child: _isSaving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white),
                        )
                      : const Text('Save Changes'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSimpleFields(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Stock',
                  style: Theme.of(context).textTheme.labelMedium),
              const SizedBox(height: 6),
              TextField(
                controller: _stockControllers[0],
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  suffixText: 'units',
                ),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Price',
                  style: Theme.of(context).textTheme.labelMedium),
              const SizedBox(height: 6),
              TextField(
                controller: _priceControllers[0],
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(
                  prefixText: 'RM ',
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildVariantRow(
      BuildContext context, int index, ProductVariant variant) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.background,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            variant.name ?? 'Variant ${index + 1}',
            style: Theme.of(context).textTheme.labelLarge,
          ),
          if (variant.modelSku != null)
            Text(variant.modelSku!,
                style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _stockControllers[index],
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(
                    labelText: 'Stock',
                    suffixText: 'units',
                    isDense: true,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: TextField(
                  controller: _priceControllers[index],
                  keyboardType:
                      const TextInputType.numberWithOptions(decimal: true),
                  decoration: const InputDecoration(
                    labelText: 'Price',
                    prefixText: 'RM ',
                    isDense: true,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
