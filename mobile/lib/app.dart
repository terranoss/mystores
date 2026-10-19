import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'core/ads/ad_banner_widget.dart';
import 'core/ads/interstitial_ad_manager.dart';
import 'core/api/api_client.dart';
import 'core/notifications/notification_routes.dart';
import 'core/providers/auth_provider.dart';
import 'core/providers/notification_provider.dart';
import 'core/push/onesignal_push_service.dart';
import 'features/auth/login_screen.dart';
import 'features/auth/store_connect_screen.dart';
import 'features/dashboard/dashboard_screen.dart';
import 'features/orders/orders_screen.dart';
import 'features/notifications/notifications_screen.dart';
import 'features/pick_list/pick_list_screen.dart';
import 'features/stock/stock_screen.dart';
import 'features/team/team_screen.dart';
import 'shared/theme/app_theme.dart';
import 'shared/widgets/app_shell_scope.dart';

// ── Shell with bottom navigation ──────────────────────────────

final _rootNavigatorKey = GlobalKey<NavigatorState>();
final _shellNavigatorKey = GlobalKey<NavigatorState>();
final _shellScaffoldKey = GlobalKey<ScaffoldState>();

final _router = GoRouter(
  navigatorKey: _rootNavigatorKey,
  initialLocation: '/dashboard',
  redirect: (context, state) {
    final container = ProviderScope.containerOf(context);
    final auth = container.read(authProvider);

    // Wait until secure storage reads user token on app start
    if (auth.isInitializing) return null;

    // 1. If NO store is connected yet, MUST go to /connect-store
    if (!auth.hasConfiguredStore) {
      if (state.matchedLocation != '/connect-store') {
        return '/connect-store';
      }
      return null;
    }

    // 2. If store is connected, standard auth guards apply
    final isAuthRoute = state.matchedLocation == '/login' ||
        state.matchedLocation == '/connect-store';
    if (!auth.isAuthenticated && !isAuthRoute) return '/login';
    if (auth.isAuthenticated && isAuthRoute) return '/dashboard';
    return null;
  },
  routes: [
    GoRoute(
      path: '/',
      redirect: (_, __) => '/dashboard',
    ),
    GoRoute(
      path: '/login',
      builder: (_, __) => const LoginScreen(),
    ),
    GoRoute(
      path: '/connect-store',
      builder: (_, __) => const StoreConnectScreen(),
    ),
    ShellRoute(
      navigatorKey: _shellNavigatorKey,
      builder: (context, state, child) =>
          _AppShell(location: state.matchedLocation, child: child),
      routes: [
        GoRoute(
          path: '/dashboard',
          builder: (_, __) => const DashboardScreen(),
        ),
        GoRoute(
          path: '/orders',
          builder: (_, __) => const OrdersScreen(),
        ),
        GoRoute(
          path: '/stock',
          builder: (_, __) => const StockScreen(),
        ),
        GoRoute(
          path: '/team',
          builder: (_, __) => const TeamScreen(),
        ),
        GoRoute(
          path: '/notifications',
          builder: (_, __) => const NotificationsScreen(),
        ),
        GoRoute(
          path: '/pick-list/today',
          builder: (_, __) =>
              const PickListScreen(kind: PickListKind.today),
        ),
        GoRoute(
          path: '/pick-list/pending',
          builder: (_, __) =>
              const PickListScreen(kind: PickListKind.pending),
        ),
      ],
    ),
  ],
);

class MyStoresApp extends ConsumerStatefulWidget {
  const MyStoresApp({super.key});

  @override
  ConsumerState<MyStoresApp> createState() => _MyStoresAppState();
}

class _MyStoresAppState extends ConsumerState<MyStoresApp> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      // Initialize push service during app startup
      final pushService = ref.read(pushServiceProvider);
      if (pushService is OneSignalPushService) {
        pushService.onNotificationOpened = (data) {
          final category = data['category'] as String?;
          final kind = data['kind'] as String?;
          final route = NotificationRouteResolver.resolveRoute(
            category: category,
            kind: kind,
          );
          if (route != null) {
            _router.go(route);
          }
          // Refresh unread count on notification open
          ref.read(notificationProvider.notifier).fetchUnreadCount();
        };
      }
      pushService.initialize();
    });
  }

  @override
  Widget build(BuildContext context) {
    // Re-evaluates router when session restoration finishes or auth changes
    ref.listen(authProvider, (previous, next) {
      if (previous?.isInitializing == true && !next.isInitializing) {
        _router.refresh();
      } else if (previous?.isAuthenticated != next.isAuthenticated) {
        _router.refresh();
      }
    });

    // Hook 401 interceptor — local clear only (never POST /logout)
    onUnauthorized = () {
      ref.read(authProvider.notifier).forceLocalLogout();
      _router.go('/login');
    };

    return MaterialApp.router(
      title: 'MyStores',
      theme: buildAppTheme(),
      routerConfig: _router,
      debugShowCheckedModeBanner: false,
    );
  }
}

// ── Bottom navigation shell ───────────────────────────────────

class _AppShell extends StatelessWidget {
  final Widget child;
  final String location;

  const _AppShell({required this.child, required this.location});

  int _currentIndex(String location) {
    if (location.startsWith('/orders')) return 1;
    if (location.startsWith('/stock')) return 2;
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    final index = _currentIndex(location);
    final isDashboard = location == '/dashboard';

    return AppShellScope(
      scaffoldKey: _shellScaffoldKey,
      child: Scaffold(
        key: _shellScaffoldKey,
        drawer: const _AppDrawer(),
        body: child,
        bottomNavigationBar: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (isDashboard) const AdBannerWidget(),
            NavigationBar(
              selectedIndex: index,
              onDestinationSelected: (i) {
                if (i != index) {
                  InterstitialAdManager.instance.showIfAllowed();
                }
                switch (i) {
                  case 0:
                    GoRouter.of(context).go('/dashboard');
                    break;
                  case 1:
                    GoRouter.of(context).go('/orders');
                    break;
                  case 2:
                    GoRouter.of(context).go('/stock');
                    break;
                }
              },
              destinations: const [
                NavigationDestination(
                  icon: Icon(Icons.home_outlined),
                  selectedIcon: Icon(Icons.home_rounded),
                  label: 'Dashboard',
                ),
                NavigationDestination(
                  icon: Icon(Icons.receipt_long_outlined),
                  selectedIcon: Icon(Icons.receipt_long_rounded),
                  label: 'Orders',
                ),
                NavigationDestination(
                  icon: Icon(Icons.inventory_2_outlined),
                  selectedIcon: Icon(Icons.inventory_2_rounded),
                  label: 'Stock',
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _AppDrawer extends StatelessWidget {
  const _AppDrawer();

  void _go(BuildContext context, String path) {
    Navigator.of(context).pop();
    GoRouter.of(context).go(path);
  }

  @override
  Widget build(BuildContext context) {
    return Drawer(
      child: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
              child: Text(
                'Pick lists',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
            ),
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 20),
              child: Text(
                'What to pick for fulfillment',
                style: TextStyle(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                ),
              ),
            ),
            const SizedBox(height: 12),
            const Divider(),
            ExpansionTile(
              leading: const Icon(Icons.receipt_long_outlined, color: AppColors.accent),
              title: const Text('Orders'),
              children: [
                ListTile(
                  leading: const Icon(Icons.today_rounded, color: AppColors.accent),
                  title: const Text('Today Pick List'),
                  subtitle: const Text('Orders placed today'),
                  onTap: () => _go(context, '/pick-list/today'),
                ),
                ListTile(
                  leading: const Icon(Icons.pending_actions_rounded, color: AppColors.accent),
                  title: const Text('Pending Pick List'),
                  subtitle: const Text('Awaiting fulfillment'),
                  onTap: () => _go(context, '/pick-list/pending'),
                ),
              ],
            ),
            const Spacer(),
            const Divider(height: 1),
            ListTile(
              leading: const Icon(Icons.sync_alt_rounded, color: AppColors.primaryDark),
              title: const Text('Switch Store'),
              subtitle: const Text('Connect to another server'),
              onTap: () async {
                Navigator.of(context).pop();
                // Logout local session if any and open connect screen
                final container = ProviderScope.containerOf(context);
                await container.read(authProvider.notifier).logout();
                if (context.mounted) {
                  GoRouter.of(context).push('/connect-store');
                }
              },
            ),
            const Divider(height: 1),
            const _DrawerAppVersion(),
          ],
        ),
      ),
    );
  }
}

class _DrawerAppVersion extends StatefulWidget {
  const _DrawerAppVersion();

  @override
  State<_DrawerAppVersion> createState() => _DrawerAppVersionState();
}

class _DrawerAppVersionState extends State<_DrawerAppVersion> {
  String _label = 'MyStores';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final info = await PackageInfo.fromPlatform();
      if (!mounted) return;
      setState(() {
        _label = 'MyStores v${info.version} (${info.buildNumber})';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _label = 'MyStores');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
      child: Text(
        _label,
        style: const TextStyle(
          fontSize: 12,
          color: AppColors.textMuted,
        ),
      ),
    );
  }
}
