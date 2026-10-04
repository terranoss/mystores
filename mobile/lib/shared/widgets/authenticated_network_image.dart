import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../../core/api/api_client.dart';
import '../theme/app_theme.dart';

/// Loads a private API image using the authenticated Dio client.
class AuthenticatedNetworkImage extends StatefulWidget {
  final String path;
  final BoxFit fit;
  final double? width;
  final double? height;
  final BorderRadius? borderRadius;

  const AuthenticatedNetworkImage({
    super.key,
    required this.path,
    this.fit = BoxFit.cover,
    this.width,
    this.height,
    this.borderRadius,
  });

  @override
  State<AuthenticatedNetworkImage> createState() =>
      _AuthenticatedNetworkImageState();
}

class _AuthenticatedNetworkImageState extends State<AuthenticatedNetworkImage> {
  late Future<Uint8List> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void didUpdateWidget(covariant AuthenticatedNetworkImage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.path != widget.path) {
      _future = _load();
    }
  }

  Future<Uint8List> _load() async {
    final path = widget.path.startsWith('/api/')
        ? widget.path.substring(4)
        : widget.path;
    final res = await apiClient.get<List<int>>(
      path,
      options: Options(responseType: ResponseType.bytes),
    );
    return Uint8List.fromList(res.data ?? []);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Uint8List>(
      future: _future,
      builder: (context, snapshot) {
        Widget child;
        if (snapshot.connectionState != ConnectionState.done) {
          child = Container(
            width: widget.width,
            height: widget.height,
            color: AppColors.surfaceVariant,
            alignment: Alignment.center,
            child: const SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          );
        } else if (snapshot.hasError ||
            snapshot.data == null ||
            snapshot.data!.isEmpty) {
          child = Container(
            width: widget.width,
            height: widget.height,
            color: AppColors.surfaceVariant,
            alignment: Alignment.center,
            child: const Icon(Icons.broken_image_outlined,
                color: AppColors.textMuted),
          );
        } else {
          child = Image.memory(
            snapshot.data!,
            fit: widget.fit,
            width: widget.width,
            height: widget.height,
          );
        }
        if (widget.borderRadius != null) {
          return ClipRRect(borderRadius: widget.borderRadius!, child: child);
        }
        return child;
      },
    );
  }
}
