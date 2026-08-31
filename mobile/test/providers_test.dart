import 'package:flutter_test/flutter_test.dart';
import 'package:hwt_license_mobile/providers/app_providers.dart';
import 'package:hwt_license_mobile/models/models.dart';
import 'package:hwt_license_mobile/services/api_service.dart';
import 'package:mockito/mockito.dart';
import 'package:mockito/annotations.dart';

@GenerateMocks([ApiService])
import 'providers_test.mocks.dart';

void main() {
  group('DashboardProvider', () {
    late MockApiService mockApi;
    late DashboardProvider provider;
    const testStats = DashboardStats(
      totalLicenses: 100,
      activeLicenses: 80,
      expiringSoon: 5,
      totalDevices: 120,
    );

    setUp(() {
      mockApi = MockApiService();
      provider = DashboardProvider(mockApi);
    });

    test('initial state', () {
      expect(provider.isLoading, isFalse);
      expect(provider.stats.totalLicenses, 0);
      expect(provider.recentLicenses, isEmpty);
      expect(provider.error, isNull);
    });

    test('load sets stats and recent licenses on success', () async {
      when(mockApi.getDashboardStats()).thenAnswer((_) async => testStats);
      when(mockApi.getLicenses(page: 1)).thenAnswer((_) async => []);

      await provider.load();

      expect(provider.isLoading, isFalse);
      expect(provider.stats.totalLicenses, 100);
      expect(provider.stats.activeLicenses, 80);
      expect(provider.error, isNull);
      verify(mockApi.getDashboardStats()).called(1);
      verify(mockApi.getLicenses(page: 1)).called(1);
    });

    test('load sets error on failure', () async {
      when(mockApi.getDashboardStats()).thenThrow(Exception('Network error'));

      await provider.load();

      expect(provider.isLoading, isFalse);
      expect(provider.stats.totalLicenses, 0);
      expect(provider.error, isNotNull);
    });

    test('load notifies listeners', () {
      when(mockApi.getDashboardStats()).thenAnswer((_) async => testStats);
      when(mockApi.getLicenses(page: 1)).thenAnswer((_) async => []);

      int notifyCount = 0;
      provider.addListener(() => notifyCount++);

      provider.load();

      // notifyListeners called at least: start loading, finish loading
      expect(notifyCount, greaterThanOrEqualTo(2));
    });
  });

  group('LicenseProvider', () {
    late MockApiService mockApi;
    late LicenseProvider provider;

    setUp(() {
      mockApi = MockApiService();
      provider = LicenseProvider(mockApi);
    });

    test('initial state', () {
      expect(provider.isLoading, isFalse);
      expect(provider.licenses, isEmpty);
      expect(provider.error, isNull);
      expect(provider.statusFilter, 'all');
    });

    test('load sets licenses on success', () async {
      final licenses = [
        License(id: 1, licenseKey: 'KEY-001', status: 'active', createdAt: DateTime.now()),
        License(id: 2, licenseKey: 'KEY-002', status: 'expired', createdAt: DateTime.now()),
      ];
      when(mockApi.getLicenses()).thenAnswer((_) async => licenses);

      await provider.load();

      expect(provider.licenses.length, 2);
      expect(provider.licenses[0].licenseKey, 'KEY-001');
      expect(provider.error, isNull);
    });

    test('load with status filter', () async {
      when(mockApi.getLicenses(status: 'active')).thenAnswer((_) async => []);

      await provider.load(status: 'active');

      expect(provider.statusFilter, 'active');
      verify(mockApi.getLicenses(status: 'active')).called(1);
    });

    test('load sets error on failure', () async {
      when(mockApi.getLicenses()).thenThrow(Exception('API error'));

      await provider.load();

      expect(provider.error, isNotNull);
      expect(provider.licenses, isEmpty);
    });

    test('suspendLicense reloads after success', () async {
      when(mockApi.suspendLicense(1)).thenAnswer((_) async => {});
      when(mockApi.getLicenses()).thenAnswer((_) async => []);

      await provider.suspendLicense(1);

      verify(mockApi.suspendLicense(1)).called(1);
      verify(mockApi.getLicenses()).called(1);
    });

    test('revokeLicense reloads after success', () async {
      when(mockApi.revokeLicense(1, reason: 'test')).thenAnswer((_) async => {});
      when(mockApi.getLicenses()).thenAnswer((_) async => []);

      await provider.revokeLicense(1, reason: 'test');

      verify(mockApi.revokeLicense(1, reason: 'test')).called(1);
      verify(mockApi.getLicenses()).called(1);
    });
  });

  group('NotificationProvider', () {
    late MockApiService mockApi;
    late NotificationProvider provider;

    setUp(() {
      mockApi = MockApiService();
      provider = NotificationProvider(mockApi);
    });

    test('initial state', () {
      expect(provider.isLoading, isFalse);
      expect(provider.notifications, isEmpty);
      expect(provider.unreadCount, 0);
    });

    test('load sets notifications and unread count on success', () async {
      final notifs = [
        Notification(id: 1, type: 'info', title: 'Test', isRead: false, createdAt: DateTime.now()),
      ];
      when(mockApi.getNotifications()).thenAnswer((_) async => notifs);
      when(mockApi.getUnreadCount()).thenAnswer((_) async => 5);

      await provider.load();

      expect(provider.notifications.length, 1);
      expect(provider.unreadCount, 5);
      expect(provider.isLoading, isFalse);
    });

    test('markAsRead reloads after success', () async {
      when(mockApi.markAsRead(1)).thenAnswer((_) async => {});
      when(mockApi.getNotifications()).thenAnswer((_) async => []);
      when(mockApi.getUnreadCount()).thenAnswer((_) async => 0);

      await provider.markAsRead(1);

      verify(mockApi.markAsRead(1)).called(1);
    });

    test('markAllAsRead reloads after success', () async {
      when(mockApi.markAllAsRead()).thenAnswer((_) async => {});
      when(mockApi.getNotifications()).thenAnswer((_) async => []);
      when(mockApi.getUnreadCount()).thenAnswer((_) async => 0);

      await provider.markAllAsRead();

      verify(mockApi.markAllAsRead()).called(1);
    });
  });

  group('ApprovalProvider', () {
    late MockApiService mockApi;
    late ApprovalProvider provider;

    setUp(() {
      mockApi = MockApiService();
      provider = ApprovalProvider(mockApi);
    });

    test('initial state', () {
      expect(provider.isLoading, isFalse);
      expect(provider.approvals, isEmpty);
      expect(provider.pendingCount, 0);
    });

    test('load sets approvals on success', () async {
      when(mockApi.getPendingApprovals()).thenAnswer((_) async => [
        {'id': 1, 'license_key': 'KEY-001', 'status': 'pending'},
        {'id': 2, 'license_key': 'KEY-002', 'status': 'pending'},
      ]);

      await provider.load();

      expect(provider.approvals.length, 2);
      expect(provider.pendingCount, 2);
      expect(provider.error, isNull);
    });

    test('load sets error on failure', () async {
      when(mockApi.getPendingApprovals()).thenThrow(Exception('Error'));

      await provider.load();

      expect(provider.error, isNotNull);
      expect(provider.approvals, isEmpty);
    });

    test('approve reloads after success', () async {
      when(mockApi.approveActivation(1)).thenAnswer((_) async => {});
      when(mockApi.getPendingApprovals()).thenAnswer((_) async => []);

      await provider.approve(1);

      verify(mockApi.approveActivation(1)).called(1);
    });

    test('reject reloads after success', () async {
      when(mockApi.rejectActivation(1, reason: 'invalid')).thenAnswer((_) async => {});
      when(mockApi.getPendingApprovals()).thenAnswer((_) async => []);

      await provider.reject(1, reason: 'invalid');

      verify(mockApi.rejectActivation(1, reason: 'invalid')).called(1);
    });
  });

  group('DeviceProvider', () {
    late MockApiService mockApi;
    late DeviceProvider provider;

    setUp(() {
      mockApi = MockApiService();
      provider = DeviceProvider(mockApi);
    });

    test('initial state', () {
      expect(provider.isLoading, isFalse);
      expect(provider.devices, isEmpty);
      expect(provider.error, isNull);
    });

    test('load sets devices on success', () async {
      final devices = [
        Device(id: 1, fingerprint: 'fp1', name: 'Android', platform: 'android'),
        Device(id: 2, fingerprint: 'fp2', name: 'iPhone', platform: 'ios'),
      ];
      when(mockApi.getDevices()).thenAnswer((_) async => devices);

      await provider.load();

      expect(provider.devices.length, 2);
      expect(provider.devices[0].name, 'Android');
    });

    test('removeDevice reloads after success', () async {
      when(mockApi.removeDevice(1)).thenAnswer((_) async => {});
      when(mockApi.getDevices()).thenAnswer((_) async => []);

      await provider.removeDevice(1);

      verify(mockApi.removeDevice(1)).called(1);
    });
  });
}
