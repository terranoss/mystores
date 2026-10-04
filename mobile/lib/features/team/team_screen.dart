import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api/api_client.dart';
import '../../core/api/api_endpoints.dart';
import '../../core/models/workspace.dart';
import '../../core/providers/auth_provider.dart';
import '../../shared/theme/app_theme.dart';
import '../../shared/widgets/app_snackbar.dart';
import '../../shared/widgets/error_retry.dart';
import '../../shared/widgets/hamburger_button.dart';

// ── Workspace provider (inline for simplicity) ────────────────

final _workspaceProvider =
    FutureProvider<WorkspaceResponse>((ref) async {
  final res = await apiClient.get(ApiEndpoints.workspace);
  return WorkspaceResponse.fromJson(res.data as Map<String, dynamic>);
});

class TeamScreen extends ConsumerWidget {
  const TeamScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final workspaceAsync = ref.watch(_workspaceProvider);
    final auth = ref.watch(authProvider);

    return Scaffold(
      appBar: AppBar(
        leading: const HamburgerButton(),
        title: const Text('Team'),
        actions: [
          TextButton.icon(
            onPressed: () =>
                ref.read(authProvider.notifier).logout(),
            icon: const Icon(Icons.logout_rounded,
                color: Colors.white70, size: 18),
            label: Text(
              auth.user?.name ?? 'Logout',
              style: const TextStyle(color: Colors.white70, fontSize: 13),
            ),
          ),
        ],
      ),
      body: workspaceAsync.when(
        loading: () =>
            const LoadingWidget(message: 'Loading workspace…'),
        error: (e, _) => ErrorRetryWidget(
          message: e.toString(),
          onRetry: () => ref.invalidate(_workspaceProvider),
        ),
        data: (ws) => RefreshIndicator(
          color: AppColors.accent,
          onRefresh: () async => ref.invalidate(_workspaceProvider),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // Workspace info card
              _WorkspaceInfoCard(workspace: ws.workspace),
              const SizedBox(height: 20),
              // Members
              _SectionHeader(
                title: 'Members (${ws.members.length})',
                action: ws.workspace.isOwner
                    ? TextButton.icon(
                        onPressed: () =>
                            _showInviteDialog(context, ref),
                        icon: const Icon(Icons.person_add_alt_1_rounded,
                            size: 16),
                        label: const Text('Invite'),
                      )
                    : null,
              ),
              const SizedBox(height: 8),
              ...ws.members.map(
                (m) => _MemberTile(
                  member: m,
                  canRemove: ws.workspace.isOwner &&
                      m.userId != auth.user?.id,
                  onRemove: () async {
                    final ok = await _removeMember(context, ref, m.id);
                    if (ok) ref.invalidate(_workspaceProvider);
                  },
                ),
              ),

              // Pending invites
              if (ws.pendingInvites.isNotEmpty) ...[
                const SizedBox(height: 20),
                _SectionHeader(
                    title:
                        'Pending Invites (${ws.pendingInvites.length})'),
                const SizedBox(height: 8),
                ...ws.pendingInvites.map(
                  (inv) => _InviteTile(invite: inv),
                ),
              ],

              const SizedBox(height: 80),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showInviteDialog(
      BuildContext context, WidgetRef ref) async {
    final emailController = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Invite Member'),
        content: TextField(
          controller: emailController,
          keyboardType: TextInputType.emailAddress,
          autofocus: true,
          decoration: const InputDecoration(
            labelText: 'Email address',
            hintText: 'colleague@example.com',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () async {
              try {
                await apiClient.post(
                  ApiEndpoints.workspaceInvites,
                  data: {'email': emailController.text.trim()},
                );
                if (ctx.mounted) Navigator.pop(ctx, true);
              } catch (_) {
                if (ctx.mounted) Navigator.pop(ctx, false);
              }
            },
            child: const Text('Send Invite'),
          ),
        ],
      ),
    );
    emailController.dispose();
    if (ok == true) {
      if (context.mounted) {
        AppSnackbar.show(context, 'Invite sent!');
      }
      ref.invalidate(_workspaceProvider);
    } else if (ok == false) {
      if (context.mounted) {
        AppSnackbar.show(context, 'Failed to send invite', success: false);
      }
    }
  }

  Future<bool> _removeMember(
      BuildContext context, WidgetRef ref, int memberId) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Remove Member'),
        content: const Text(
            'Are you sure you want to remove this member from the workspace?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.error),
            child: const Text('Remove'),
          ),
        ],
      ),
    );
    if (confirm != true) return false;
    try {
      await apiClient.delete(ApiEndpoints.workspaceMember(memberId));
      if (context.mounted) {
        AppSnackbar.show(context, 'Member removed');
      }
      return true;
    } catch (_) {
      if (context.mounted) {
        AppSnackbar.show(context, 'Failed to remove member', success: false);
      }
      return false;
    }
  }
}

// ── Sub-widgets ───────────────────────────────────────────────

class _WorkspaceInfoCard extends StatelessWidget {
  final WorkspaceInfo workspace;
  const _WorkspaceInfoCard({required this.workspace});

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: AppColors.accentLight,
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(Icons.group_rounded,
                  color: AppColors.accent, size: 26),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(workspace.name,
                      style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      _RoleBadge(
                          role: workspace.isOwner
                              ? 'Owner'
                              : workspace.role ?? 'Member'),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  final String title;
  final Widget? action;
  const _SectionHeader({required this.title, this.action});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Text(title, style: Theme.of(context).textTheme.titleSmall),
        const Spacer(),
        if (action != null) action!,
      ],
    );
  }
}

class _MemberTile extends StatelessWidget {
  final WorkspaceMember member;
  final bool canRemove;
  final VoidCallback onRemove;

  const _MemberTile({
    required this.member,
    required this.canRemove,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: AppColors.accentLight,
          child: Text(
            (member.name?.isNotEmpty == true
                    ? member.name![0]
                    : member.email?[0] ?? '?')
                .toUpperCase(),
            style: const TextStyle(
                color: AppColors.accent, fontWeight: FontWeight.w700),
          ),
        ),
        title: Text(member.name ?? member.email ?? 'Unknown',
            style: Theme.of(context).textTheme.titleSmall),
        subtitle: Text(member.email ?? '',
            style: Theme.of(context).textTheme.bodySmall),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            _RoleBadge(role: member.role),
            if (canRemove) ...[
              const SizedBox(width: 8),
              IconButton(
                icon: const Icon(Icons.person_remove_rounded,
                    size: 18, color: AppColors.error),
                onPressed: onRemove,
                tooltip: 'Remove member',
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _InviteTile extends StatelessWidget {
  final WorkspaceInvite invite;
  const _InviteTile({required this.invite});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: const CircleAvatar(
          backgroundColor: AppColors.warningLight,
          child: Icon(Icons.mail_outline_rounded,
              color: AppColors.warning, size: 20),
        ),
        title: Text(invite.email,
            style: Theme.of(context).textTheme.titleSmall),
        subtitle: Text(
          'Expires: ${invite.expiresAt.substring(0, 10)}',
          style: Theme.of(context).textTheme.bodySmall,
        ),
        trailing: _RoleBadge(role: invite.role),
      ),
    );
  }
}

class _RoleBadge extends StatelessWidget {
  final String role;
  const _RoleBadge({required this.role});

  @override
  Widget build(BuildContext context) {
    final isOwner = role.toLowerCase() == 'owner';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: isOwner ? AppColors.accentLight : AppColors.surfaceVariant,
        borderRadius: BorderRadius.circular(6),
        border: Border.all(
            color: isOwner
                ? AppColors.accent.withAlpha(51)
                : AppColors.border),
      ),
      child: Text(
        role,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: isOwner ? AppColors.accent : AppColors.textSecondary,
        ),
      ),
    );
  }
}
