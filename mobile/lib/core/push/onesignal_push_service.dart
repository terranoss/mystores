import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';
import 'push_service.dart';

/// Default OneSignal implementation of [PushService].
class OneSignalPushService implements PushService {
  final String appId;
  NotificationClickCallback? onNotificationOpened;

  OneSignalPushService({
    this.appId = const String.fromEnvironment('ONESIGNAL_APP_ID', defaultValue: ''),
    this.onNotificationOpened,
  });

  @override
  Future<void> initialize() async {
    if (appId.isEmpty) {
      debugPrint('[OneSignalPushService] No ONESIGNAL_APP_ID provided; push disabled');
      return;
    }

    try {
      OneSignal.initialize(appId);

      // Prompt Android 13+ permission once on startup, ungated on auth
      OneSignal.Notifications.requestPermission(true);

      if (onNotificationOpened != null) {
        OneSignal.Notifications.addClickListener((event) {
          final additionalData = event.notification.additionalData;
          if (additionalData != null) {
            onNotificationOpened!(additionalData);
          }
        });
      }
    } catch (e) {
      debugPrint('[OneSignalPushService] Initialization failed: $e');
    }
  }

  @override
  Future<String?> getPlayerId() async {
    try {
      return OneSignal.User.pushSubscription.id;
    } catch (e) {
      debugPrint('[OneSignalPushService] Failed to get player id: $e');
      return null;
    }
  }

  @override
  Future<void> login(String externalId) async {
    try {
      await OneSignal.login(externalId);
    } catch (e) {
      debugPrint('[OneSignalPushService] Login failed: $e');
    }
  }

  @override
  Future<void> logout() async {
    try {
      await OneSignal.logout();
    } catch (e) {
      debugPrint('[OneSignalPushService] Logout failed: $e');
    }
  }
}

/// Fake implementation of [PushService] for synchronous testing of auth-push lifecycle ordering.
class FakePushService implements PushService {
  String? mockPlayerId = 'mock-player-id';
  final List<String> callLog = [];
  String? currentExternalId;
  bool shouldFailGetPlayerId = false;

  @override
  Future<void> initialize() async {
    callLog.add('initialize');
  }

  @override
  Future<String?> getPlayerId() async {
    callLog.add('getPlayerId');
    if (shouldFailGetPlayerId) {
      throw Exception('Player ID retrieval failure');
    }
    return mockPlayerId;
  }

  @override
  Future<void> login(String externalId) async {
    callLog.add('login:$externalId');
    currentExternalId = externalId;
  }

  @override
  Future<void> logout() async {
    callLog.add('logout');
    currentExternalId = null;
  }
}

/// Callback type for notification click handling
typedef NotificationClickCallback = void Function(Map<String, dynamic> data);

/// Riverpod provider for [PushService], can be overridden in tests.
final pushServiceProvider = Provider<PushService>((ref) {
  return OneSignalPushService();
});
