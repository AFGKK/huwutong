/// HWT License 移动端 - 数据模型
class License {
  final int id;
  final String licenseKey;
  final String status;
  final String? plan;
  final int seats;
  final int usedSeats;
  final String? productName;
  final String? customerName;
  final DateTime? activatedAt;
  final DateTime? expiresAt;
  final DateTime createdAt;

  License({
    required this.id,
    required this.licenseKey,
    required this.status,
    this.plan,
    this.seats = 0,
    this.usedSeats = 0,
    this.productName,
    this.customerName,
    this.activatedAt,
    this.expiresAt,
    required this.createdAt,
  });

  factory License.fromJson(Map<String, dynamic> json) => License(
        id: json['id'] as int,
        licenseKey: json['license_key'] as String? ?? '',
        status: json['status'] as String? ?? 'unknown',
        plan: json['plan'] as String?,
        seats: json['seats'] as int? ?? 0,
        usedSeats: json['used_seats'] as int? ?? 0,
        productName: json['product'] is Map ? json['product']['name'] as String? : null,
        customerName: json['customer'] is Map ? json['customer']['name'] as String? : null,
        activatedAt: json['activated_at'] != null ? DateTime.tryParse(json['activated_at']) : null,
        expiresAt: json['expires_at'] != null ? DateTime.tryParse(json['expires_at']) : null,
        createdAt: DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
      );

  bool get isActive => status == 'active';
  bool get isExpiringSoon =>
      expiresAt != null && expiresAt!.difference(DateTime.now()).inDays <= 30 && isActive;
  int get daysRemaining => expiresAt != null
      ? expiresAt!.difference(DateTime.now()).inDays
      : 365;
}

class Device {
  final int id;
  final String fingerprint;
  final String? name;
  final String? platform;
  final bool isTrusted;
  final int trustScore;
  final DateTime? lastSeenAt;

  Device({
    required this.id,
    required this.fingerprint,
    this.name,
    this.platform,
    this.isTrusted = false,
    this.trustScore = 0,
    this.lastSeenAt,
  });

  factory Device.fromJson(Map<String, dynamic> json) => Device(
        id: json['id'] as int,
        fingerprint: json['fingerprint'] as String? ?? '',
        name: json['name'] as String?,
        platform: json['platform'] as String?,
        isTrusted: json['is_trusted'] as bool? ?? false,
        trustScore: json['trust_score'] as int? ?? 0,
        lastSeenAt: json['last_seen_at'] != null ? DateTime.tryParse(json['last_seen_at']) : null,
      );
}

class DashboardStats {
  final int totalLicenses;
  final int activeLicenses;
  final int expiringSoon;
  final int expiredLicenses;
  final int totalDevices;
  final int pendingApprovals;

  DashboardStats({
    this.totalLicenses = 0,
    this.activeLicenses = 0,
    this.expiringSoon = 0,
    this.expiredLicenses = 0,
    this.totalDevices = 0,
    this.pendingApprovals = 0,
  });

  factory DashboardStats.fromJson(Map<String, dynamic> json) => DashboardStats(
        totalLicenses: json['total'] as int? ?? 0,
        activeLicenses: json['active'] as int? ?? 0,
        expiringSoon: json['expiring_soon'] as int? ?? 0,
        expiredLicenses: json['expired'] as int? ?? 0,
        totalDevices: json['devices'] as int? ?? 0,
        pendingApprovals: json['pending_approvals'] as int? ?? 0,
      );
}

class Notification {
  final int id;
  final String type;
  final String title;
  final String? content;
  final bool isRead;
  final DateTime createdAt;

  Notification({
    required this.id,
    required this.type,
    required this.title,
    this.content,
    this.isRead = false,
    required this.createdAt,
  });

  factory Notification.fromJson(Map<String, dynamic> json) => Notification(
        id: json['id'] as int,
        type: json['type'] as String? ?? 'info',
        title: json['title'] as String? ?? '',
        content: json['content'] as String?,
        isRead: json['is_read'] as bool? ?? false,
        createdAt: DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
      );
}
