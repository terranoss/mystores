enum MarketplaceType { shopee, lazada, unknown }

MarketplaceType marketplaceFromString(String? s) {
  switch (s) {
    case 'shopee':
      return MarketplaceType.shopee;
    case 'lazada':
      return MarketplaceType.lazada;
    default:
      return MarketplaceType.unknown;
  }
}

enum ConnectionStatus {
  active,
  expiringSoon,
  refreshFailed,
  reauthorizeRequired,
  disconnected,
  missingToken,
  unknown,
}

ConnectionStatus connectionStatusFromString(String? s) {
  switch (s) {
    case 'active':
      return ConnectionStatus.active;
    case 'expiring_soon':
      return ConnectionStatus.expiringSoon;
    case 'refresh_failed':
      return ConnectionStatus.refreshFailed;
    case 'reauthorize_required':
      return ConnectionStatus.reauthorizeRequired;
    case 'disconnected':
      return ConnectionStatus.disconnected;
    case 'missing_token':
      return ConnectionStatus.missingToken;
    default:
      return ConnectionStatus.unknown;
  }
}

enum TokenHealth { healthy, expiringSoon, expired, unknown }

/// Coerces an arbitrary JSON value into an int without throwing.
///
/// Upstream APIs are inconsistent about numeric id types: the same field can
/// arrive as an `int`, a numeric `String`, or `null`. A bare `as int` cast
/// throws a `TypeError` and takes down the whole store list render.
int? _parseIntOrNull(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse(value?.toString() ?? '');
}

int _parseIntOrZero(Object? value) => _parseIntOrNull(value) ?? 0;

TokenHealth tokenHealthFromString(String? s) {
  switch (s) {
    case 'healthy':
      return TokenHealth.healthy;
    case 'expiring_soon':
      return TokenHealth.expiringSoon;
    case 'expired':
      return TokenHealth.expired;
    default:
      return TokenHealth.unknown;
  }
}

class ShopeeStore {
  final int id;
  final int shopId;
  final MarketplaceType platform;
  final String? shopName;
  final String? region;
  final bool isActive;
  final ConnectionStatus connectionStatus;
  final TokenHealth tokenHealth;
  final bool requiresReauthorization;
  final String linkedAt;
  final String? lastRefreshedAt;
  final String? lastRefreshError;
  final int? refreshFailureCount;
  final String? accessTokenExpiresAt;
  final String? refreshTokenExpiresAt;

  const ShopeeStore({
    required this.id,
    required this.shopId,
    required this.platform,
    this.shopName,
    this.region,
    required this.isActive,
    required this.connectionStatus,
    required this.tokenHealth,
    required this.requiresReauthorization,
    required this.linkedAt,
    this.lastRefreshedAt,
    this.lastRefreshError,
    this.refreshFailureCount,
    this.accessTokenExpiresAt,
    this.refreshTokenExpiresAt,
  });

  factory ShopeeStore.fromJson(Map<String, dynamic> json) {
    return ShopeeStore(
      id: _parseIntOrZero(json['id']),
      shopId: _parseIntOrZero(json['shop_id']),
      platform: marketplaceFromString(json['platform'] as String?),
      shopName: json['shop_name'] as String?,
      region: json['region'] as String?,
      isActive: json['is_active'] as bool? ?? false,
      connectionStatus:
          connectionStatusFromString(json['connection_status'] as String?),
      tokenHealth: tokenHealthFromString(json['token_health'] as String?),
      requiresReauthorization:
          json['requires_reauthorization'] as bool? ?? false,
      linkedAt: json['linked_at'] as String? ?? '',
      lastRefreshedAt: json['last_refreshed_at'] as String?,
      lastRefreshError: json['last_refresh_error'] as String?,
      refreshFailureCount: _parseIntOrNull(json['refresh_failure_count']),
      accessTokenExpiresAt: json['access_token_expires_at'] as String?,
      refreshTokenExpiresAt: json['refresh_token_expires_at'] as String?,
    );
  }
}
