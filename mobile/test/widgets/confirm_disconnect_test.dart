import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mystores/core/models/store.dart';
import 'package:mystores/features/dashboard/dashboard_screen.dart';

void main() {
  group('confirmStoreDisconnect', () {
    testWidgets('returns true when user confirms', (tester) async {
      final store = ShopeeStore.fromJson({
        'id': 1,
        'shop_id': 100,
        'platform': 'shopee',
        'shop_name': 'Test Store',
        'is_active': true,
        'connection_status': 'active',
        'token_health': 'healthy',
        'requires_reauthorization': false,
        'linked_at': '2026-01-01T00:00:00Z',
      });

      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: ElevatedButton(
                onPressed: () async {
                  final result = await confirmStoreDisconnect(context, store);
                  expect(result, isTrue);
                },
                child: const Text('Test'),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Test'));
      await tester.pumpAndSettle();

      expect(find.text('Disconnect Store'), findsOneWidget);
      expect(find.text('Cancel'), findsOneWidget);
      expect(find.text('Disconnect'), findsOneWidget);

      await tester.tap(find.text('Disconnect'));
      await tester.pumpAndSettle();
    });

    testWidgets('returns false when user cancels', (tester) async {
      final store = ShopeeStore.fromJson({
        'id': 1,
        'shop_id': 100,
        'platform': 'shopee',
        'shop_name': 'Test Store',
        'is_active': true,
        'connection_status': 'active',
        'token_health': 'healthy',
        'requires_reauthorization': false,
        'linked_at': '2026-01-01T00:00:00Z',
      });

      bool? dialogResult;

      await tester.pumpWidget(
        MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: ElevatedButton(
                onPressed: () async {
                  dialogResult = await confirmStoreDisconnect(context, store);
                },
                child: const Text('Test'),
              ),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Test'));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();

      expect(dialogResult, isFalse);
    });
  });
}
