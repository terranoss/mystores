import 'package:dio/dio.dart';
import '../auth/auth_storage.dart';
import 'api_endpoints.dart';
import 'api_log.dart';

import '../auth/server_storage.dart';

/// Called once when a protected request returns 401. Must clear local session only
/// (no network) — see AuthNotifier.forceLocalLogout.
void Function()? onUnauthorized;

bool _handlingUnauthorized = false;

bool _isAuthEndpoint(String path) {
  return path.endsWith(ApiEndpoints.login) ||
      path.endsWith(ApiEndpoints.logout);
}

Dio createDio() {
  final dio = Dio(
    BaseOptions(
      baseUrl: ServerStorage.defaultBaseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ),
  );

  // 1. Auth & Dynamic Host interceptor — attach Bearer token + dynamic store base URL
  dio.interceptors.add(
    InterceptorsWrapper(
      onRequest: (options, handler) async {
        // Resolve dynamic base URL if configured
        final customUrl = await ServerStorage.getStoreUrl();
        if (customUrl != null && customUrl.isNotEmpty) {
          options.baseUrl = customUrl;
        }

        final token = await AuthStorage.getToken();
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        ApiLog.add(
          '→ ${options.method} ${options.uri}'
          '${token != null ? ' (auth)' : ''}',
        );
        return handler.next(options);
      },
      onResponse: (response, handler) {
        ApiLog.add(
          '← ${response.statusCode} ${response.requestOptions.method} '
          '${response.requestOptions.uri}',
        );
        return handler.next(response);
      },
      onError: (e, handler) {
        ApiLog.add(
          '✕ ${e.type.name} ${e.requestOptions.method} ${e.requestOptions.uri} '
          'status=${e.response?.statusCode ?? '-'} '
          'msg=${e.message ?? e.error ?? 'none'}',
        );
        return handler.next(e);
      },
    ),
  );

  // 2. Response interceptor — 401 local logout (once) + GET retry on 429/5xx
  dio.interceptors.add(
    InterceptorsWrapper(
      onError: (DioException e, handler) async {
        final status = e.response?.statusCode;
        final path = e.requestOptions.path;

        // 401 on protected routes → clear local session once (never POST /logout)
        if (status == 401 && !_isAuthEndpoint(path)) {
          if (!_handlingUnauthorized) {
            _handlingUnauthorized = true;
            try {
              await AuthStorage.clearAll();
              onUnauthorized?.call();
            } finally {
              // Allow a later real session expiry after a successful re-login.
              Future.delayed(const Duration(seconds: 2), () {
                _handlingUnauthorized = false;
              });
            }
          }
          return handler.reject(e);
        }

        // Retry GET requests on 429 or 5xx (up to 3 times, exponential backoff)
        final isGet = e.requestOptions.method.toUpperCase() == 'GET';
        final shouldRetry = isGet && (status == 429 || (status ?? 0) >= 500);
        if (shouldRetry) {
          final retryCount =
              (e.requestOptions.extra['_retryCount'] as int?) ?? 0;
          if (retryCount < 3) {
            e.requestOptions.extra['_retryCount'] = retryCount + 1;
            final delay = Duration(seconds: (retryCount + 1) * 2);
            await Future.delayed(delay);
            try {
              final response = await dio.fetch(e.requestOptions);
              return handler.resolve(response);
            } on DioException catch (retryError) {
              return handler.reject(retryError);
            }
          }
        }

        return handler.next(e);
      },
    ),
  );

  return dio;
}

/// Singleton Dio instance.
final Dio apiClient = createDio();
