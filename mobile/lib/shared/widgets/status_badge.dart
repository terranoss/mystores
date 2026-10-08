import 'package:flutter/material.dart';
import '../../core/models/store.dart';
import '../theme/app_theme.dart';

/// Color-coded badge for store connection/token health status.
class StatusBadge extends StatelessWidget {
  final String label;
  final Color color;
  final Color bgColor;

  const StatusBadge({
    super.key,
    required this.label,
    required this.color,
    required this.bgColor,
  });

  factory StatusBadge.fromConnectionStatus(ConnectionStatus status) {
    switch (status) {
      case ConnectionStatus.active:
        return StatusBadge(
          label: 'Active',
          color: AppColors.success,
          bgColor: AppColors.successLight,
        );
      case ConnectionStatus.expiringSoon:
        return StatusBadge(
          label: 'Expiring',
          color: AppColors.warning,
          bgColor: AppColors.warningLight,
        );
      case ConnectionStatus.refreshFailed:
        return StatusBadge(
          label: 'Refresh Failed',
          color: AppColors.orange,
          bgColor: AppColors.orangeLight,
        );
      case ConnectionStatus.reauthorizeRequired:
        return StatusBadge(
          label: 'Reauthorize Required',
          color: AppColors.error,
          bgColor: AppColors.errorLight,
        );
      case ConnectionStatus.disconnected:
        return StatusBadge(
          label: 'Disconnected',
          color: AppColors.muted,
          bgColor: AppColors.mutedLight,
        );
      case ConnectionStatus.missingToken:
        return StatusBadge(
          label: 'Missing Token',
          color: AppColors.error,
          bgColor: AppColors.errorLight,
        );
      case ConnectionStatus.unknown:
        return StatusBadge(
          label: 'Unknown',
          color: AppColors.muted,
          bgColor: AppColors.mutedLight,
        );
    }
  }

  factory StatusBadge.fromOrderStatus(String status) {
    Color color;
    Color bgColor;
    String label = status.replaceAll('_', ' ');

    switch (status.toUpperCase()) {
      case 'READY_TO_SHIP':
        color = const Color(0xFF2563EB);
        bgColor = const Color(0xFFDBEAFE);
        label = 'Ready to Ship';
        break;
      case 'SHIPPED':
      case 'IN_TRANSIT':
        color = AppColors.success;
        bgColor = AppColors.successLight;
        break;
      case 'PENDING':
      case 'UNPAID':
        color = AppColors.warning;
        bgColor = AppColors.warningLight;
        label = 'Pending';
        break;
      case 'COMPLETED':
      case 'DELIVERED':
        color = const Color(0xFF059669);
        bgColor = const Color(0xFFD1FAE5);
        break;
      case 'CANCELLED':
        color = AppColors.error;
        bgColor = AppColors.errorLight;
        break;
      case 'RETRY_SHIP':
        color = AppColors.orange;
        bgColor = AppColors.orangeLight;
        break;
      default:
        color = AppColors.muted;
        bgColor = AppColors.mutedLight;
    }

    return StatusBadge(label: label, color: color, bgColor: bgColor);
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: color.withAlpha(51)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

/// Color-coded stock level badge.
class StockBadge extends StatelessWidget {
  final int stock;

  const StockBadge({super.key, required this.stock});

  @override
  Widget build(BuildContext context) {
    Color color;
    Color bgColor;

    if (stock > 10) {
      color = AppColors.success;
      bgColor = AppColors.successLight;
    } else if (stock >= 4) {
      color = AppColors.warning;
      bgColor = AppColors.warningLight;
    } else {
      color = AppColors.error;
      bgColor = AppColors.errorLight;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: color.withAlpha(51)),
      ),
      child: Text(
        stock.toString(),
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
