import 'package:home_widget/home_widget.dart';
import '../models/dashboard.dart';

/// Syncs Orders today / Pending to the Android home-screen widget.
class HomeScreenWidget {
  static const androidName = 'MyStoresWidgetProvider';
  static const qualifiedAndroidName =
      'com.melur.mystores.MyStoresWidgetProvider';

  static Future<void> syncFromDashboard(DashboardStats stats) async {
    try {
      final now = DateTime.now();
      final hh = now.hour.toString().padLeft(2, '0');
      final mm = now.minute.toString().padLeft(2, '0');
      await HomeWidget.saveWidgetData<String>(
        'orders_today',
        stats.ordersToday.toString(),
      );
      await HomeWidget.saveWidgetData<String>(
        'pending_orders',
        stats.pendingOrdersCount.toString(),
      );
      await HomeWidget.saveWidgetData<String>(
        'updated_at',
        'Updated $hh:$mm',
      );
      await HomeWidget.updateWidget(
        name: androidName,
        androidName: androidName,
        qualifiedAndroidName: qualifiedAndroidName,
      );
    } catch (_) {}
  }

  static Future<void> clear() async {
    try {
      await HomeWidget.saveWidgetData<String>('orders_today', '—');
      await HomeWidget.saveWidgetData<String>('pending_orders', '—');
      await HomeWidget.saveWidgetData<String>('updated_at', 'Sign in to refresh');
      await HomeWidget.updateWidget(
        name: androidName,
        androidName: androidName,
        qualifiedAndroidName: qualifiedAndroidName,
      );
    } catch (_) {}
  }
}
