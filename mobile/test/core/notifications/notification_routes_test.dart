import 'package:flutter_test/flutter_test.dart';
import 'package:mystores/core/notifications/notification_routes.dart';

void main() {
  group('NotificationRouteResolver', () {
    test('order notifications map to /orders', () {
      expect(NotificationRouteResolver.resolveRoute(category: 'order_new'), '/orders');
      expect(NotificationRouteResolver.resolveRoute(category: 'order_status'), '/orders');
      expect(NotificationRouteResolver.resolveRoute(category: 'order_urgent'), '/orders');
    });

    test('picklist comment notifications map based on kind', () {
      expect(
        NotificationRouteResolver.resolveRoute(category: 'picklist_comment', kind: 'pending'),
        '/pick-list/pending',
      );
      expect(
        NotificationRouteResolver.resolveRoute(category: 'picklist_comment', kind: 'today'),
        '/pick-list/today',
      );
      expect(
        NotificationRouteResolver.resolveRoute(category: 'picklist_comment'),
        '/pick-list/today',
      );
    });

    test('team activity notification maps to /team', () {
      expect(NotificationRouteResolver.resolveRoute(category: 'team_activity'), '/team');
    });

    test('store reauth notification maps to /dashboard', () {
      expect(NotificationRouteResolver.resolveRoute(category: 'store_reauth'), '/dashboard');
    });

    test('unrecognized category returns null without throwing', () {
      expect(NotificationRouteResolver.resolveRoute(category: 'unknown_category'), isNull);
      expect(NotificationRouteResolver.resolveRoute(category: null), isNull);
      expect(NotificationRouteResolver.resolveRoute(category: ''), isNull);
    });
  });
}
