import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/providers/notification_provider.dart';
import '../../shared/theme/app_theme.dart';
import '../../shared/widgets/error_retry.dart';
import '../../shared/widgets/hamburger_button.dart';

class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(notificationProvider.notifier).fetchNotifications();
    });
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(notificationProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const HamburgerButton(),
        title: const Text('Notifications'),
        actions: [
          if (state.unreadCount > 0)
            TextButton(
              onPressed: () =>
                  ref.read(notificationProvider.notifier).markAllAsRead(),
              child: const Text(
                'Mark all read',
                style: TextStyle(color: AppColors.accent, fontSize: 13),
              ),
            ),
        ],
      ),
      body: state.isLoading
          ? const LoadingWidget(message: 'Loading notifications…')
          : state.error != null && state.notifications.isEmpty
              ? ErrorRetryWidget(
                  message: state.error!,
                  onRetry: () => ref
                      .read(notificationProvider.notifier)
                      .fetchNotifications(),
                )
              : RefreshIndicator(
                  color: AppColors.accent,
                  onRefresh: () => ref
                      .read(notificationProvider.notifier)
                      .fetchNotifications(),
                  child: state.notifications.isEmpty
                      ? const Center(
                          child: Text(
                            'No notifications yet.',
                            style: TextStyle(color: AppColors.textMuted),
                          ),
                        )
                      : ListView.separated(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          itemCount: state.notifications.length,
                          separatorBuilder: (_, __) =>
                              const Divider(height: 1, indent: 16, endIndent: 16),
                          itemBuilder: (ctx, i) {
                            final n = state.notifications[i];
                            return Dismissible(
                              key: Key(n.id),
                              direction: DismissDirection.endToStart,
                              background: Container(
                                alignment: Alignment.centerRight,
                                padding: const EdgeInsets.only(right: 20),
                                color: AppColors.error,
                                child: const Icon(Icons.delete_rounded,
                                    color: Colors.white),
                              ),
                              onDismissed: (_) => ref
                                  .read(notificationProvider.notifier)
                                  .deleteNotification(n.id),
                              child: ListTile(
                                tileColor: n.isRead
                                    ? Colors.transparent
                                    : AppColors.accentLight.withValues(alpha: 0.15),
                                leading: CircleAvatar(
                                  radius: 20,
                                  backgroundColor: _getCategoryColor(n.data.category),
                                  child: Icon(
                                    _getCategoryIcon(n.data.category),
                                    color: Colors.white,
                                    size: 18,
                                  ),
                                ),
                                title: Text(
                                  n.data.title,
                                  style: TextStyle(
                                    fontSize: 14,
                                    fontWeight: n.isRead
                                        ? FontWeight.w500
                                        : FontWeight.w700,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                                subtitle: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const SizedBox(height: 2),
                                    Text(
                                      n.data.message,
                                      style: const TextStyle(
                                        fontSize: 12,
                                        color: AppColors.textSecondary,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      n.createdAt.length >= 10
                                          ? n.createdAt.substring(0, 10)
                                          : n.createdAt,
                                      style: const TextStyle(
                                        fontSize: 10,
                                        color: AppColors.textMuted,
                                      ),
                                    ),
                                  ],
                                ),
                                onTap: () {
                                  if (!n.isRead) {
                                    ref
                                        .read(notificationProvider.notifier)
                                        .markAsRead(n.id);
                                  }
                                  final category = n.data.category;
                                  if (category == 'order_new' ||
                                      category == 'order_status' ||
                                      category == 'order_urgent') {
                                    context.go('/orders');
                                  } else if (category == 'picklist_comment') {
                                    final kind = n.data.kind;
                                    context.go(
                                      kind == 'pending'
                                          ? '/pick-list/pending'
                                          : '/pick-list/today',
                                    );
                                  } else if (category == 'team_activity') {
                                    context.go('/team');
                                  } else if (category == 'store_reauth') {
                                    context.go('/dashboard');
                                  }
                                },
                              ),
                            );
                          },
                        ),
                ),
    );
  }

  Color _getCategoryColor(String category) {
    switch (category) {
      case 'store_reauth':
        return AppColors.warning;
      case 'order_urgent':
      case 'order_status':
        return AppColors.error;
      case 'order_new':
        return AppColors.success;
      case 'picklist_comment':
        return Colors.teal;
      case 'team_activity':
        return Colors.blue;
      default:
        return AppColors.accent;
    }
  }

  IconData _getCategoryIcon(String category) {
    switch (category) {
      case 'store_reauth':
        return Icons.warning_amber_rounded;
      case 'order_urgent':
        return Icons.local_shipping_rounded;
      case 'order_new':
        return Icons.shopping_bag_rounded;
      case 'order_status':
        return Icons.sync_alt_rounded;
      case 'picklist_comment':
        return Icons.chat_bubble_rounded;
      case 'team_activity':
        return Icons.people_rounded;
      default:
        return Icons.notifications_rounded;
    }
  }
}
