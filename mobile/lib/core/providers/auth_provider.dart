import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../api/api_log.dart';
import '../auth/auth_storage.dart';
import '../auth/server_storage.dart';
import '../models/dashboard.dart';
import '../push/push_service.dart';
import '../push/onesignal_push_service.dart';
import '../widgets/home_screen_widget.dart';

class AuthState {
  final AppUser? user;
  final bool isLoading;
  final bool isInitializing;
  final bool hasConfiguredStore;
  final String? storeName;
  final String? storeUrl;
  final String? error;
  final String? errorDetail;

  const AuthState({
    this.user,
    this.isLoading = false,
    this.isInitializing = true,
    this.hasConfiguredStore = false,
    this.storeName,
    this.storeUrl,
    this.error,
    this.errorDetail,
  });

  bool get isAuthenticated => user != null;

  AuthState copyWith({
    AppUser? user,
    bool? isLoading,
    bool? isInitializing,
    bool? hasConfiguredStore,
    String? storeName,
    String? storeUrl,
    String? error,
    String? errorDetail,
    bool clearUser = false,
    bool clearError = false,
  }) {
    return AuthState(
      user: clearUser ? null : user ?? this.user,
      isLoading: isLoading ?? this.isLoading,
      isInitializing: isInitializing ?? this.isInitializing,
      hasConfiguredStore: hasConfiguredStore ?? this.hasConfiguredStore,
      storeName: storeName ?? this.storeName,
      storeUrl: storeUrl ?? this.storeUrl,
      error: clearError ? null : error ?? this.error,
      errorDetail: clearError ? null : errorDetail ?? this.errorDetail,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  final PushService? pushService;

  AuthNotifier({this.pushService}) : super(const AuthState()) {
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    String? storeUrl;
    String? storeName;
    try {
      storeUrl = await ServerStorage.getStoreUrl();
      storeName = await ServerStorage.getStoreName();
    } catch (_) {}

    final hasStore = storeUrl != null && storeUrl.isNotEmpty;

    try {
      final token = await AuthStorage.getToken();
      final userJson = await AuthStorage.getUser();
      if (token != null && userJson != null) {
        final user =
            AppUser.fromJson(jsonDecode(userJson) as Map<String, dynamic>);
        state = state.copyWith(
          user: user,
          isInitializing: false,
          hasConfiguredStore: hasStore,
          storeName: storeName,
          storeUrl: storeUrl,
        );
        _registerPushDevice();
        return;
      }
    } catch (_) {
      await AuthStorage.clearAll();
    }
    state = state.copyWith(
      isInitializing: false,
      hasConfiguredStore: hasStore,
      storeName: storeName,
      storeUrl: storeUrl,
    );
  }

  Future<void> reloadStoreConfig() async {
    final storeUrl = await ServerStorage.getStoreUrl();
    final storeName = await ServerStorage.getStoreName();
    state = state.copyWith(
      hasConfiguredStore: storeUrl != null && storeUrl.isNotEmpty,
      storeName: storeName,
      storeUrl: storeUrl,
    );
  }

  Future<void> _registerPushDevice() async {
    if (pushService == null) return;
    try {
      final playerId = await pushService!.getPlayerId();
      if (playerId == null || playerId.isEmpty) return;

      final res = await apiClient.post(
        ApiEndpoints.devices,
        data: {'player_id': playerId},
      );
      final workspacePublicId = res.data['workspace_public_id'] as String?;
      if (workspacePublicId != null && workspacePublicId.isNotEmpty) {
        await pushService!.login(workspacePublicId);
        ApiLog.add('push device registered: $playerId -> $workspacePublicId');
      }
    } catch (e) {
      // Registration failures are logged and swallowed — never block entry to the app
      ApiLog.add('push device registration failed (swallowed): $e');
    }
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, clearError: true);
    final activeUrl = await ServerStorage.getStoreUrl() ?? 'unconnected';
    ApiLog.add('login attempt for $email at $activeUrl');
    try {
      final res = await apiClient.post(
        ApiEndpoints.login,
        data: {'email': email, 'password': password},
      );
      final token = res.data['token'] as String;
      final user = AppUser.fromJson(
          res.data['user'] as Map<String, dynamic>);
      await AuthStorage.saveToken(token);
      await AuthStorage.saveUser(jsonEncode(user.toJson()));
      ApiLog.add('login OK for ${user.email} (id ${user.id})');
      state = state.copyWith(user: user, isLoading: false);
      _registerPushDevice();
      return true;
    } catch (e) {
      final detail = _loginErrorDetail(e);
      ApiLog.add('login FAILED — $detail');
      state = state.copyWith(
        isLoading: false,
        error: _loginErrorMessage(e),
        errorDetail: detail,
      );
      return false;
    }
  }

  String _loginErrorDetail(Object error) {
    if (error is! DioException) return '${error.runtimeType}: $error';
    final buffer = StringBuffer()
      ..write('type=${error.type.name}')
      ..write(' status=${error.response?.statusCode ?? '-'}')
      ..write(' url=${error.requestOptions.uri}');
    if (error.error != null) buffer.write(' cause=${error.error}');
    final data = error.response?.data;
    if (data != null) {
      final body = data.toString();
      buffer.write(
        ' body=${body.length > 300 ? '${body.substring(0, 300)}…' : body}',
      );
    }
    return buffer.toString();
  }

  String _loginErrorMessage(Object error) {
    if (error is! DioException) return 'Login failed. Please try again.';
    final status = error.response?.statusCode;
    final data = error.response?.data;
    if (data is Map && data['message'] is String) {
      return data['message'] as String;
    }
    if (status == 429) {
      return 'Too many login attempts. Please wait a minute and try again.';
    }
    if (status != null) {
      return 'Login failed (server error $status).';
    }
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return 'Connection timed out. Check your internet and try again.';
      default:
        return 'Cannot reach the server. Check your internet connection.';
    }
  }

  /// Involuntary local logout (e.g. 401 interceptor) — contractually local-only:
  /// detaches local push identity, never calls DELETE /devices or POST /logout.
  Future<void> forceLocalLogout() async {
    try {
      await pushService?.logout();
    } catch (_) {}
    await AuthStorage.clearAll();
    await HomeScreenWidget.clear();
    state = const AuthState(isInitializing: false);
  }

  /// Explicit user logout:
  /// 1. Calls DELETE /devices/:playerId while token is still valid.
  /// 2. Calls POST /logout.
  /// 3. Clears local session and calls pushService.logout().
  Future<void> logout() async {
    if (pushService != null) {
      try {
        final playerId = await pushService!.getPlayerId();
        if (playerId != null && playerId.isNotEmpty) {
          await apiClient.delete(ApiEndpoints.device(playerId));
        }
      } catch (e) {
        ApiLog.add('device unregister error (swallowed): $e');
      }
    }
    try {
      await apiClient.post(ApiEndpoints.logout);
    } catch (_) {}
    await forceLocalLogout();
  }

  void clearError() {
    state = state.copyWith(clearError: true);
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>(
  (ref) => AuthNotifier(pushService: ref.watch(pushServiceProvider)),
);
