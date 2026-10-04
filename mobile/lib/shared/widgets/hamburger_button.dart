import 'package:flutter/material.dart';
import 'app_shell_scope.dart';

/// Opens the shell drawer from nested AppBars.
class HamburgerButton extends StatelessWidget {
  const HamburgerButton({super.key});

  @override
  Widget build(BuildContext context) {
    return IconButton(
      icon: const Icon(Icons.menu_rounded, color: Colors.white),
      tooltip: 'Menu',
      onPressed: () => AppShellScope.of(context)?.openDrawer(),
    );
  }
}
