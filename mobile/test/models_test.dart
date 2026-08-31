import 'package:flutter_test/flutter_test.dart';
import 'package:hwt_license_mobile/models/models.dart';

void main() {
  group('DashboardStats', () {
    test('fromJson parses correctly', () {
      final json = {
        'total': 100,
        'active': 80,
        'expiring_soon': 5,
        'expired': 10,
        'devices': 120,
        'pending_approvals': 3,
      };

      final stats = DashboardStats.fromJson(json);

      expect(stats.totalLicenses, 100);
      expect(stats.activeLicenses, 80);
      expect(stats.expiringSoon, 5);
      expect(stats.expiredLicenses, 10);
      expect(stats.totalDevices, 120);
      expect(stats.pendingApprovals, 3);
    });

    test('fromJson handles empty data', () {
      final stats = DashboardStats.fromJson({});
      expect(stats.totalLicenses, 0);
      expect(stats.activeLicenses, 0);
      expect(stats.expiringSoon, 0);
      expect(stats.expiredLicenses, 0);
      expect(stats.totalDevices, 0);
      expect(stats.pendingApprovals, 0);
    });

    test('fromJson handles null values', () {
      final stats = DashboardStats.fromJson({
        'total': null,
        'active': null,
        'expiring_soon': null,
        'expired': null,
        'devices': null,
        'pending_approvals': null,
      });
      expect(stats.totalLicenses, 0);
      expect(stats.activeLicenses, 0);
    });
  });

  group('License', () {
    test('fromJson parses correctly with full data', () {
      final json = {
        'id': 1,
        'license_key': 'HWT-TEST-1234',
        'status': 'active',
        'plan': 'enterprise',
        'seats': 50,
        'used_seats': 12,
        'product': {'name': 'Test Product', 'id': 5},
        'customer': {'name': '测试客户', 'id': 3},
        'activated_at': '2026-01-15T00:00:00Z',
        'expires_at': '2027-01-01T00:00:00Z',
        'created_at': '2026-01-01T00:00:00Z',
      };

      final license = License.fromJson(json);

      expect(license.id, 1);
      expect(license.licenseKey, 'HWT-TEST-1234');
      expect(license.status, 'active');
      expect(license.plan, 'enterprise');
      expect(license.seats, 50);
      expect(license.usedSeats, 12);
      expect(license.productName, 'Test Product');
      expect(license.customerName, '测试客户');
      expect(license.activatedAt, isNotNull);
      expect(license.expiresAt, isNotNull);
      expect(license.isActive, isTrue);
    });

    test('fromJson handles expired status', () {
      final json = {
        'id': 2,
        'license_key': 'HWT-EXPIRED-5678',
        'status': 'expired',
        'product_name': 'Test Product',
        'expires_at': '2025-01-01T00:00:00Z',
        'created_at': '2024-01-01T00:00:00Z',
      };

      final license = License.fromJson(json);

      expect(license.id, 2);
      expect(license.status, 'expired');
      expect(license.isActive, isFalse);
      expect(license.daysRemaining, lessThan(-365)); // 已过期
    });

    test('fromJson parses nested objects correctly', () {
      final json = {
        'id': 1,
        'license_key': 'HWT-TEST-1234',
        'status': 'active',
        'product': {'name': 'HWT Enterprise'},
        'customer': {'name': 'Acme Corp'},
        'created_at': '2026-01-01T00:00:00Z',
      };

      final license = License.fromJson(json);

      expect(license.productName, 'HWT Enterprise');
      expect(license.customerName, 'Acme Corp');
    });

    test('isExpiringSoon returns true when within 30 days', () {
      final futureDate = DateTime.now().add(const Duration(days: 15));
      final json = {
        'id': 3,
        'license_key': 'HWT-E4E4',
        'status': 'active',
        'expires_at': futureDate.toIso8601String(),
        'created_at': '2026-01-01T00:00:00Z',
      };

      final license = License.fromJson(json);
      expect(license.isExpiringSoon, isTrue);
    });

    test('isExpiringSoon returns false when beyond 30 days', () {
      final futureDate = DateTime.now().add(const Duration(days: 60));
      final json = {
        'id': 4,
        'license_key': 'HWT-E4E5',
        'status': 'active',
        'expires_at': futureDate.toIso8601String(),
        'created_at': '2026-01-01T00:00:00Z',
      };

      final license = License.fromJson(json);
      expect(license.isExpiringSoon, isFalse);
    });

    test('daysRemaining handles null expires_at', () {
      final json = {
        'id': 5,
        'license_key': 'HWT-NOEXP',
        'status': 'active',
        'created_at': '2026-01-01T00:00:00Z',
      };

      final license = License.fromJson(json);
      expect(license.daysRemaining, 365);
    });
  });

  group('Device', () {
    test('fromJson parses correctly', () {
      final json = {
        'id': 1,
        'fingerprint': 'abc123',
        'name': 'Test Phone',
        'platform': 'android',
        'is_trusted': true,
        'trust_score': 85,
        'last_seen_at': '2026-07-01T00:00:00Z',
      };

      final device = Device.fromJson(json);

      expect(device.id, 1);
      expect(device.fingerprint, 'abc123');
      expect(device.name, 'Test Phone');
      expect(device.platform, 'android');
      expect(device.isTrusted, isTrue);
      expect(device.trustScore, 85);
      expect(device.lastSeenAt, isNotNull);
    });

    test('fromJson handles default values', () {
      final device = Device.fromJson({'id': 1, 'fingerprint': ''});
      expect(device.id, 1);
      expect(device.name, isNull);
      expect(device.isTrusted, isFalse);
      expect(device.trustScore, 0);
    });
  });

  group('Notification', () {
    test('fromJson parses correctly', () {
      final json = {
        'id': 1,
        'type': 'alert',
        'title': 'Security Alert',
        'content': 'New device login detected',
        'is_read': false,
        'created_at': '2026-07-01T00:00:00Z',
      };

      final notification = Notification.fromJson(json);

      expect(notification.id, 1);
      expect(notification.type, 'alert');
      expect(notification.title, 'Security Alert');
      expect(notification.content, 'New device login detected');
      expect(notification.isRead, isFalse);
    });

    test('fromJson handles read notification', () {
      final json = {
        'id': 2,
        'type': 'info',
        'title': '系统通知',
        'is_read': true,
        'created_at': '2026-06-15T00:00:00Z',
      };

      final notif = Notification.fromJson(json);
      expect(notif.isRead, isTrue);
    });

    test('fromJson handles defaults', () {
      final notif = Notification.fromJson({'id': 1, 'title': '', 'created_at': null});
      expect(notif.type, 'info');
      expect(notif.content, isNull);
      expect(notif.isRead, isFalse);
      expect(notif.createdAt, isA<DateTime>());
    });
  });
}
