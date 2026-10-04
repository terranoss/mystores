class WorkspaceMember {
  final int id;
  final int userId;
  final String role;
  final String? name;
  final String? email;

  const WorkspaceMember({
    required this.id,
    required this.userId,
    required this.role,
    this.name,
    this.email,
  });

  factory WorkspaceMember.fromJson(Map<String, dynamic> json) {
    return WorkspaceMember(
      id: json['id'] as int,
      userId: json['user_id'] as int,
      role: json['role'] as String,
      name: json['name'] as String?,
      email: json['email'] as String?,
    );
  }
}

class WorkspaceInvite {
  final int id;
  final String email;
  final String role;
  final String expiresAt;
  final String createdAt;

  const WorkspaceInvite({
    required this.id,
    required this.email,
    required this.role,
    required this.expiresAt,
    required this.createdAt,
  });

  factory WorkspaceInvite.fromJson(Map<String, dynamic> json) {
    return WorkspaceInvite(
      id: json['id'] as int,
      email: json['email'] as String,
      role: json['role'] as String,
      expiresAt: json['expires_at'] as String,
      createdAt: json['created_at'] as String,
    );
  }
}

class WorkspaceInfo {
  final int id;
  final String name;
  final String? role;
  final bool isOwner;

  const WorkspaceInfo({
    required this.id,
    required this.name,
    this.role,
    required this.isOwner,
  });

  factory WorkspaceInfo.fromJson(Map<String, dynamic> json) {
    return WorkspaceInfo(
      id: json['id'] as int,
      name: json['name'] as String,
      role: json['role'] as String?,
      isOwner: json['is_owner'] as bool? ?? false,
    );
  }
}

class WorkspaceResponse {
  final WorkspaceInfo workspace;
  final List<WorkspaceMember> members;
  final List<WorkspaceInvite> pendingInvites;

  const WorkspaceResponse({
    required this.workspace,
    required this.members,
    required this.pendingInvites,
  });

  factory WorkspaceResponse.fromJson(Map<String, dynamic> json) {
    return WorkspaceResponse(
      workspace: WorkspaceInfo.fromJson(
          json['workspace'] as Map<String, dynamic>),
      members: (json['members'] as List<dynamic>)
          .map((m) => WorkspaceMember.fromJson(m as Map<String, dynamic>))
          .toList(),
      pendingInvites: (json['pending_invites'] as List<dynamic>)
          .map((i) => WorkspaceInvite.fromJson(i as Map<String, dynamic>))
          .toList(),
    );
  }
}

class ActivityEntry {
  final int id;
  final String action;
  final String summary;
  final String? subjectType;
  final int? subjectId;
  final String? createdAt;
  final Map<String, dynamic>? meta;
  final ActivityUser? user;

  const ActivityEntry({
    required this.id,
    required this.action,
    required this.summary,
    this.subjectType,
    this.subjectId,
    this.createdAt,
    this.meta,
    this.user,
  });

  factory ActivityEntry.fromJson(Map<String, dynamic> json) {
    return ActivityEntry(
      id: json['id'] as int,
      action: json['action'] as String,
      summary: json['summary'] as String,
      subjectType: json['subject_type'] as String?,
      subjectId: json['subject_id'] as int?,
      createdAt: json['created_at'] as String?,
      meta: json['meta'] as Map<String, dynamic>?,
      user: json['user'] != null
          ? ActivityUser.fromJson(json['user'] as Map<String, dynamic>)
          : null,
    );
  }
}

class ActivityUser {
  final int id;
  final String name;
  final String email;

  const ActivityUser(
      {required this.id, required this.name, required this.email});

  factory ActivityUser.fromJson(Map<String, dynamic> json) {
    return ActivityUser(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
    );
  }
}

class PaginatedActivity {
  final List<ActivityEntry> data;
  final int currentPage;
  final int lastPage;
  final int total;

  const PaginatedActivity({
    required this.data,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  factory PaginatedActivity.fromJson(Map<String, dynamic> json) {
    return PaginatedActivity(
      data: (json['data'] as List<dynamic>)
          .map((e) => ActivityEntry.fromJson(e as Map<String, dynamic>))
          .toList(),
      currentPage: json['current_page'] as int,
      lastPage: json['last_page'] as int,
      total: json['total'] as int,
    );
  }
}
