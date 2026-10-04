import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../core/models/dashboard.dart';
import '../../core/providers/auth_provider.dart';
import '../../core/providers/dashboard_provider.dart';
import '../../shared/theme/app_theme.dart';
import '../../shared/widgets/authenticated_network_image.dart';
import '../../shared/widgets/error_retry.dart';
import '../../shared/widgets/hamburger_button.dart';

enum PickListKind { today, pending }

class PickListScreen extends ConsumerStatefulWidget {
  final PickListKind kind;

  const PickListScreen({super.key, required this.kind});

  @override
  ConsumerState<PickListScreen> createState() => _PickListScreenState();
}

class _PickListScreenState extends ConsumerState<PickListScreen> {
  PickList? _data;
  Object? _error;
  bool _loading = true;
  bool _busy = false;
  String? _busyItemKey;
  final ImagePicker _picker = ImagePicker();

  String get _title =>
      widget.kind == PickListKind.today ? 'Orders Today' : 'Pending Orders';

  String get _kindParam =>
      widget.kind == PickListKind.today ? 'today' : 'pending';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final notifier = ref.read(dashboardProvider.notifier);
      final list = widget.kind == PickListKind.today
          ? await notifier.fetchTodayPickList()
          : await notifier.fetchPendingPickList();
      if (!mounted) return;
      setState(() {
        _data = list;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e;
        _loading = false;
      });
    }
  }

  void _snack(String message, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? AppColors.error : null,
      ),
    );
  }

  Future<XFile?> _takePhoto() async {
    try {
      // Target ~100KB after server re-encode; capture already downsized here.
      return await _picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 65,
        maxWidth: 1280,
        maxHeight: 1280,
      );
    } catch (e) {
      _snack('Camera unavailable. Check camera permission.', error: true);
      return null;
    }
  }

  Future<void> _pickWithPhoto(PickListItem item) async {
    if (_busy) return;
    final photo = await _takePhoto();
    if (photo == null) return;
    setState(() {
      _busy = true;
      _busyItemKey = item.itemKey;
    });
    try {
      await ref.read(dashboardProvider.notifier).completePickListItem(
            kind: _kindParam,
            item: item,
            photoPath: photo.path,
          );
      await _load();
    } catch (e) {
      _snack('Failed to save pick photo: $e', error: true);
      await _load();
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
          _busyItemKey = null;
        });
      }
    }
  }

  Future<void> _unpick(PickListItem item) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _busyItemKey = item.itemKey;
    });
    try {
      await ref.read(dashboardProvider.notifier).uncompletePickListItem(
            kind: _kindParam,
            itemKey: item.itemKey,
          );
      await _load();
    } catch (e) {
      _snack('Failed to unpick: $e', error: true);
      await _load();
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
          _busyItemKey = null;
        });
      }
    }
  }

  Future<void> _onItemTap(PickListItem item) async {
    if (_busy) return;
    if (item.isPicked) {
      await _showItemSheet(item);
      return;
    }
    await _pickWithPhoto(item);
  }

  Future<void> _showItemSheet(PickListItem item) async {
    if (item.hasUnreadComments) {
      try {
        await ref.read(dashboardProvider.notifier).markPickListCommentsRead(
              kind: _kindParam,
              itemKey: item.itemKey,
            );
      } catch (_) {}
      if (mounted) {
        setState(() {
          final data = _data;
          if (data == null) return;
          _data = PickList(
            date: data.date,
            totalOrders: data.totalOrders,
            totalSkus: data.totalSkus,
            totalUnits: data.totalUnits,
            aggregatedItems: data.aggregatedItems
                .map(
                  (i) => i.itemKey == item.itemKey
                      ? i.copyWith(
                          hasUnreadComments: false,
                          unreadCommentCount: 0,
                        )
                      : i,
                )
                .toList(),
          );
        });
      }
    }
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) {
        return _PickListItemSheet(
          item: item.copyWith(
            hasUnreadComments: false,
            unreadCommentCount: 0,
          ),
          kind: _kindParam,
          currentUserId: ref.read(authProvider).user?.id,
          onReplacePhoto: () {
            Navigator.pop(ctx);
            _pickWithPhoto(item);
          },
          onUnpick: () {
            Navigator.pop(ctx);
            _unpick(item);
          },
          onPosted: () => _load(),
          onDeleted: () => _load(),
        );
      },
    );
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const HamburgerButton(),
        title: Text(_title),
      ),
      body: Stack(
        children: [
          _buildBody(),
          if (_busy)
            const Positioned(
              left: 0,
              right: 0,
              top: 0,
              child: LinearProgressIndicator(
                minHeight: 2,
                color: AppColors.accent,
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_loading && _data == null) {
      return const LoadingWidget(message: 'Loading pick list…');
    }
    if (_error != null && _data == null) {
      return ErrorRetryWidget(
        message: _error.toString(),
        onRetry: _load,
      );
    }
    final data = _data;
    if (data == null || data.aggregatedItems.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        color: AppColors.accent,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            SizedBox(
              height: MediaQuery.of(context).size.height * 0.6,
              child: EmptyStateWidget(
                icon: Icons.checklist_rounded,
                title: 'Nothing to pick',
                subtitle: widget.kind == PickListKind.today
                    ? 'No orders placed today.'
                    : 'No pending orders to fulfill.',
              ),
            ),
          ],
        ),
      );
    }
    final total = data.aggregatedItems.length;
    final done =
        data.aggregatedItems.where((i) => i.isPicked).length;
    return RefreshIndicator(
      onRefresh: _load,
      color: AppColors.accent,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        children: [
          _SummaryRow(data: data, checkedCount: done, totalCount: total),
          const SizedBox(height: 12),
          ...data.aggregatedItems.map((item) {
            return _PickListTile(
              item: item,
              checked: item.isPicked,
              busy: _busyItemKey == item.itemKey,
              onTap: () => _onItemTap(item),
              onCommentTap: () => _showItemSheet(item),
            );
          }),
        ],
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  final PickList data;
  final int checkedCount;
  final int totalCount;

  const _SummaryRow({
    required this.data,
    required this.checkedCount,
    required this.totalCount,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        children: [
          Row(
            children: [
              _Stat(label: 'Orders', value: '${data.totalOrders}'),
              _divider(),
              _Stat(label: 'SKUs', value: '${data.totalSkus}'),
              _divider(),
              _Stat(label: 'Units', value: '${data.totalUnits}'),
              _divider(),
              _Stat(label: 'Picked', value: '$checkedCount/$totalCount'),
            ],
          ),
          if (totalCount > 0) ...[
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: checkedCount / totalCount,
                minHeight: 6,
                backgroundColor: AppColors.surfaceVariant,
                color: AppColors.success,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _divider() {
    return Container(
      width: 1,
      height: 28,
      margin: const EdgeInsets.symmetric(horizontal: 8),
      color: AppColors.divider,
    );
  }
}

class _Stat extends StatelessWidget {
  final String label;
  final String value;

  const _Stat({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        children: [
          Text(
            value,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w500,
              color: AppColors.textMuted,
            ),
          ),
        ],
      ),
    );
  }
}

class _PickListTile extends StatelessWidget {
  final PickListItem item;
  final bool checked;
  final bool busy;
  final VoidCallback onTap;
  final VoidCallback onCommentTap;

  const _PickListTile({
    required this.item,
    required this.checked,
    required this.busy,
    required this.onTap,
    required this.onCommentTap,
  });

  @override
  Widget build(BuildContext context) {
    final completion = item.completion;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: busy ? null : onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.fromLTRB(8, 10, 14, 10),
          decoration: BoxDecoration(
            color: checked ? AppColors.successLight : AppColors.surface,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color:
                  checked ? AppColors.success.withAlpha(80) : AppColors.border,
            ),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (busy)
                const Padding(
                  padding: EdgeInsets.all(12),
                  child: SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                )
              else
                Checkbox(
                  value: checked,
                  onChanged: busy ? null : (_) => onTap(),
                  activeColor: AppColors.success,
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
              Container(
                width: 48,
                padding:
                    const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
                decoration: BoxDecoration(
                  color: checked
                      ? AppColors.success.withAlpha(40)
                      : AppColors.accentLight,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '×${item.quantity}',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: checked ? AppColors.success : AppColors.accent,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.itemName,
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: checked
                            ? AppColors.textMuted
                            : AppColors.textPrimary,
                        decoration:
                            checked ? TextDecoration.lineThrough : null,
                        decorationColor: AppColors.textMuted,
                      ),
                    ),
                    if (completion != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        'Picked by ${completion.pickedByName}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.success,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                    if (item.storeNames.isNotEmpty ||
                        item.platforms.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: [
                          ...item.platforms.map(
                            (p) => _Chip(label: p, filled: true),
                          ),
                          ...item.storeNames.map((s) => _Chip(label: s)),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
              IconButton(
                onPressed: busy ? null : onCommentTap,
                tooltip: item.hasUnreadComments
                    ? 'New comments'
                    : 'Comments',
                visualDensity: VisualDensity.compact,
                icon: Badge(
                  isLabelVisible:
                      item.hasUnreadComments || item.commentCount > 0,
                  label: Text(
                    item.hasUnreadComments
                        ? (item.unreadCommentCount > 0
                            ? '${item.unreadCommentCount}'
                            : '!')
                        : '${item.commentCount}',
                  ),
                  backgroundColor: item.hasUnreadComments
                      ? AppColors.error
                      : null,
                  child: Icon(
                    item.hasUnreadComments || item.commentCount > 0
                        ? Icons.chat_bubble_rounded
                        : Icons.chat_bubble_outline_rounded,
                    size: 20,
                    color: item.hasUnreadComments
                        ? AppColors.error
                        : AppColors.textMuted,
                  ),
                ),
              ),
              if (completion != null && completion.photoUrl.isNotEmpty) ...[
                const SizedBox(width: 4),
                AuthenticatedNetworkImage(
                  path: completion.photoUrl,
                  width: 48,
                  height: 48,
                  borderRadius: BorderRadius.circular(8),
                ),
              ] else if (!checked) ...[
                const SizedBox(width: 4),
                const Padding(
                  padding: EdgeInsets.only(top: 8),
                  child: Icon(
                    Icons.camera_alt_outlined,
                    size: 20,
                    color: AppColors.textMuted,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _PickListItemSheet extends ConsumerStatefulWidget {
  final PickListItem item;
  final String kind;
  final int? currentUserId;
  final VoidCallback? onReplacePhoto;
  final VoidCallback? onUnpick;
  final VoidCallback onPosted;
  final VoidCallback onDeleted;

  const _PickListItemSheet({
    required this.item,
    required this.kind,
    required this.currentUserId,
    this.onReplacePhoto,
    this.onUnpick,
    required this.onPosted,
    required this.onDeleted,
  });

  @override
  ConsumerState<_PickListItemSheet> createState() => _PickListItemSheetState();
}

class _PickListItemSheetState extends ConsumerState<_PickListItemSheet> {
  late List<PickListComment> _comments;
  final _controller = TextEditingController();
  bool _sending = false;
  int? _deletingId;

  @override
  void initState() {
    super.initState();
    _comments = List<PickListComment>.from(widget.item.comments);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final body = _controller.text.trim();
    if (body.isEmpty || _sending) return;
    setState(() => _sending = true);
    try {
      final comment = await ref.read(dashboardProvider.notifier).postPickListComment(
            kind: widget.kind,
            item: widget.item,
            body: body,
          );
      setState(() {
        _comments = [..._comments, comment];
        _controller.clear();
      });
      widget.onPosted();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to post comment: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _delete(PickListComment comment) async {
    setState(() => _deletingId = comment.id);
    try {
      await ref.read(dashboardProvider.notifier).deletePickListComment(comment.id);
      setState(() {
        _comments = _comments.where((c) => c.id != comment.id).toList();
      });
      widget.onDeleted();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to delete: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _deletingId = null);
    }
  }

  String _formatWhen(DateTime? dt) {
    if (dt == null) return '';
    final local = dt.toLocal();
    return '${local.year}-'
        '${local.month.toString().padLeft(2, '0')}-'
        '${local.day.toString().padLeft(2, '0')} '
        '${local.hour.toString().padLeft(2, '0')}:'
        '${local.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final completion = widget.item.completion;
    final media = MediaQuery.of(context);
    final bottomInset = media.viewInsets.bottom;
    final maxHeight = media.size.height * 0.88;

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(16, 12, 16, 12 + bottomInset),
        child: ConstrainedBox(
          constraints: BoxConstraints(maxHeight: maxHeight),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(
                    color: AppColors.border,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              Expanded(
                child: SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        widget.item.itemName,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      if (completion != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          'Picked by ${completion.pickedByName}'
                              '${_formatWhen(completion.pickedAt).isEmpty ? '' : ' at ${_formatWhen(completion.pickedAt)}'}',
                          style: const TextStyle(
                            fontSize: 13,
                            color: AppColors.textSecondary,
                          ),
                        ),
                        if (completion.photoUrl.isNotEmpty) ...[
                          const SizedBox(height: 12),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(12),
                            child: SizedBox(
                              height: 160,
                              width: double.infinity,
                              child: AuthenticatedNetworkImage(
                                path: completion.photoUrl,
                                fit: BoxFit.cover,
                              ),
                            ),
                          ),
                        ],
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: OutlinedButton.icon(
                                onPressed: widget.onReplacePhoto,
                                icon: const Icon(Icons.camera_alt_outlined,
                                    size: 18),
                                label: const Text('Replace'),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: TextButton.icon(
                                onPressed: widget.onUnpick,
                                icon: const Icon(Icons.undo_rounded,
                                    color: AppColors.error, size: 18),
                                label: const Text(
                                  'Unpick',
                                  style: TextStyle(color: AppColors.error),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                      const SizedBox(height: 12),
                      const Text(
                        'Comments',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 8),
                      if (_comments.isEmpty)
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 12),
                          child: Text(
                            'No comments yet. Add a note for the team.',
                            style: TextStyle(
                              fontSize: 13,
                              color: AppColors.textMuted,
                            ),
                          ),
                        )
                      else
                        ..._comments.map((c) {
                          final mine = widget.currentUserId != null &&
                              c.userId == widget.currentUserId;
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: AppColors.surfaceVariant,
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          c.userName,
                                          style: const TextStyle(
                                            fontSize: 12,
                                            fontWeight: FontWeight.w700,
                                            color: AppColors.textPrimary,
                                          ),
                                        ),
                                      ),
                                      Text(
                                        _formatWhen(c.createdAt),
                                        style: const TextStyle(
                                          fontSize: 11,
                                          color: AppColors.textMuted,
                                        ),
                                      ),
                                      if (mine)
                                        IconButton(
                                          onPressed: _deletingId == c.id
                                              ? null
                                              : () => _delete(c),
                                          icon: _deletingId == c.id
                                              ? const SizedBox(
                                                  width: 14,
                                                  height: 14,
                                                  child:
                                                      CircularProgressIndicator(
                                                    strokeWidth: 2,
                                                  ),
                                                )
                                              : const Icon(Icons.delete_outline,
                                                  size: 18),
                                          visualDensity: VisualDensity.compact,
                                          tooltip: 'Delete',
                                        ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    c.body,
                                    style: const TextStyle(
                                      fontSize: 13,
                                      color: AppColors.textSecondary,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        }),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _controller,
                      minLines: 1,
                      maxLines: 3,
                      textInputAction: TextInputAction.send,
                      onSubmitted: (_) => _send(),
                      decoration: const InputDecoration(
                        hintText: 'Add a comment…',
                        isDense: true,
                        border: OutlineInputBorder(),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: _sending ? null : _send,
                    icon: _sending
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.send_rounded),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  final String label;
  final bool filled;

  const _Chip({required this.label, this.filled = false});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: filled ? AppColors.primaryDark : AppColors.surfaceVariant,
        borderRadius: BorderRadius.circular(6),
        border: filled ? null : Border.all(color: AppColors.border),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w600,
          color: filled ? Colors.white : AppColors.textSecondary,
        ),
      ),
    );
  }
}

