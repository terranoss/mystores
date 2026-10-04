import 'package:dio/dio.dart';

/// Extracts a user-visible error message from API failures.
/// Never returns an empty string — always something actionable.
String apiErrorMessage(Object error, {String fallback = 'Something went wrong. Please try again.'}) {
  if (error is! DioException) {
    final text = error.toString().trim();
    return text.isEmpty ? fallback : text;
  }
  final data = error.response?.data;
  if (data is Map) {
    final message = data['message'];
    if (message is String && message.trim().isNotEmpty) {
      return message.trim();
    }
    final errors = data['errors'];
    if (errors is Map && errors.isNotEmpty) {
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) {
        return first.first.toString();
      }
      if (first is String && first.trim().isNotEmpty) {
        return first.trim();
      }
    }
  }
  final status = error.response?.statusCode;
  if (status == 422) {
    return 'Invalid input. Please check and try again.';
  }
  if (status == 429) {
    return 'Too many requests. Please wait a moment and try again.';
  }
  if (status != null && status >= 500) {
    return 'Server error ($status). Please try again.';
  }
  switch (error.type) {
    case DioExceptionType.connectionTimeout:
    case DioExceptionType.sendTimeout:
    case DioExceptionType.receiveTimeout:
      return 'Connection timed out. Check your internet and try again.';
    case DioExceptionType.connectionError:
      return 'Cannot reach the server. Check your internet connection.';
    default:
      if (status != null) {
        return 'Request failed (error $status). Please try again.';
      }
      return fallback;
  }
}

class ApiResult<T> {
  final T? data;
  final String? error;

  const ApiResult._({this.data, this.error});

  factory ApiResult.ok([T? data]) => ApiResult._(data: data);

  factory ApiResult.fail(String message) =>
      ApiResult._(error: message.trim().isEmpty ? 'Something went wrong. Please try again.' : message.trim());

  bool get isOk => error == null;

  bool get isFail => !isOk;
}
