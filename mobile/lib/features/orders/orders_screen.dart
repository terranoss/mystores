import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/models/order.dart';
import '../../core/models/store.dart';
import '../../core/providers/order_provider.dart';
import '../../core/providers/store_provider.dart';
import '../../shared/theme/app_theme.dart';
import '../../shared/widgets/app_snackbar.dart';
import '../../shared/widgets/error_retry.dart';
import '../../shared/widgets/hamburger_button.dart';
import '../../shared/widgets/status_badge.dart';

const _cancelReasonLabels = {
  'OUT_OF_STOCK': 'Out of Stock',
  'CUSTOMER_REQUEST': 'Customer Request',
  'UNDELIVERABLE_AREA': 'Undeliverable Area',
  'COD_NOT_SUPPORTED': 'COD Not Supported',
  'Out of stock': 'Out of stock',
  'Wrong price': 'Wrong price',
  'Seller out of stock': 'Seller out of stock',
  'Unable to deliver': 'Unable to deliver',
};

String _formatCancelReason(String? raw) {
  if (raw == null || raw.trim().isEmpty) return '—';
  final value = raw.trim();
  return _cancelReasonLabels[value] ?? value.replaceAll('_', ' ');
}

class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});

  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen> {
  final _scrollController = ScrollController();

  static const List<String> _statuses = [
    'ALL',
    'PENDING',
    'READY_TO_SHIP',
    'SHIPPED',
    'COMPLETED',
    'CANCELLED',
    'RETRY_SHIP',
    'IN_TRANSIT',
    'UNPAID',
  ];

  static const List<String> _urgencies = [
    'all',
    'overdue',
    'due_today',
    'due_48h',
  ];

  static const List<String> _urgencyLabels = [
    'All',
    'Overdue',
    'Due Today',
    'Due 48h',
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(orderProvider.notifier).fetchOrders();
      ref.read(storeProvider.notifier).fetchStores();
    });
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      ref.read(orderProvider.notifier).fetchOrders(reset: false);
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _setStatus(String status) {
    final current = ref.read(orderProvider).filters;
    ref.read(orderProvider.notifier).updateFilters(
          current.copyWith(
            status: status == 'ALL' ? null : status,
            clearStatus: status == 'ALL',
          ),
        );
    ref.read(orderProvider.notifier).fetchOrders();
  }

  void _setUrgency(String urgency) {
    final current = ref.read(orderProvider).filters;
    ref.read(orderProvider.notifier).updateFilters(
          current.copyWith(
            urgency: urgency == 'all' ? null : urgency,
            clearUrgency: urgency == 'all',
          ),
        );
    ref.read(orderProvider.notifier).fetchOrders();
  }

  void _setPlatform(MarketplaceType? platform) {
    final current = ref.read(orderProvider).filters;
    ref.read(orderProvider.notifier).updateFilters(
          current.copyWith(
            platform: platform,
            clearPlatform: platform == null,
          ),
        );
    ref.read(orderProvider.notifier).fetchOrders();
  }

  Future<void> _syncOrders() async {
    final ok = await ref.read(orderProvider.notifier).syncOrders();
    if (mounted) {
      AppSnackbar.show(
        context,
        ok ? 'Sync queued' : 'Sync failed',
        success: ok,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final orderState = ref.watch(orderProvider);
    ref.watch(storeProvider); // watched for side effects (store data available)
    final filters = orderState.filters;

    return Scaffold(
      appBar: AppBar(
        leading: const HamburgerButton(),
        title: const Text('Orders'),
        actions: [
          IconButton(
            icon: const Icon(Icons.sync_rounded, color: Colors.white),
            onPressed: _syncOrders,
            tooltip: 'Sync orders',
          ),
        ],
      ),
      body: Column(
        children: [
          // Filters
          Container(
            color: AppColors.surface,
            child: Column(
              children: [
                // Platform filter
                _FilterChipRow<MarketplaceType?>(
                  label: 'Platform:',
                  items: const [null, MarketplaceType.shopee, MarketplaceType.lazada],
                  itemLabel: (p) => p == null
                      ? 'All'
                      : p.name[0].toUpperCase() + p.name.substring(1),
                  selected: filters.platform,
                  onSelect: _setPlatform,
                ),
                // Status filter
                _FilterChipRow<String>(
                  label: 'Status:',
                  items: _statuses,
                  itemLabel: (s) =>
                      s == 'ALL' ? 'All' : s.replaceAll('_', ' '),
                  selected: filters.status ?? 'ALL',
                  onSelect: _setStatus,
                ),
                // Urgency filter
                _FilterChipRow<String>(
                  label: 'Urgency:',
                  items: _urgencies,
                  itemLabel: (u) => _urgencyLabels[_urgencies.indexOf(u)],
                  selected: filters.urgency ?? 'all',
                  onSelect: _setUrgency,
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          // Order list
          Expanded(
            child: orderState.isLoading
                ? const LoadingWidget(message: 'Loading orders…')
                : orderState.error != null && orderState.orders.isEmpty
                    ? ErrorRetryWidget(
                        message: orderState.error!,
                        onRetry: () =>
                            ref.read(orderProvider.notifier).fetchOrders(),
                      )
                    : orderState.orders.isEmpty
                        ? const EmptyStateWidget(
                            icon: Icons.receipt_long_outlined,
                            title: 'No orders found',
                            subtitle: 'Try adjusting your filters.',
                          )
                        : RefreshIndicator(
                            color: AppColors.accent,
                            onRefresh: () =>
                                ref.read(orderProvider.notifier).fetchOrders(),
                            child: ListView.builder(
                              controller: _scrollController,
                              padding: const EdgeInsets.only(
                                  top: 8, bottom: 80),
                              itemCount: orderState.orders.length +
                                  (orderState.isLoadingMore ? 1 : 0),
                              itemBuilder: (ctx, i) {
                                if (i == orderState.orders.length) {
                                  return const Padding(
                                    padding: EdgeInsets.all(16),
                                    child: Center(
                                      child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                          color: AppColors.accent),
                                    ),
                                  );
                                }
                                return _OrderTile(
                                  order: orderState.orders[i],
                                  onTap: () => _showDetail(
                                      orderState.orders[i]),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }

  void _showDetail(Order order) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _OrderDetailSheet(order: order),
    );
  }
}

// ── Filter chip row ───────────────────────────────────────────

class _FilterChipRow<T> extends StatelessWidget {
  final String label;
  final List<T> items;
  final String Function(T) itemLabel;
  final T selected;
  final void Function(T) onSelect;

  const _FilterChipRow({
    required this.label,
    required this.items,
    required this.itemLabel,
    required this.selected,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 44,
      child: Row(
        children: [
          Padding(
            padding: const EdgeInsets.only(left: 12),
            child: Text(label,
                style: Theme.of(context).textTheme.labelSmall),
          ),
          Expanded(
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding:
                  const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
              children: items
                  .map(
                    (item) => Padding(
                      padding: const EdgeInsets.only(right: 6),
                      child: FilterChip(
                        label: Text(
                          itemLabel(item),
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: selected == item
                                ? AppColors.primaryDark
                                : AppColors.textPrimary,
                          ),
                        ),
                        selected: selected == item,
                        onSelected: (_) => onSelect(item),
                        checkmarkColor: AppColors.primaryDark,
                        selectedColor: AppColors.accentLight,
                        backgroundColor: AppColors.surface,
                        side: BorderSide(
                          color: selected == item
                              ? AppColors.accent
                              : AppColors.border,
                          width: selected == item ? 1.5 : 1.0,
                        ),
                        materialTapTargetSize:
                            MaterialTapTargetSize.shrinkWrap,
                        visualDensity: VisualDensity.compact,
                      ),
                    ),
                  )
                  .toList(),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Order tile ────────────────────────────────────────────────

class _OrderTile extends StatelessWidget {
  final Order order;
  final VoidCallback onTap;

  const _OrderTile({required this.order, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final isOverdue = order.isOverdue &&
        !['COMPLETED', 'CANCELLED', 'SHIPPED'].contains(order.orderStatus);

    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: isOverdue ? AppColors.errorLight.withAlpha(80) : null,
          border: const Border(
              bottom: BorderSide(color: AppColors.divider)),
        ),
        child: Row(
          children: [
            // Platform icon
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: order.store?.platform == MarketplaceType.lazada
                    ? const Color(0xFF1A73E8).withAlpha(20)
                    : const Color(0xFFFF5722).withAlpha(20),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(
                order.store?.platform == MarketplaceType.lazada
                    ? Icons.shopping_bag_outlined
                    : Icons.storefront_rounded,
                size: 18,
                color: order.store?.platform == MarketplaceType.lazada
                    ? const Color(0xFF1A73E8)
                    : const Color(0xFFFF5722),
              ),
            ),
            const SizedBox(width: 10),
            // Order info
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        order.orderSn,
                        style: Theme.of(context)
                            .textTheme
                            .titleSmall
                            ?.copyWith(fontSize: 13),
                      ),
                      const Spacer(),
                      StatusBadge.fromOrderStatus(order.orderStatus),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          order.store?.shopName ?? '',
                          style: Theme.of(context).textTheme.bodySmall,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      Text(
                        'RM ${order.totalAmount.toStringAsFixed(2)}',
                        style: Theme.of(context)
                            .textTheme
                            .labelMedium
                            ?.copyWith(
                                color: AppColors.textPrimary,
                                fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                  if (order.shipByDate != null && isOverdue)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        '⚠ Ship by ${order.shipByDate!.substring(0, 10)}',
                        style: const TextStyle(
                            color: AppColors.error,
                            fontSize: 11,
                            fontWeight: FontWeight.w600),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(width: 4),
            const Icon(Icons.chevron_right_rounded,
                size: 20, color: AppColors.textMuted),
          ],
        ),
      ),
    );
  }
}

// ── Order detail bottom sheet ─────────────────────────────────

class _OrderDetailSheet extends StatelessWidget {
  final Order order;
  const _OrderDetailSheet({required this.order});

  @override
  Widget build(BuildContext context) {
    final canCancel = !['COMPLETED', 'CANCELLED'].contains(order.orderStatus);
    return DraggableScrollableSheet(
      initialChildSize: 0.65,
      maxChildSize: 0.95,
      minChildSize: 0.4,
      builder: (ctx, sc) => Container(
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
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(order.orderSn,
                            style:
                                Theme.of(context).textTheme.titleMedium),
                        Text(order.store?.shopName ?? '',
                            style: Theme.of(context).textTheme.bodySmall),
                      ],
                    ),
                  ),
                  StatusBadge.fromOrderStatus(order.orderStatus),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                controller: sc,
                padding: const EdgeInsets.all(16),
                children: [
                  _InfoRow('Buyer',
                      order.buyerUsername ?? '—'),
                  _InfoRow('Ordered At',
                      order.orderedAt?.substring(0, 10) ?? '—'),
                  if (order.shipByDate != null)
                    _InfoRow('Ship By',
                        order.shipByDate!.substring(0, 10),
                        urgent: order.isOverdue),
                  _InfoRow('Total',
                      'RM ${order.totalAmount.toStringAsFixed(2)}'),
                  if (order.shippingAddress != null)
                    _InfoRow('Address', order.shippingAddress!),
                  if (order.cancelReason != null &&
                      order.cancelReason!.trim().isNotEmpty)
                    _InfoRow(
                      'Cancel reason',
                      _formatCancelReason(order.cancelReason),
                    ),
                  const SizedBox(height: 16),
                  Text('Items',
                      style:
                          Theme.of(context).textTheme.titleSmall),
                  const SizedBox(height: 8),
                  ...order.items.map(
                    (item) => Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.background,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: AppColors.border),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.center,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.itemName ?? '(no name)',
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodyMedium
                                      ?.copyWith(
                                        fontWeight: FontWeight.w600,
                                      ),
                                ),
                                const SizedBox(height: 4),
                                Wrap(
                                  spacing: 8,
                                  crossAxisAlignment:
                                      WrapCrossAlignment.center,
                                  children: [
                                    if (item.sku != null &&
                                        item.sku!.trim().isNotEmpty)
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                            horizontal: 6, vertical: 2),
                                        decoration: BoxDecoration(
                                          color: AppColors.surfaceVariant,
                                          borderRadius:
                                              BorderRadius.circular(4),
                                          border: Border.all(
                                              color: AppColors.border),
                                        ),
                                        child: Text(
                                          item.sku!.trim(),
                                          style: const TextStyle(
                                            fontSize: 11,
                                            fontWeight: FontWeight.w600,
                                            color: AppColors.accent,
                                          ),
                                        ),
                                      ),
                                    Text(
                                      'RM ${item.price.toStringAsFixed(2)}',
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall,
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 12),
                          Text('×${item.quantity}',
                              style: Theme.of(context)
                                  .textTheme
                                  .labelLarge
                                  ?.copyWith(
                                      fontWeight: FontWeight.w700)),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            if (canCancel)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                child: Text(
                  'Cancel only on the official Shopee/Lazada seller website.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.textMuted,
                      ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;
  final bool urgent;

  const _InfoRow(this.label, this.value, {this.urgent = false});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 90,
            child: Text(label,
                style: Theme.of(context).textTheme.bodySmall),
          ),
          Expanded(
            child: Text(
              value,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: urgent ? AppColors.error : null,
                    fontWeight:
                        urgent ? FontWeight.w600 : null,
                  ),
            ),
          ),
        ],
      ),
    );
  }
}
