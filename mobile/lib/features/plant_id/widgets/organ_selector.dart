import 'package:flutter/material.dart';
import '../../../shared/theme/app_theme.dart';

/// Widget for selecting plant organ type for identification
class OrganSelector extends StatelessWidget {
  final String selectedOrgan;
  final ValueChanged<String> onOrganChanged;

  const OrganSelector({
    super.key,
    required this.selectedOrgan,
    required this.onOrganChanged,
  });

  static const List<Map<String, String>> _organs = [
    {'id': 'auto', 'label': 'Auto', 'icon': '🎯'},
    {'id': 'leaf', 'label': 'Leaf', 'icon': '🍃'},
    {'id': 'flower', 'label': 'Flower', 'icon': '🌸'},
    {'id': 'fruit', 'label': 'Fruit', 'icon': '🍎'},
    {'id': 'bark', 'label': 'Bark', 'icon': '🪵'},
  ];

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'SELECT ORGAN TYPE',
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.bold,
            letterSpacing: 1.1,
            color: AppColors.textSecondary,
          ),
        ),
        const SizedBox(height: 10),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: _organs.map((organ) {
              final isSelected = selectedOrgan == organ['id'];
              return Padding(
                padding: const EdgeInsets.only(right: 8),
                child: GestureDetector(
                  onTap: () => onOrganChanged(organ['id']!),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: isSelected
                          ? AppColors.accent
                          : AppColors.surfaceVariant,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(
                        color: isSelected
                            ? AppColors.accent
                            : AppColors.border,
                        width: 1,
                      ),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          organ['icon']!,
                          style: const TextStyle(fontSize: 15),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          organ['label']!,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: isSelected
                                ? FontWeight.bold
                                : FontWeight.w500,
                            color: isSelected
                                ? Colors.white
                                : AppColors.textPrimary,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }).toList(),
          ),
        ),
      ],
    );
  }
}
