import 'package:flutter/material.dart';

/// Lets nested AppBars open the shell drawer without fighting nested Scaffolds.
class AppShellScope extends InheritedWidget {
  final GlobalKey<ScaffoldState> scaffoldKey;

  const AppShellScope({
    super.key,
    required this.scaffoldKey,
    required super.child,
  });

  static AppShellScope? of(BuildContext context) {
    return context.dependOnInheritedWidgetOfExactType<AppShellScope>();
  }

  void openDrawer() => scaffoldKey.currentState?.openDrawer();

  @override
  bool updateShouldNotify(AppShellScope oldWidget) =>
      scaffoldKey != oldWidget.scaffoldKey;
}
