abstract class PushService {
  /// Initializes push notification SDK and sets up listeners/permissions.
  Future<void> initialize();

  /// Returns current push player / subscription identifier, or null if not available.
  Future<String?> getPlayerId();

  /// Logs into the push service with the workspace's public identifier as external ID.
  Future<void> login(String externalId);

  /// Logs out locally from the push service.
  Future<void> logout();
}
