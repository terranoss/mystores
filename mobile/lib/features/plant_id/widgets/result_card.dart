import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../shared/theme/app_theme.dart';
import '../models/plant_identification_result.dart';

/// Widget for displaying a single species identification result
class ResultCard extends StatelessWidget {
  final SpeciesResult result;
  final int index;

  const ResultCard({super.key, required this.result, required this.index});

  Color _getConfidenceColor(double score) {
    if (score >= 0.7) return AppColors.success;
    if (score >= 0.4) return AppColors.warning;
    return AppColors.error;
  }

  Future<void> _launchSearch(BuildContext context, String query) async {
    final uri = Uri.parse(
      'https://www.google.com/search?q=${Uri.encodeComponent(query)}',
    );
    try {
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      } else if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Could not open browser for "$query"'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Failed to open web search: $e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final confidenceColor = _getConfidenceColor(result.score);
    final confidencePercent = (result.score * 100).toInt();
    final searchQuery = result.scientificName.trim().isNotEmpty
        ? 'pokok ${result.scientificName.trim()}'
        : (result.commonNames.isNotEmpty
            ? 'pokok ${result.commonNames.first}'
            : 'pokok');

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Rank and scientific name
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Rank badge
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: index == 0
                      ? AppColors.primaryDark
                      : AppColors.surfaceVariant,
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Text(
                    '${index + 1}',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: index == 0
                          ? Colors.white
                          : AppColors.textSecondary,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),

              // Scientific name and common names
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Scientific name (bold)
                    Text(
                      result.scientificName.isNotEmpty
                          ? result.scientificName
                          : 'Unknown species',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textPrimary,
                        fontStyle: result.scientificName.isEmpty
                            ? FontStyle.italic
                            : FontStyle.normal,
                      ),
                    ),

                    // Common names
                    if (result.commonNames.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Wrap(
                        spacing: 6,
                        runSpacing: 4,
                        children: result.commonNames.take(3).map((name) {
                          return Text(
                            name,
                            style: const TextStyle(
                              fontSize: 13,
                              color: AppColors.accent,
                              fontWeight: FontWeight.w500,
                            ),
                          );
                        }).toList(),
                      ),
                    ],

                    // Family and genus
                    if (result.family.isNotEmpty ||
                        result.genus.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 4,
                        children: [
                          if (result.genus.isNotEmpty)
                            _buildBadge(context, 'Genus: ${result.genus}'),
                          if (result.family.isNotEmpty)
                            _buildBadge(context, 'Family: ${result.family}'),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),

          const SizedBox(height: 14),

          // Confidence score bar
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Confidence',
                    style: TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                  Text(
                    '$confidencePercent%',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      color: confidenceColor,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: LinearProgressIndicator(
                  value: result.score,
                  backgroundColor: AppColors.surfaceVariant,
                  valueColor: AlwaysStoppedAnimation<Color>(confidenceColor),
                  minHeight: 6,
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),
          const Divider(height: 1, color: AppColors.divider),
          const SizedBox(height: 10),

          // Malay web search link
          InkWell(
            onTap: () => _launchSearch(context, searchQuery),
            borderRadius: BorderRadius.circular(8),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 2),
              child: Row(
                children: [
                  const Icon(
                    Icons.travel_explore_rounded,
                    size: 16,
                    color: AppColors.accent,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      'Cari "$searchQuery" di web',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: AppColors.accent,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 4),
                  const Icon(
                    Icons.open_in_new_rounded,
                    size: 13,
                    color: AppColors.accent,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBadge(BuildContext context, String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: AppColors.surfaceVariant,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(color: AppColors.border),
      ),
      child: Text(
        text,
        style: const TextStyle(
          fontSize: 11,
          color: AppColors.textSecondary,
        ),
      ),
    );
  }
}
