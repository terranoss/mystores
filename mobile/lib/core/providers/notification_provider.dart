import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../models/notification.dart';

class NotificationState {
  final int unreadCount;
  final List<AppNotification> notifications;
  final bool isLoading;
  final String? error;

  const NotificationState({
    this.unreadCount = 0,
    this.notifications = const [],
    this.isLoading = false,
    this.error,
  });

  NotificationState copyWith({
    int? unreadCount,
    List<AppNotification>? notifications,
    bool? isLoading,
    String? error,
  }) {
    return NotificationState(
      unreadCount: unreadCount ?? this.unreadCount,
      notifications: notifications ?? this.notifications,
      isLoading: isLoading ?? this.isLoading,
      error: error,
    );
  }
}

class NotificationNotifier extends StateNotifier<NotificationState> {
  NotificationNotifier() : super(const NotificationState()) {
    fetchUnreadCount();
  }

  Future<void> fetchUnreadCount() async {
    try {
      final res = await apiClient.get(ApiEndpoints.unreadNotificationCount);
      final count = (res.data['unread_count'] as num?)?.toInt() ?? 0;
      state = state.copyWith(unreadCount: count);
    } catch (_) {}
  }

  Future<void> fetchNotifications() async {
    state = state.copyWith(isLoading: true);
    try {
      final res = await apiClient.get(ApiEndpoints.notifications);
      final count = (res.data['unread_count'] as num?)?.toInt() ?? 0;
      final rawList =
          (res.data['notifications']?['data'] as List<dynamic>? ?? []);
      final list =
          rawList.map((i) => AppNotification.fromJson(i as Map<String, dynamic>)).toList();
      state = state.copyWith(
        unreadCount: count,
        notifications: list,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<void> markAsRead(String id) async {
    try {
      final res = await apiClient.post(ApiEndpoints.markNotificationRead(id));
      final count = (res.data['unread_count'] as num?)?.toInt() ?? 0;
      final updatedList = state.notifications.map((n) {
        if (n.id == id) {
          return AppNotification(
            id: n.id,
            type: n.type,
            data: n.data,
            readAt: DateTime.now().toIso8601String(),
            createdAt: n.createdAt,
          );
        }
        return n;
      }).toList();
      state = state.copyWith(unreadCount: count, notifications: updatedList);
    } catch (_) {}
  }

  Future<void> markAllAsRead() async {
    try {
      await apiClient.post(ApiEndpoints.markAllNotificationsRead);
      final updatedList = state.notifications.map((n) {
        return AppNotification(
          id: n.id,
          type: n.type,
          data: n.data,
          readAt: DateTime.now().toIso8601String(),
          createdAt: n.createdAt,
        );
      }).toList();
      state = state.copyWith(unreadCount: 0, notifications: updatedList);
    } catch (_) {}
  }

  Future<void> deleteNotification(String id) async {
    try {
      final res = await apiClient.delete(ApiEndpoints.deleteNotification(id));
      final count = (res.data['unread_count'] as num?)?.toInt() ?? 0;
      final updatedList = state.notifications.where((n) => n.id != id).toList();
      state = state.copyWith(unreadCount: count, notifications: updatedList);
    } catch (_) {}
  }
}

final notificationProvider =
    StateNotifierProvider<NotificationNotifier, NotificationState>(
  (ref) => NotificationNotifier(),
);
