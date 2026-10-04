import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Wrapper around [FlutterSecureStorage] for managing the auth token.
class AuthStorage {
  AuthStorage._();

  static const FlutterSecureStorage _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const String _tokenKey = 'auth_token';
  static const String _userKey = 'auth_user';

  static Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  static Future<String?> getToken() => _storage.read(key: _tokenKey);

  static Future<void> deleteToken() => _storage.delete(key: _tokenKey);

  static Future<void> saveUser(String userJson) =>
      _storage.write(key: _userKey, value: userJson);

  static Future<String?> getUser() => _storage.read(key: _userKey);

  static Future<void> deleteUser() => _storage.delete(key: _userKey);

  static Future<void> clearAll() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _userKey);
  }
}
