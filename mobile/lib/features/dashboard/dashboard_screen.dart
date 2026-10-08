import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/models/dashboard.dart';
import '../../core/models/store.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/providers/dashboard_provider.dart';
import '../../core/providers/notification_provider.dart';
import '../../core/providers/store_provider.dart';
import '../../shared/theme/app_theme.dart';
import '../../shared/widgets/app_snackbar.dart';
import '../../shared/widgets/error_retry.dart';
import '../../shared/widgets/hamburger_button.dart';
import '../../shared/widgets/status_badge.dart';

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen>
    with WidgetsBindingObserver {
  int _trendDays = 7;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(dashboardProvider.notifier).fetchDashboard(trendDays: 7);
      ref.read(storeProvider.notifier).fetchStores();
      ref.read(notificationProvider.notifier).fetchUnreadCount();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      final auth = ref.read(authProvider);
      if (auth.isAuthenticated) {
        ref
            .read(dashboardProvider.notifier)
            .fetchDashboard(trendDays: _trendDays);
      }
      ref.read(notificationProvider.notifier).fetchUnreadCount();
    }
  }

  Future<void> _refresh() async {
    await Future.wait([
      ref
          .read(dashboardProvider.notifier)
          .fetchDashboard(trendDays: _trendDays),
      ref.read(storeProvider.notifier).fetchStores(),
      ref.read(notificationProvider.notifier).fetchUnreadCount(),
    ]);
  }

  Future<void> _linkStore(MarketplaceType platform) async {
    final url = await ref.read(storeProvider.notifier).getAuthUrl(platform);
    if (url != null) {
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    } else if (mounted) {
      AppSnackbar.show(context, 'Failed to get auth URL', success: false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final dashState = ref.watch(dashboardProvider);
    final storeState = ref.watch(storeProvider);
    final auth = ref.watch(authProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const HamburgerButton(),
        title: Row(
          children: [
            const Icon(Icons.store_rounded,
                color: AppColors.accent, size: 22),
            const SizedBox(width: 8),
            RichText(
              text: const TextSpan(
                children: [
                  TextSpan(
                    text: 'My',
                    style: TextStyle(
                        color: AppColors.accent,
                        fontSize: 18,
                        fontWeight: FontWeight.w800),
                  ),
                  TextSpan(
                    text: 'Stores',
                    style: TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w800),
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          Consumer(
            builder: (ctx, ref, _) {
              final unread = ref.watch(notificationProvider).unreadCount;
              return Stack(
                alignment: Alignment.center,
                children: [
                  IconButton(
                    icon: const Icon(Icons.notifications_rounded,
                        color: Colors.white),
                    onPressed: () => context.push('/notifications'),
                  ),
                  if (unread > 0)
                    Positioned(
                      top: 8,
                      right: 8,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: const BoxDecoration(
                          color: AppColors.error,
                          shape: BoxShape.circle,
                        ),
                        constraints: const BoxConstraints(
                          minWidth: 16,
                          minHeight: 16,
                        ),
                        child: Text(
                          unread > 99 ? '99+' : unread.toString(),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 9,
                            fontWeight: FontWeight.bold,
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ),
                ],
              );
            },
          ),
          PopupMenuButton<String>(
            icon: const Icon(Icons.more_vert_rounded, color: Colors.white),
            onSelected: (v) {
              if (v == 'team') {
                context.go('/team');
              } else if (v == 'logout') {
                ref.read(authProvider.notifier).logout();
              }
            },
            itemBuilder: (_) => [
              const PopupMenuItem(
                value: 'team',
                child: Row(
                  children: [
                    Icon(Icons.group_outlined, size: 18),
                    SizedBox(width: 8),
                    Text('Team'),
                  ],
                ),
              ),
              PopupMenuItem(
                value: 'logout',
                child: Row(
                  children: [
                    const Icon(Icons.logout_rounded, size: 18),
                    const SizedBox(width: 8),
                    Text('Logout (${auth.user?.name ?? ''})'),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
      body: RefreshIndicator(
        color: AppColors.accent,
        onRefresh: _refresh,
        child: dashState.when(
          loading: () => const LoadingWidget(message: 'Loading dashboard…'),
          error: (e, _) => ErrorRetryWidget(
            message: e.toString(),
            onRetry: _refresh,
          ),
          data: (data) => _buildContent(context, data, storeState),
        ),
      ),
    );
  }

  Widget _buildContent(
      BuildContext context,
      DashboardData data,
      AsyncValue<List<ShopeeStore>> storeState) {
    final stats = data.stats;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Welcome
        Text('Welcome back, ${data.user.name}',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: AppColors.textSecondary,
                )),
        const SizedBox(height: 16),

        // Stat cards
        _buildStatCards(context, stats),
        const SizedBox(height: 20),

        // Sales trend chart
        if (stats.salesTrend.isNotEmpty) ...[
          _buildSalesTrendSection(context, stats),
          const SizedBox(height: 20),
        ],

        _buildCancelReasonsSection(context, stats),
        const SizedBox(height: 20),

        // Linked Stores
        _buildStoresSection(context, storeState),
        const SizedBox(height: 80),
      ],
    );
  }

  Widget _buildStatCards(BuildContext context, DashboardStats stats) {
    final cards = [
      _StatCardData(
        icon: Icons.store_rounded,
        label: 'Linked Stores',
        value: stats.storesCount.toString(),
        color: AppColors.accent,
        bgColor: AppColors.accentLight,
      ),
      _StatCardData(
        icon: Icons.receipt_long_rounded,
        label: 'Orders Today',
        value: stats.ordersToday.toString(),
        color: const Color(0xFF2563EB),
        bgColor: const Color(0xFFDBEAFE),
        onTap: () => context.push('/pick-list/today'),
      ),
      _StatCardData(
        icon: Icons.pending_actions_rounded,
        label: 'Pending',
        value: stats.pendingOrdersCount.toString(),
        color: AppColors.warning,
        bgColor: AppColors.warningLight,
        onTap: () => context.push('/pick-list/pending'),
      ),
      _StatCardData(
        icon: Icons.inventory_2_rounded,
        label: 'Low Stock',
        value: stats.lowStockItems.toString(),
        color: AppColors.error,
        bgColor: AppColors.errorLight,
      ),
    ];

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 1.25,
      ),
      itemCount: cards.length,
      itemBuilder: (ctx, i) => _StatCard(data: cards[i]),
    );
  }

  Widget _buildCancelReasonsSection(
      BuildContext context, DashboardStats stats) {
    final reasons = stats.cancelReasons;
    final total = stats.cancelReasonsTotal;
    final days = stats.trendDays ?? _trendDays;
    final maxCount = reasons.fold<int>(
      0,
      (m, r) => r.count > m ? r.count : m,
    );

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Cancel reasons',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 4),
            Text(
              total > 0
                  ? 'Last $days days · $total cancelled'
                  : 'Last $days days',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.textSecondary,
                  ),
            ),
            const SizedBox(height: 16),
            if (total == 0 || reasons.isEmpty)
              Text(
                'No cancelled orders in this period.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.textMuted,
                    ),
              )
            else
              ...reasons.map((row) {
                final share = total > 0 ? row.count / total : 0.0;
                final bar = maxCount > 0 ? row.count / maxCount : 0.0;
                return Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              row.label,
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textPrimary,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          Text(
                            '${row.count}',
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            '${(share * 100).round()}%',
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppColors.textMuted,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(999),
                        child: LinearProgressIndicator(
                          value: bar.clamp(0.0, 1.0),
                          minHeight: 8,
                          backgroundColor: AppColors.surfaceVariant,
                          color: AppColors.error.withAlpha(200),
                        ),
                      ),
                    ],
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }

  Widget _buildSalesTrendSection(
      BuildContext context, DashboardStats stats) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text('Sales Trend',
                    style: Theme.of(context).textTheme.titleMedium),
                const Spacer(),
                // Day toggle
                _TrendDayToggle(
                  selected: _trendDays,
                  onChanged: (days) {
                    setState(() => _trendDays = days);
                    ref
                        .read(dashboardProvider.notifier)
                        .fetchDashboard(trendDays: days);
                  },
                ),
              ],
            ),
            const SizedBox(height: 20),
            SizedBox(
              height: 180,
              child: _SalesChart(trend: stats.salesTrend),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStoresSection(
      BuildContext context, AsyncValue<List<ShopeeStore>> storeState) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text('Linked Stores',
                style: Theme.of(context).textTheme.titleMedium),
            const Spacer(),
            PopupMenuButton<MarketplaceType>(
              onSelected: _linkStore,
              itemBuilder: (_) => [
                const PopupMenuItem(
                  value: MarketplaceType.shopee,
                  child: Row(
                    children: [
                      Icon(Icons.add_rounded, size: 18),
                      SizedBox(width: 8),
                      Text('Link Shopee Store'),
                    ],
                  ),
                ),
                const PopupMenuItem(
                  value: MarketplaceType.lazada,
                  child: Row(
                    children: [
                      Icon(Icons.add_rounded, size: 18),
                      SizedBox(width: 8),
                      Text('Link Lazada Store'),
                    ],
                  ),
                ),
              ],
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: AppColors.primaryDark,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.add_rounded, color: Colors.white, size: 16),
                    SizedBox(width: 4),
                    Text('Link Store',
                        style:
                            TextStyle(color: Colors.white, fontSize: 13)),
                  ],
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        storeState.when(
          loading: () => const LoadingWidget(),
          error: (e, _) => ErrorRetryWidget(
            message: e.toString(),
            onRetry: () => ref.read(storeProvider.notifier).fetchStores(),
          ),
          data: (stores) => stores.isEmpty
              ? Card(
                  child: Padding(
                    padding: const EdgeInsets.all(32),
                    child: EmptyStateWidget(
                      icon: Icons.store_outlined,
                      title: 'No stores linked yet',
                      subtitle:
                          'Connect your first Shopee or Lazada store to start managing orders.',
                      action: ElevatedButton.icon(
                        onPressed: () =>
                            _linkStore(MarketplaceType.shopee),
                        icon: const Icon(Icons.add_rounded, size: 18),
                        label: const Text('Link Shopee Store'),
                      ),
                    ),
                  ),
                )
              : Column(
                  children: stores
                      .map((s) => _StoreCard(
                            store: s,
                            onReauthorize: () async {
                              final url = await ref
                                  .read(storeProvider.notifier)
                                  .reauthorizeStore(s.id, s.platform);
                              if (url != null) {
                                final uri = Uri.parse(url);
                                if (await canLaunchUrl(uri)) {
                                  await launchUrl(uri,
                                      mode:
                                          LaunchMode.externalApplication);
                                }
                              }
                            },
                            onDisconnect: () async {
                              final confirmed = await confirmStoreDisconnect(
                                  context, s);
                              if (confirmed) {
                                await ref
                                    .read(storeProvider.notifier)
                                    .disconnectStore(s.id, s.platform);
                              }
                            },
                          ))
                      .toList(),
                ),
        ),
      ],
    );
  }
}

// ── Supporting widgets ────────────────────────────────────────

class _StatCardData {
  final IconData icon;
  final String label;
  final String value;
  final Color color;
  final Color bgColor;
  final VoidCallback? onTap;

  const _StatCardData({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
    required this.bgColor,
    this.onTap,
  });
}

class _StatCard extends StatelessWidget {
  final _StatCardData data;
  const _StatCard({required this.data});

  @override
  Widget build(BuildContext context) {
    final content = Padding(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: data.bgColor,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(data.icon, color: data.color, size: 18),
          ),
          const Spacer(),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              data.value,
              maxLines: 1,
              style: const TextStyle(
                fontSize: 22,
                fontWeight: FontWeight.w800,
                color: AppColors.textPrimary,
                height: 1.05,
              ),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            data.label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );

    return Card(
      clipBehavior: Clip.antiAlias,
      child: data.onTap == null
          ? content
          : InkWell(
              onTap: data.onTap,
              child: content,
            ),
    );
  }
}

class _TrendDayToggle extends StatelessWidget {
  final int selected;
  final void Function(int) onChanged;

  const _TrendDayToggle(
      {required this.selected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [7, 14, 30].map((d) {
        final isSelected = d == selected;
        return GestureDetector(
          onTap: () => onChanged(d),
          child: Container(
            padding:
                const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            margin: const EdgeInsets.only(left: 4),
            decoration: BoxDecoration(
              color:
                  isSelected ? AppColors.primaryDark : AppColors.surfaceVariant,
              borderRadius: BorderRadius.circular(6),
            ),
            child: Text(
              '${d}d',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: isSelected ? Colors.white : AppColors.textMuted,
              ),
            ),
          ),
        );
      }).toList(),
    );
  }
}

class _SalesChart extends StatelessWidget {
  final List<SalesTrendDay> trend;
  const _SalesChart({required this.trend});

  @override
  Widget build(BuildContext context) {
    if (trend.isEmpty) return const SizedBox();

    final maxGmv =
        trend.map((d) => d.gmv).reduce((a, b) => a > b ? a : b);
    final spots = trend.asMap().entries.map((e) {
      return FlSpot(e.key.toDouble(), e.value.gmv);
    }).toList();

    return LineChart(
      LineChartData(
        gridData: FlGridData(
          show: true,
          drawVerticalLine: false,
          getDrawingHorizontalLine: (_) =>
              const FlLine(color: AppColors.divider, strokeWidth: 1),
        ),
        titlesData: FlTitlesData(
          leftTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 52,
              getTitlesWidget: (v, _) => Text(
                'RM${v.toStringAsFixed(0)}',
                style: const TextStyle(
                    fontSize: 9, color: AppColors.textMuted),
              ),
            ),
          ),
          bottomTitles: AxisTitles(
            sideTitles: SideTitles(
              showTitles: true,
              interval: trend.length > 14 ? 7 : 3,
              getTitlesWidget: (v, _) {
                final idx = v.toInt();
                if (idx >= 0 && idx < trend.length) {
                  final parts = trend[idx].date.split('-');
                  if (parts.length >= 3) {
                    return Text(
                      '${parts[2]}/${parts[1]}',
                      style: const TextStyle(
                          fontSize: 9, color: AppColors.textMuted),
    );
  }
}
                return const SizedBox();
              },
            ),
          ),
          topTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          rightTitles:
              const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        ),
        borderData: FlBorderData(show: false),
        minX: 0,
        maxX: (trend.length - 1).toDouble(),
        minY: 0,
        maxY: maxGmv * 1.2,
        lineBarsData: [
          LineChartBarData(
            spots: spots,
            isCurved: true,
            color: AppColors.accent,
            barWidth: 2.5,
            isStrokeCapRound: true,
            dotData: const FlDotData(show: false),
            belowBarData: BarAreaData(
              show: true,
              color: AppColors.accent.withAlpha(30),
            ),
          ),
        ],
      ),
    );
  }
}

class _StoreCard extends StatelessWidget {
  final ShopeeStore store;
  final VoidCallback onReauthorize;
  final VoidCallback onDisconnect;

  const _StoreCard(
      {required this.store,
      required this.onReauthorize,
      required this.onDisconnect});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            // Platform icon
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: store.platform == MarketplaceType.shopee
                    ? const Color(0xFFFF5722).withAlpha(20)
                    : const Color(0xFF1A73E8).withAlpha(20),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                store.platform == MarketplaceType.shopee
                    ? Icons.storefront_rounded
                    : Icons.shopping_bag_outlined,
                color: store.platform == MarketplaceType.shopee
                    ? const Color(0xFFFF5722)
                    : const Color(0xFF1A73E8),
                size: 22,
              ),
            ),
            const SizedBox(width: 12),
            // Store info
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    store.shopName ?? 'Store #${store.shopId}',
                    style: Theme.of(context).textTheme.titleSmall,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      StatusBadge.fromConnectionStatus(
                          store.connectionStatus),
                      const SizedBox(width: 6),
                      Text(
                        store.platform.name.toUpperCase(),
                        style:
                            Theme.of(context).textTheme.labelSmall,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            // Action menu
            PopupMenuButton<String>(
              icon: const Icon(Icons.more_vert, size: 20),
              onSelected: (value) {
                if (value == 'disconnect') {
                  onDisconnect();
                } else if (value == 'reauthorize') {
                  onReauthorize();
                }
              },
              itemBuilder: (context) => [
                const PopupMenuItem(
                  value: 'disconnect',
                  child: Text('Disconnect'),
                ),
                if (store.requiresReauthorization)
                  const PopupMenuItem(
                    value: 'reauthorize',
                    child: Text('Reauthorize'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Shows a confirmation dialog before disconnecting a store.
/// Returns `true` if the user confirms, `false` otherwise.
Future<bool> confirmStoreDisconnect(
    BuildContext context, ShopeeStore store) async {
  final result = await showDialog<bool>(
    context: context,
    builder: (context) => AlertDialog(
      title: const Text('Disconnect Store'),
      content: Text(
          'Are you sure you want to disconnect ${store.shopName ?? 'Store #${store.shopId}'}? You will need to reconnect it to sync orders and products.'),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: const Text('Cancel'),
        ),
        TextButton(
          onPressed: () => Navigator.of(context).pop(true),
          style: TextButton.styleFrom(foregroundColor: AppColors.error),
          child: const Text('Disconnect'),
        ),
      ],
    ),
  );
  return result ?? false;
}
