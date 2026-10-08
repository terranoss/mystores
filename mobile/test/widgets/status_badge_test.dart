import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:mystores/core/models/store.dart';
import 'package:mystores/shared/widgets/status_badge.dart';

void main() {
  group('StatusBadge.fromConnectionStatus', () {
    test('active renders correct label and colors', () {
      final badge = StatusBadge.fromConnectionStatus(ConnectionStatus.active);
      expect(badge.label, 'Active');
      expect(badge.color, isA<Color>());
      expect(badge.bgColor, isA<Color>());
    });

    test('expiringSoon renders "Expiring"', () {
      final badge =
          StatusBadge.fromConnectionStatus(ConnectionStatus.expiringSoon);
      expect(badge.label, 'Expiring');
    });

    test('refreshFailed renders "Refresh Failed"', () {
      final badge =
          StatusBadge.fromConnectionStatus(ConnectionStatus.refreshFailed);
      expect(badge.label, 'Refresh Failed');
    });

    test('reauthorizeRequired renders "Reauthorize Required"', () {
      final badge = StatusBadge
          .fromConnectionStatus(ConnectionStatus.reauthorizeRequired);
      expect(badge.label, 'Reauthorize Required');
    });

    test('disconnected renders "Disconnected"', () {
      final badge =
          StatusBadge.fromConnectionStatus(ConnectionStatus.disconnected);
      expect(badge.label, 'Disconnected');
    });

    test('missingToken renders "Missing Token"', () {
      final badge =
          StatusBadge.fromConnectionStatus(ConnectionStatus.missingToken);
      expect(badge.label, 'Missing Token');
    });

    test('unknown renders "Unknown"', () {
      final badge =
          StatusBadge.fromConnectionStatus(ConnectionStatus.unknown);
      expect(badge.label, 'Unknown');
    });

    test('all statuses produce non-empty labels', () {
      for (final status in ConnectionStatus.values) {
        final badge = StatusBadge.fromConnectionStatus(status);
        expect(badge.label, isNotEmpty);
        expect(badge.color, isA<Color>());
        expect(badge.bgColor, isA<Color>());
      }
    });
  });
}
