import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'core/api/api_client.dart';
import 'core/providers/auth_provider.dart';
import 'features/auth/login_screen.dart';
import 'features/dashboard/dashboard_screen.dart';
import 'features/orders/orders_screen.dart';
import 'features/notifications/notifications_screen.dart';
import 'features/pick_list/pick_list_screen.dart';
import 'features/plant_id/screens/plant_id_screen.dart';
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

    final isLoggingIn = state.matchedLocation == '/login';
    if (!auth.isAuthenticated && !isLoggingIn) return '/login';
    if (auth.isAuthenticated && isLoggingIn) return '/dashboard';
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
          path: '/plant-id',
          builder: (_, __) => const PlantIdScreen(),
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

class MyStoresApp extends ConsumerWidget {
  const MyStoresApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
    if (location.startsWith('/plant-id')) return 3;
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    final index = _currentIndex(location);
    return AppShellScope(
      scaffoldKey: _shellScaffoldKey,
      child: Scaffold(
        key: _shellScaffoldKey,
        drawer: const _AppDrawer(),
        body: child,
        bottomNavigationBar: NavigationBar(
          selectedIndex: index,
          onDestinationSelected: (i) {
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
              case 3:
                GoRouter.of(context).go('/plant-id');
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
            NavigationDestination(
              icon: Icon(Icons.local_florist_outlined),
              selectedIcon: Icon(Icons.local_florist_rounded),
              label: 'Plant ID',
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
            ListTile(
              leading: const Icon(Icons.today_rounded,
                  color: AppColors.accent),
              title: const Text('Orders Today'),
              subtitle: const Text('Orders placed today'),
              onTap: () => _go(context, '/pick-list/today'),
            ),
            ListTile(
              leading: const Icon(Icons.pending_actions_rounded,
                  color: AppColors.accent),
              title: const Text('Pending Orders'),
              subtitle: const Text('Awaiting fulfillment'),
              onTap: () => _go(context, '/pick-list/pending'),
            ),
            const Spacer(),
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
