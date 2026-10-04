import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../shared/theme/app_theme.dart';
import '../../../shared/widgets/hamburger_button.dart';
import '../models/plant_identification_result.dart';
import '../services/plantnet_service.dart';
import '../widgets/organ_selector.dart';
import '../widgets/result_card.dart';

/// Provider for the PlantNet service
final plantNetServiceProvider = Provider<PlantNetService>((ref) {
  return PlantNetService();
});

/// Provider for remaining daily quota
final remainingQuotaProvider = StateProvider<int>((ref) => 500);

/// Main screen for plant identification
class PlantIdScreen extends ConsumerStatefulWidget {
  const PlantIdScreen({super.key});

  @override
  ConsumerState<PlantIdScreen> createState() => _PlantIdScreenState();
}

class _PlantIdScreenState extends ConsumerState<PlantIdScreen> {
  final ImagePicker _picker = ImagePicker();

  File? _selectedImage;
  String _selectedOrgan = 'auto';
  bool _isLoading = false;
  PlantIdentificationResult? _result;
  String? _errorMessage;

  Future<void> _pickImage(ImageSource source) async {
    try {
      final XFile? image = await _picker.pickImage(
        source: source,
        maxWidth: 1024,
        maxHeight: 1024,
        imageQuality: 85,
      );

      if (image != null) {
        setState(() {
          _selectedImage = File(image.path);
          _result = null;
          _errorMessage = null;
        });

        // Automatically trigger identification if quota is available
        final remainingQuota = ref.read(remainingQuotaProvider);
        if (remainingQuota > 0) {
          _identifyPlant();
        }
      }
    } catch (e) {
      _showError('Failed to pick image: $e');
    }
  }

  void _showImageSourceSheet() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.border,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Select Image Source',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  _buildSourceOption(
                    icon: Icons.camera_alt_rounded,
                    label: 'Camera',
                    onTap: () {
                      Navigator.pop(context);
                      _pickImage(ImageSource.camera);
                    },
                  ),
                  _buildSourceOption(
                    icon: Icons.photo_library_rounded,
                    label: 'Gallery',
                    onTap: () {
                      Navigator.pop(context);
                      _pickImage(ImageSource.gallery);
                    },
                  ),
                ],
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSourceOption({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 110,
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: AppColors.surfaceVariant,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          children: [
            Icon(icon, size: 36, color: AppColors.accent),
            const SizedBox(height: 8),
            Text(
              label,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _identifyPlant() async {
    if (_selectedImage == null) {
      _showError('Please select an image first');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final service = ref.read(plantNetServiceProvider);
      final result = await service.identifyPlant(
        _selectedImage!,
        organ: _selectedOrgan,
      );

      // Update remaining quota
      if (result.remainingRequests > 0) {
        ref.read(remainingQuotaProvider.notifier).state =
            result.remainingRequests;
      }

      setState(() {
        _result = result;
        _isLoading = false;
      });
    } on PlantNetException catch (e) {
      setState(() {
        _errorMessage = e.message;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _errorMessage = 'An unexpected error occurred: $e';
        _isLoading = false;
      });
    }
  }

  Future<void> _launchSearch(String query) async {
    final uri = Uri.parse(
      'https://www.google.com/search?q=${Uri.encodeComponent(query)}',
    );
    try {
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      } else if (mounted) {
        _showError('Could not open browser for "$query"');
      }
    } catch (e) {
      if (mounted) {
        _showError('Failed to open web search: $e');
      }
    }
  }

  void _showError(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: AppColors.error,
      ),
    );
  }

  void _reset() {
    setState(() {
      _selectedImage = null;
      _result = null;
      _errorMessage = null;
      _selectedOrgan = 'auto';
    });
  }

  @override
  Widget build(BuildContext context) {
    final remainingQuota = ref.watch(remainingQuotaProvider);
    final showQuotaWarning = remainingQuota < 50 && remainingQuota > 0;

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        leading: const HamburgerButton(),
        title: const Text('Plant ID'),
        actions: [
          if (_result != null || _selectedImage != null)
            IconButton(
              icon: const Icon(Icons.refresh_rounded, color: Colors.white),
              onPressed: _reset,
              tooltip: 'Start Over',
            ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Quota warning banner
              if (showQuotaWarning)
                Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.warningLight,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.warning.withValues(alpha: 0.5)),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.warning_amber_rounded,
                        color: AppColors.warning,
                        size: 20,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Only $remainingQuota identifications remaining today',
                          style: const TextStyle(
                            color: AppColors.warning,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

              // Quota exhausted banner
              if (remainingQuota == 0)
                Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.errorLight,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.error.withValues(alpha: 0.5)),
                  ),
                  child: const Row(
                    children: [
                      Icon(Icons.block_rounded, color: AppColors.error, size: 20),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Daily limit reached. Try again tomorrow.',
                          style: TextStyle(
                            color: AppColors.error,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

              // Image section
              _buildImageSection(),

              const SizedBox(height: 16),

              // Organ selector
              OrganSelector(
                selectedOrgan: _selectedOrgan,
                onOrganChanged: (organ) {
                  setState(() => _selectedOrgan = organ);
                },
              ),

              const SizedBox(height: 20),

              // Identify action button
              _buildIdentifyButton(),

              // Error message
              if (_errorMessage != null) ...[
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.errorLight,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.error_outline_rounded,
                        color: AppColors.error,
                        size: 20,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _errorMessage!,
                          style: const TextStyle(
                            color: AppColors.error,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],

              // Best match banner
              if (_result != null && _result!.bestMatch.isNotEmpty) ...[
                const SizedBox(height: 20),
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.successLight,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: AppColors.success.withValues(alpha: 0.3),
                    ),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.check_circle_rounded,
                        color: AppColors.success,
                        size: 24,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Best Match',
                              style: TextStyle(
                                fontSize: 12,
                                color: AppColors.success,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              _result!.bestMatch,
                              style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textPrimary,
                              ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        icon: const Icon(
                          Icons.travel_explore_rounded,
                          color: AppColors.success,
                        ),
                        tooltip: 'Cari di web',
                        onPressed: () => _launchSearch(
                          'pokok ${_result!.bestMatch.trim()}',
                        ),
                      ),
                    ],
                  ),
                ),
              ],

              // Predicted organ
              if (_result != null && _result!.predictedOrgans.isNotEmpty) ...[
                const SizedBox(height: 12),
                Text(
                  'Detected organ: ${_result!.predictedOrgans.first}',
                  style: const TextStyle(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                    fontStyle: FontStyle.italic,
                  ),
                ),
              ],

              // Results list
              if (_result != null && _result!.results.isNotEmpty) ...[
                const SizedBox(height: 20),
                const Text(
                  'IDENTIFICATION RESULTS',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 1.1,
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 10),
                ..._result!.results.asMap().entries.map((entry) {
                  return ResultCard(result: entry.value, index: entry.key);
                }),
              ],

              const SizedBox(height: 40),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildImageSection() {
    if (_selectedImage != null) {
      return GestureDetector(
        onTap: _showImageSourceSheet,
        child: Container(
          height: 240,
          width: double.infinity,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.border),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.08),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: Stack(
              fit: StackFit.expand,
              children: [
                Image.file(_selectedImage!, fit: BoxFit.cover),
                Positioned(
                  bottom: 12,
                  right: 12,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.65),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.edit_rounded, color: Colors.white, size: 14),
                        SizedBox(width: 4),
                        Text(
                          'Change',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    // Placeholder
    return GestureDetector(
      onTap: _showImageSourceSheet,
      child: Container(
        height: 220,
        width: double.infinity,
        decoration: BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: AppColors.border,
            width: 1.5,
          ),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(
                color: AppColors.accentLight,
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.add_a_photo_outlined,
                size: 32,
                color: AppColors.accent,
              ),
            ),
            const SizedBox(height: 14),
            const Text(
              'Tap to select plant photo',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Take a photo or choose from gallery',
              style: TextStyle(
                fontSize: 13,
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildIdentifyButton() {
    final remainingQuota = ref.watch(remainingQuotaProvider);
    final isDisabled =
        _selectedImage == null || _isLoading || remainingQuota == 0;

    return SizedBox(
      width: double.infinity,
      height: 50,
      child: ElevatedButton(
        onPressed: isDisabled ? null : _identifyPlant,
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.accent,
          disabledBackgroundColor: AppColors.surfaceVariant,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          elevation: isDisabled ? 0 : 2,
        ),
        child: _isLoading
            ? const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                ),
              )
            : Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    remainingQuota == 0 ? Icons.block_rounded : Icons.search_rounded,
                    color: isDisabled ? AppColors.textMuted : Colors.white,
                    size: 20,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    remainingQuota == 0
                        ? 'Daily Limit Reached'
                        : 'Identify Plant',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                      color: isDisabled ? AppColors.textMuted : Colors.white,
                    ),
                  ),
                ],
              ),
      ),
    );
  }
}
