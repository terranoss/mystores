import 'package:flutter/material.dart';
import '../theme/app_theme.dart';

/// App-wide snackbar helper using Flutter's built-in ScaffoldMessenger.
/// No external dependencies — avoids cross-drive Kotlin compilation issues on Windows.
class AppSnackbar {
  AppSnackbar._();

  static void show(
    BuildContext context,
    String message, {
    bool success = true,
    Duration duration = const Duration(seconds: 3),
  }) {
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).clearSnackBars();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(
          children: [
            Icon(
              success ? Icons.check_circle_rounded : Icons.error_rounded,
              color: Colors.white,
              size: 18,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                message,
                style: const TextStyle(
                    color: Colors.white, fontWeight: FontWeight.w500),
              ),
            ),
          ],
        ),
        backgroundColor:
            success ? AppColors.success : AppColors.error,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        margin: const EdgeInsets.all(12),
        duration: duration,
      ),
    );
  }
}
