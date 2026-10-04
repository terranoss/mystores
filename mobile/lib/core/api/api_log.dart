import 'package:flutter/foundation.dart';

/// In-memory API trace kept for on-device diagnostics (visible in the UI and logcat).
class ApiLog {
  ApiLog._();

  static const int _maxEntries = 100;
  static final List<String> _entries = [];

  static List<String> get entries => List.unmodifiable(_entries);

  static void add(String message) {
    final stamp = DateTime.now().toIso8601String().substring(11, 23);
    final line = '[$stamp] $message';
    _entries.add(line);
    if (_entries.length > _maxEntries) {
      _entries.removeAt(0);
    }
    debugPrint('MyStores API $line');
  }

  static void clear() => _entries.clear();

  static String dump() => _entries.join('\n');
}
