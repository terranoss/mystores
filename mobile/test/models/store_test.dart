import 'package:flutter_test/flutter_test.dart';
import 'package:mystores/core/models/store.dart';

void main() {
  group('ShopeeStore.fromJson', () {
    test('parses integer id and shop_id', () {
      final store = ShopeeStore.fromJson({
        'id': 42,
        'shop_id': 123456,
        'platform': 'shopee',
        'shop_name': 'Test Store',
        'is_active': true,
        'connection_status': 'active',
        'token_health': 'healthy',
        'requires_reauthorization': false,
        'linked_at': '2026-01-01T00:00:00Z',
      });

      expect(store.id, 42);
      expect(store.shopId, 123456);
    });

    test('parses string id and shop_id', () {
      final store = ShopeeStore.fromJson({
        'id': '42',
        'shop_id': '123456',
        'platform': 'shopee',
        'shop_name': 'Test Store',
        'is_active': true,
        'connection_status': 'active',
        'token_health': 'healthy',
        'requires_reauthorization': false,
        'linked_at': '2026-01-01T00:00:00Z',
      });

      expect(store.id, 42);
      expect(store.shopId, 123456);
    });

    test('parses double id and shop_id', () {
      final store = ShopeeStore.fromJson({
        'id': 42.0,
        'shop_id': 123456.0,
        'platform': 'shopee',
        'shop_name': 'Test Store',
        'is_active': true,
        'connection_status': 'active',
        'token_health': 'healthy',
        'requires_reauthorization': false,
        'linked_at': '2026-01-01T00:00:00Z',
      });

      expect(store.id, 42);
      expect(store.shopId, 123456);
    });

    test('falls back to 0 for null id and shop_id', () {
      final store = ShopeeStore.fromJson({
        'id': null,
        'shop_id': null,
        'platform': 'shopee',
        'shop_name': 'Test Store',
        'is_active': true,
        'connection_status': 'active',
        'token_health': 'healthy',
        'requires_reauthorization': false,
        'linked_at': '2026-01-01T00:00:00Z',
      });

      expect(store.id, 0);
      expect(store.shopId, 0);
    });

    test('falls back to 0 for non-numeric string id and shop_id', () {
      final store = ShopeeStore.fromJson({
        'id': 'abc',
        'shop_id': 'xyz',
        'platform': 'shopee',
        'shop_name': 'Test Store',
        'is_active': true,
        'connection_status': 'active',
        'token_health': 'healthy',
        'requires_reauthorization': false,
        'linked_at': '2026-01-01T00:00:00Z',
      });

      expect(store.id, 0);
      expect(store.shopId, 0);
    });

    test('parses refresh_failure_count as int', () {
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
        'refresh_failure_count': 3,
      });

      expect(store.refreshFailureCount, 3);
    });

    test('parses refresh_failure_count as string', () {
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
        'refresh_failure_count': '5',
      });

      expect(store.refreshFailureCount, 5);
    });

    test('handles null refresh_failure_count', () {
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
        'refresh_failure_count': null,
      });

      expect(store.refreshFailureCount, isNull);
    });
  });
}
