num _parseNum(dynamic val) {
  if (val is num) return val;
  if (val is String) return num.tryParse(val) ?? 0;
  return 0;
}

class WorkspaceActivityUser {
  final int id;
  final String? name;
  final String? email;

  const WorkspaceActivityUser({
    required this.id,
    this.name,
    this.email,
  });

  factory WorkspaceActivityUser.fromJson(Map<String, dynamic> json) {
    return WorkspaceActivityUser(
      id: _parseNum(json['id']).toInt(),
      name: json['name'] as String?,
      email: json['email'] as String?,
    );
  }

  String get displayName {
    final n = name?.trim();
    if (n != null && n.isNotEmpty) return n;
    final e = email?.trim();
    if (e != null && e.isNotEmpty) return e;
    return 'User #$id';
  }
}

class WorkspaceActivityLog {
  final int id;
  final String action;
  final String summary;
  final Map<String, dynamic>? meta;
  final String? createdAt;
  final WorkspaceActivityUser? user;

  const WorkspaceActivityLog({
    required this.id,
    required this.action,
    required this.summary,
    this.meta,
    this.createdAt,
    this.user,
  });

  factory WorkspaceActivityLog.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic>? meta;
    final rawMeta = json['meta'];
    if (rawMeta is Map) {
      meta = Map<String, dynamic>.from(rawMeta);
    }
    WorkspaceActivityUser? user;
    final rawUser = json['user'];
    if (rawUser is Map) {
      user = WorkspaceActivityUser.fromJson(Map<String, dynamic>.from(rawUser));
    }
    return WorkspaceActivityLog(
      id: _parseNum(json['id']).toInt(),
      action: (json['action'] as String?) ?? '',
      summary: (json['summary'] as String?) ?? '',
      meta: meta,
      createdAt: json['created_at'] as String?,
      user: user,
    );
  }

  String? get sku => meta?['sku']?.toString();

  String? get productName {
    final v = meta?['name'];
    if (v == null) return null;
    final text = v.toString().trim();
    return text.isEmpty ? null : text;
  }

  int? get oldStock {
    final v = meta?['old_stock'];
    if (v == null) return null;
    return _parseNum(v).toInt();
  }

  int? get newStock {
    final v = meta?['new_stock'];
    if (v == null) return null;
    return _parseNum(v).toInt();
  }

  String? get note {
    final v = meta?['note'];
    if (v == null) return null;
    final text = v.toString().trim();
    return text.isEmpty ? null : text;
  }

  String? get photoUrl {
    final v = meta?['photo_url'];
    if (v == null) return null;
    final text = v.toString().trim();
    return text.isEmpty ? null : text;
  }
}
