import 'dart:io';
import 'package:dio/dio.dart';
import '../models/plant_identification_result.dart';

/// Exception for Pl@ntNet API errors
class PlantNetException implements Exception {
  final String message;
  final int? statusCode;

  PlantNetException(this.message, {this.statusCode});

  @override
  String toString() => 'PlantNetException: $message (status: $statusCode)';
}

/// Service for interacting with Pl@ntNet API
class PlantNetService {
  static const String _baseUrl = 'https://my-api.plantnet.org/v2';
  static const String _project = 'all';

  // Copied from nurserytrack project credential
  static const String _defaultApiKey = '2b10JOrURQieOwcU9sxG5YAe';

  final String _apiKey;
  final Dio _dio;

  PlantNetService({String? apiKey, Dio? dio})
      : _apiKey = apiKey ??
            const String.fromEnvironment('PLANTNET_API_KEY',
                defaultValue: _defaultApiKey),
        _dio = dio ??
            Dio(
              BaseOptions(
                connectTimeout: const Duration(seconds: 20),
                receiveTimeout: const Duration(seconds: 30),
              ),
            );

  /// Identify a plant from an image file
  ///
  /// [imageFile] - The image file to identify
  /// [organ] - Optional organ hint: 'auto', 'leaf', 'flower', 'bark', 'fruit'
  Future<PlantIdentificationResult> identifyPlant(
    File imageFile, {
    String organ = 'auto',
  }) async {
    if (_apiKey.isEmpty) {
      throw PlantNetException('API key not configured');
    }

    final url =
        '$_baseUrl/identify/$_project?api-key=$_apiKey&lang=en&nb-results=5&no-reject=false&include-related-images=false';

    try {
      final fileName = imageFile.path.split(Platform.pathSeparator).last;
      final formData = FormData.fromMap({
        'organs': organ,
        'images': await MultipartFile.fromFile(
          imageFile.path,
          filename: fileName,
        ),
      });

      final response = await _dio.post(
        url,
        data: formData,
      );

      return _handleResponse(response);
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionError ||
          e.type == DioExceptionType.connectionTimeout ||
          e.error is SocketException) {
        throw PlantNetException('No internet connection');
      }

      final statusCode = e.response?.statusCode;
      if (statusCode != null) {
        switch (statusCode) {
          case 400:
            throw PlantNetException(
              'Could not identify plant. Please ensure the image contains a plant.',
              statusCode: 400,
            );
          case 404:
            throw PlantNetException(
              'No plant species found in the image.',
              statusCode: 404,
            );
          case 429:
            throw PlantNetException(
              'Daily identification limit reached. Please try again tomorrow.',
              statusCode: 429,
            );
          case 500:
          case 502:
          case 503:
            throw PlantNetException(
              'Pl@ntNet server error. Please try again later.',
              statusCode: statusCode,
            );
          default:
            throw PlantNetException(
              'Failed to identify plant (${e.response?.statusMessage ?? e.message})',
              statusCode: statusCode,
            );
        }
      }

      throw PlantNetException('Failed to identify plant: ${e.message}');
    } catch (e) {
      if (e is PlantNetException) rethrow;
      throw PlantNetException('Failed to identify plant: $e');
    }
  }

  PlantIdentificationResult _handleResponse(Response response) {
    if (response.statusCode == 200) {
      final data = response.data;
      final Map<String, dynamic> json;
      if (data is Map<String, dynamic>) {
        json = data;
      } else if (data is Map) {
        json = Map<String, dynamic>.from(data);
      } else {
        json = <String, dynamic>{};
      }
      return PlantIdentificationResult.fromJson(json);
    }

    throw PlantNetException(
      'Unexpected response from Pl@ntNet API',
      statusCode: response.statusCode,
    );
  }
}
