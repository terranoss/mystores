class NotificationRouteResolver {
  /// Resolves the in-app route destination given a notification category and optional kind.
  ///
  /// Returns `null` if the category is unrecognized or should not navigate.
  static String? resolveRoute({
    required String? category,
    String? kind,
  }) {
    if (category == null) return null;

    switch (category) {
      case 'order_new':
      case 'order_status':
      case 'order_urgent':
        return '/orders';

      case 'picklist_comment':
        return kind == 'pending' ? '/pick-list/pending' : '/pick-list/today';

      case 'team_activity':
        return '/team';

      case 'store_reauth':
        return '/dashboard';

      default:
        return null;
    }
  }
}
