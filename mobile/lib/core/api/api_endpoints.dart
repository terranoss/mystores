// Core API endpoints
class ApiEndpoints {
  ApiEndpoints._();

  static const String baseUrl = '';

  // Auth
  static const String login = '/login';
  static const String logout = '/logout';
  static const String me = '/me';

  // Dashboard
  static const String dashboard = '/dashboard';
  static const String todayPickList = '/dashboard/today-picklist';
  static const String pendingPickList = '/dashboard/pending-picklist';
  static const String pickListCompletions = '/dashboard/picklist-completions';
  static String pickListPhoto(int id) => '/dashboard/picklist-photos/$id';
  static const String pickListComments = '/dashboard/picklist-comments';
  static const String pickListCommentsRead =
      '/dashboard/picklist-comments/read';
  static String pickListComment(int id) => '/dashboard/picklist-comments/$id';

  // Notifications
  static const String notifications = '/notifications';
  static const String unreadNotificationCount = '/notifications/unread-count';
  static const String markAllNotificationsRead = '/notifications/read-all';
  static String markNotificationRead(String id) => '/notifications/$id/read';
  static String deleteNotification(String id) => '/notifications/$id';

  // Shopee stores
  static const String shopeeAuthUrl = '/shopee/auth-url';
  static const String shopeeStores = '/shopee/stores';
  static String shopeeReauthorize(int id) => '/shopee/stores/$id/reauthorize';
  static String shopeeDisconnect(int id) => '/shopee/stores/$id/disconnect';
  static String shopeeUnlink(int id) => '/shopee/stores/$id';

  // Lazada stores
  static const String lazadaAuthUrl = '/lazada/auth-url';
  static String lazadaReauthorize(int id) => '/lazada/stores/$id/reauthorize';
  static String lazadaDisconnect(int id) => '/lazada/stores/$id/disconnect';
  static String lazadaUnlink(int id) => '/lazada/stores/$id';

  // Products
  static const String products = '/products';
  static const String productsGrouped = '/products/grouped';
  static String product(int id) => '/products/$id';
  static String productStock(int id) => '/products/$id/stock';
  static String productPrice(int id) => '/products/$id/price';
  static String productStockPrice(int id) => '/products/$id/stock-price';
  static const String productStockBySku = '/products/stock-by-sku';
  static const String productPriceBySku = '/products/price-by-sku';
  static const String productsPull = '/products/pull';
  static const String productsPush = '/products/push';

  // Orders
  static const String orders = '/orders';
  static String order(int id) => '/orders/$id';
  static String orderCancel(int id) => '/orders/$id/cancel';
  static String orderShip(int id) => '/orders/$id/ship';
  static String orderShippingParams(int id) =>
      '/orders/$id/shipping-parameters';
  static const String ordersSync = '/orders/sync';

  // Lazada orders
  static const String lazadaOrdersSync = '/lazada/orders/sync';
  static String lazadaOrderCancel(int id) => '/lazada/orders/$id/cancel';
  static String lazadaOrderPack(int id) => '/lazada/orders/$id/pack';
  static String lazadaOrderReadyToShip(int id) =>
      '/lazada/orders/$id/ready-to-ship';

  // Workspace
  static const String workspace = '/workspace';
  static const String workspaceActivity = '/workspace/activity';
  static const String workspaceInvites = '/workspace/invites';
  static const String workspaceInvitesAccept = '/workspace/invites/accept';
  static String workspaceMember(int id) => '/workspace/members/$id';

  // Devices (push notifications)
  static const String devices = '/devices';
  static String device(String playerId) => '/devices/$playerId';
}
