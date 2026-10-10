import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../../core/auth/server_storage.dart';
import '../../core/providers/auth_provider.dart';
import '../../shared/theme/app_theme.dart';

class StoreConnectScreen extends ConsumerStatefulWidget {
  const StoreConnectScreen({super.key});

  @override
  ConsumerState<StoreConnectScreen> createState() => _StoreConnectScreenState();
}

class _StoreConnectScreenState extends ConsumerState<StoreConnectScreen> {
  final _urlController = TextEditingController();
  bool _isChecking = false;
  String? _errorMessage;
  bool _showScanner = false;
  MobileScannerController? _scannerController;

  @override
  void initState() {
    super.initState();
    _loadExistingUrl();
  }

  Future<void> _loadExistingUrl() async {
    final existing = await ServerStorage.getStoreUrl();
    if (existing != null && mounted) {
      // Strip /api from display
      final display = existing.replaceAll(RegExp(r'/api/?$'), '');
      _urlController.text = display;
    }
  }

  @override
  void dispose() {
    _urlController.dispose();
    _scannerController?.dispose();
    super.dispose();
  }

  Future<void> _testAndSave(String inputUrl) async {
    var url = inputUrl.trim();
    if (url.isEmpty) {
      setState(() => _errorMessage = 'Please enter or scan a store URL');
      return;
    }

    // Auto-prefix https:// if missing
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      url = 'https://$url';
    }

    // Remove trailing slashes
    while (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }

    setState(() {
      _isChecking = true;
      _errorMessage = null;
    });

    try {
      final dio = Dio(
        BaseOptions(
          connectTimeout: const Duration(seconds: 10),
          receiveTimeout: const Duration(seconds: 10),
          headers: {'Accept': 'application/json'},
        ),
      );

      final pingUrl = '$url/api/store-info';
      final response = await dio.get(pingUrl);

      if (response.statusCode == 200 && response.data is Map) {
        final data = response.data as Map;
        final storeName = data['name']?.toString() ?? 'MyStore';

        await ServerStorage.saveStore(url: url, name: storeName);
        await ref.read(authProvider.notifier).reloadStoreConfig();

        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Connected to $storeName'),
              backgroundColor: AppColors.success,
            ),
          );
          context.go('/login');
        }
      } else {
        setState(() {
          _errorMessage = 'Server found, but did not respond with MyStore service.';
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Could not connect to store server. Check the URL and try again.';
      });
    } finally {
      if (mounted) {
        setState(() => _isChecking = false);
      }
    }
  }

  void _onDetect(BarcodeCapture capture) {
    if (_isChecking) return;
    final barcodes = capture.barcodes;
    for (final barcode in barcodes) {
      final val = barcode.rawValue;
      if (val != null && val.isNotEmpty) {
        setState(() {
          _showScanner = false;
        });
        _scannerController?.stop();
        _testAndSave(val);
        break;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_showScanner) {
      return Scaffold(
        backgroundColor: Colors.black,
        appBar: AppBar(
          backgroundColor: Colors.black,
          title: const Text('Scan Store QR Code', style: TextStyle(color: Colors.white)),
          leading: IconButton(
            icon: const Icon(Icons.arrow_back, color: Colors.white),
            onPressed: () {
              setState(() => _showScanner = false);
              _scannerController?.stop();
            },
          ),
        ),
        body: Stack(
          children: [
            MobileScanner(
              controller: _scannerController ??= MobileScannerController(),
              onDetect: _onDetect,
            ),
            Center(
              child: Container(
                width: 250,
                height: 250,
                decoration: BoxDecoration(
                  border: Border.all(color: AppColors.primaryDark, width: 3),
                  borderRadius: BorderRadius.circular(16),
                ),
              ),
            ),
            Positioned(
              bottom: 40,
              left: 20,
              right: 20,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.7),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Text(
                  'Point camera at the Store QR code in the MyStore web dashboard (Team > Mobile App)',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.white, fontSize: 13),
                ),
              ),
            ),
          ],
        ),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 40),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 400),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // App Icon / Graphic
                  Center(
                    child: Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(
                        color: AppColors.primaryDark,
                        borderRadius: BorderRadius.circular(18),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primaryDark.withValues(alpha: 0.3),
                            blurRadius: 16,
                            offset: const Offset(0, 6),
                          ),
                        ],
                      ),
                      child: const Icon(
                        Icons.storefront_rounded,
                        color: Colors.white,
                        size: 34,
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),

                  const Text(
                    'Connect Your Store',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Scan the Store QR code from your admin panel or enter your store domain to begin.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 14,
                      color: AppColors.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 32),

                  // Scan QR Code Button (Primary action)
                  ElevatedButton.icon(
                    onPressed: _isChecking
                        ? null
                        : () {
                            setState(() {
                              _showScanner = true;
                              _errorMessage = null;
                            });
                          },
                    icon: const Icon(Icons.qr_code_scanner_rounded, size: 22),
                    label: const Text(
                      'Scan Store QR Code',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primaryDark,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                  ),

                  const SizedBox(height: 24),
                  Row(
                    children: [
                      Expanded(child: Divider(color: Colors.grey.shade300)),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 12),
                        child: Text(
                          'OR ENTER MANUALLY',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textSecondary,
                            letterSpacing: 0.8,
                          ),
                        ),
                      ),
                      Expanded(child: Divider(color: Colors.grey.shade300)),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Manual Input Field
                  TextField(
                    controller: _urlController,
                    decoration: InputDecoration(
                      labelText: 'Store Domain / URL',
                      hintText: 'e.g. store.mydomain.com',
                      prefixIcon: const Icon(Icons.language_rounded),
                      filled: true,
                      fillColor: Colors.white,
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(14),
                        borderSide: BorderSide(color: Colors.grey.shade300),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(14),
                        borderSide: BorderSide(color: Colors.grey.shade300),
                      ),
                    ),
                    keyboardType: TextInputType.url,
                    textInputAction: TextInputAction.done,
                    onSubmitted: (val) => _testAndSave(val),
                  ),

                  if (_errorMessage != null) ...[
                    const SizedBox(height: 12),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.red.shade50,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.red.shade200),
                      ),
                      child: Row(
                        children: [
                          Icon(Icons.error_outline, color: Colors.red.shade700, size: 20),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _errorMessage!,
                              style: TextStyle(color: Colors.red.shade900, fontSize: 13),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],

                  const SizedBox(height: 16),
                  OutlinedButton(
                    onPressed: _isChecking ? null : () => _testAndSave(_urlController.text),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    child: _isChecking
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Connect to Store'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
