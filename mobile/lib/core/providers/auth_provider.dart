import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../api/api_client.dart';
import '../api/api_endpoints.dart';
import '../api/api_log.dart';
import '../auth/auth_storage.dart';
import '../models/dashboard.dart';
import '../widgets/home_screen_widget.dart';

class AuthState {
  final AppUser? user;
  final bool isLoading;
  final bool isInitializing;
  final String? error;
  final String? errorDetail;

  const AuthState({
    this.user,
    this.isLoading = false,
    this.isInitializing = true,
    this.error,
    this.errorDetail,
  });

  bool get isAuthenticated => user != null;

  AuthState copyWith({
    AppUser? user,
    bool? isLoading,
    bool? isInitializing,
    String? error,
    String? errorDetail,
    bool clearUser = false,
    bool clearError = false,
  }) {
    return AuthState(
      user: clearUser ? null : user ?? this.user,
      isLoading: isLoading ?? this.isLoading,
      isInitializing: isInitializing ?? this.isInitializing,
      error: clearError ? null : error ?? this.error,
      errorDetail: clearError ? null : errorDetail ?? this.errorDetail,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  AuthNotifier() : super(const AuthState()) {
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    try {
      final token = await AuthStorage.getToken();
      final userJson = await AuthStorage.getUser();
      if (token != null && userJson != null) {
        final user =
            AppUser.fromJson(jsonDecode(userJson) as Map<String, dynamic>);
        state = state.copyWith(user: user, isInitializing: false);
        return;
      }
    } catch (_) {
      await AuthStorage.clearAll();
    }
    state = state.copyWith(isInitializing: false);
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, clearError: true);
    ApiLog.add('login attempt for $email at ${ApiEndpoints.baseUrl}');
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

  Future<void> forceLocalLogout() async {
    await AuthStorage.clearAll();
    await HomeScreenWidget.clear();
    state = const AuthState(isInitializing: false);
  }

  Future<void> logout() async {
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
  (ref) => AuthNotifier(),
);
