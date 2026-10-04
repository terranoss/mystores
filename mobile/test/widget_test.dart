import 'package:flutter_test/flutter_test.dart';
import 'package:mystores/core/models/order.dart';

void main() {
  group('OrderItem model', () {
    test('parses sku correctly from json', () {
      final item = OrderItem.fromJson({
        'id': 1,
        'item_id': 1001,
        'model_id': 2001,
        'item_name': 'ANOR - Anak Pokok Lemuni Putih ( Pokok Herba )',
        'sku': 'MCA1019',
        'quantity': 3,
        'price': '19.00',
      });

      expect(item.id, 1);
      expect(item.itemId, 1001);
      expect(item.modelId, 2001);
      expect(item.itemName, 'ANOR - Anak Pokok Lemuni Putih ( Pokok Herba )');
      expect(item.sku, 'MCA1019');
      expect(item.quantity, 3);
      expect(item.price, 19.0);
    });

    test('falls back to item_sku or model_sku', () {
      final item1 = OrderItem.fromJson({
        'id': 2,
        'item_id': 1002,
        'item_name': 'Test Item 1',
        'item_sku': 'SKU-001',
        'quantity': 1,
        'price': 10.0,
      });
      expect(item1.sku, 'SKU-001');

      final item2 = OrderItem.fromJson({
        'id': 3,
        'item_id': 1003,
        'model_id': 2003,
        'item_name': 'Test Item 2',
        'model_sku': 'VAR-002',
        'quantity': 1,
        'price': 15.0,
      });
      expect(item2.sku, 'VAR-002');
    });
  });
}
