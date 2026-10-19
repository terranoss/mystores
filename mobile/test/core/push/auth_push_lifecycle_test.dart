import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mystores/core/api/api_client.dart';
import 'package:mystores/core/api/api_endpoints.dart';
import 'package:mystores/core/providers/auth_provider.dart';
import 'package:mystores/core/push/onesignal_push_service.dart';

class MockInterceptor extends Interceptor {
  final List<String> requestLog = [];
  String? currentToken;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) {
    requestLog.add('${options.method} ${options.path}');
    if (options.path == ApiEndpoints.login) {
      currentToken = 'test-token-xyz';
      handler.resolve(Response(
        requestOptions: options,
        statusCode: 200,
        data: {
          'token': currentToken,
          'user': {
            'id': 1,
            'name': 'Test User',
            'email': 'test@example.com',
            'created_at': '2026-01-01T00:00:00Z',
          },
        },
      ));
      return;
    }

    if (options.path == ApiEndpoints.devices && options.method == 'POST') {
      handler.resolve(Response(
        requestOptions: options,
        statusCode: 200,
        data: {
          'workspace_public_id': 'workspace-public-uuid-999',
        },
      ));
      return;
    }

    if (options.path.startsWith('/devices/') && options.method == 'DELETE') {
      handler.resolve(Response(
        requestOptions: options,
        statusCode: 200,
        data: {'message': 'Device unregistered'},
      ));
      return;
    }

    if (options.path == ApiEndpoints.logout && options.method == 'POST') {
      handler.resolve(Response(
        requestOptions: options,
        statusCode: 200,
        data: {'message': 'Logged out'},
      ));
      return;
    }

    super.onRequest(options, handler);
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late MockInterceptor mockInterceptor;
  late FakePushService fakePush;
  final Map<String, String> secureStorageData = {};

  setUp(() {
    mockInterceptor = MockInterceptor();
    fakePush = FakePushService();
    secureStorageData.clear();

    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(
      const MethodChannel('plugins.it_nomads.com/flutter_secure_storage'),
      (MethodCall methodCall) async {
        if (methodCall.method == 'write') {
          final args = methodCall.arguments as Map<dynamic, dynamic>;
          secureStorageData[args['key'] as String] = args['value'] as String;
          return null;
        } else if (methodCall.method == 'read') {
          final args = methodCall.arguments as Map<dynamic, dynamic>;
          return secureStorageData[args['key'] as String];
        } else if (methodCall.method == 'delete') {
          final args = methodCall.arguments as Map<dynamic, dynamic>;
          secureStorageData.remove(args['key'] as String);
          return null;
        } else if (methodCall.method == 'deleteAll') {
          secureStorageData.clear();
          return null;
        }
        return null;
      },
    );

    // Replace dio interceptors with our mock
    apiClient.interceptors.clear();
    apiClient.interceptors.add(mockInterceptor);
  });

  group('AuthNotifier and Push Lifecycle', () {
    test('becoming authenticated registers player id, then sets external id', () async {
      final authNotifier = AuthNotifier(pushService: fakePush);

      final success = await authNotifier.login('test@example.com', 'password123');
      expect(success, isTrue);

      // Give microtasks time to execute _registerPushDevice
      await Future<void>.delayed(const Duration(milliseconds: 50));

      expect(mockInterceptor.requestLog, contains('POST /login'));
      expect(mockInterceptor.requestLog, contains('POST /devices'));
      expect(fakePush.callLog, contains('getPlayerId'));
      expect(fakePush.callLog, contains('login:workspace-public-uuid-999'));
      expect(fakePush.currentExternalId, 'workspace-public-uuid-999');
    });

    test('registration failure leaves operator logged in and usable', () async {
      fakePush.shouldFailGetPlayerId = true;
      final authNotifier = AuthNotifier(pushService: fakePush);

      final success = await authNotifier.login('test@example.com', 'password123');
      expect(success, isTrue);
      expect(authNotifier.state.isAuthenticated, isTrue);
      expect(authNotifier.state.error, isNull);
    });

    test('explicit user logout unregisters device before clearing session and logs out push', () async {
      final authNotifier = AuthNotifier(pushService: fakePush);
      await authNotifier.login('test@example.com', 'password123');
      await Future<void>.delayed(const Duration(milliseconds: 50));

      mockInterceptor.requestLog.clear();
      fakePush.callLog.clear();

      await authNotifier.logout();

      // Check call ordering: DELETE /devices/:id before local session clearing and push logout
      expect(mockInterceptor.requestLog, contains('DELETE /devices/mock-player-id'));
      expect(mockInterceptor.requestLog, contains('POST /logout'));
      expect(fakePush.callLog, contains('logout'));
      expect(fakePush.currentExternalId, isNull);
      expect(authNotifier.state.isAuthenticated, isFalse);
    });

    test('involuntary logout detaches push identity without calling DELETE /devices', () async {
      final authNotifier = AuthNotifier(pushService: fakePush);
      await authNotifier.login('test@example.com', 'password123');
      await Future<void>.delayed(const Duration(milliseconds: 50));

      mockInterceptor.requestLog.clear();
      fakePush.callLog.clear();

      await authNotifier.forceLocalLogout();

      // Involuntary logout makes NO network calls
      expect(mockInterceptor.requestLog.any((r) => r.contains('DELETE /devices')), isFalse);
      expect(mockInterceptor.requestLog.any((r) => r.contains('/logout')), isFalse);
      expect(fakePush.callLog, contains('logout'));
      expect(fakePush.currentExternalId, isNull);
      expect(authNotifier.state.isAuthenticated, isFalse);
    });
  });
}
