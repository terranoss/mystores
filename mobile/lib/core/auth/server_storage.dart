import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Manages active self-hosted store domain and server configuration.
class ServerStorage {
  ServerStorage._();

  static const FlutterSecureStorage _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const String _storeUrlKey = 'active_store_url';
  static const String _storeNameKey = 'active_store_name';

  /// Saves the validated store base URL (e.g. "https://store.domain.com/api")
  static Future<void> saveStore({
    required String url,
    String? name,
  }) async {
    // Sanitize URL: ensure it ends with /api
    var sanitized = url.trim();
    if (sanitized.endsWith('/')) {
      sanitized = sanitized.substring(0, sanitized.length - 1);
    }
    if (!sanitized.endsWith('/api')) {
      sanitized = '$sanitized/api';
    }

    await _storage.write(key: _storeUrlKey, value: sanitized);
    if (name != null) {
      await _storage.write(key: _storeNameKey, value: name);
    }
  }

  /// Returns the currently active store base URL, or null if no store is configured
  static Future<String?> getStoreUrl() => _storage.read(key: _storeUrlKey);

  /// Returns human-readable store name (e.g. "Acme Supermart")
  static Future<String?> getStoreName() => _storage.read(key: _storeNameKey);

  /// Clears store configuration (used when staff taps "Switch Store")
  static Future<void> clearStore() async {
    await _storage.delete(key: _storeUrlKey);
    await _storage.delete(key: _storeNameKey);
  }
}
