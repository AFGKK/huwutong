import 'package:flutter/material.dart';
import '../models/models.dart';
import '../services/api_service.dart';

/// 仪表盘状态管理
class DashboardProvider extends ChangeNotifier {
  final ApiService _api;

  DashboardStats _stats = DashboardStats();
  List<License> _recentLicenses = [];
  bool _isLoading = false;
  String? _error;

  DashboardProvider(this._api);

  DashboardStats get stats => _stats;
  List<License> get recentLicenses => _recentLicenses;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> load() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _stats = await _api.getDashboardStats();
      _recentLicenses = await _api.getLicenses(page: 1);
    } catch (e) {
      _error = '加载数据失败';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}

/// License 列表状态管理
class LicenseProvider extends ChangeNotifier {
  final ApiService _api;

  List<License> _licenses = [];
  bool _isLoading = false;
  String? _error;
  String _statusFilter = 'all';

  LicenseProvider(this._api);

  List<License> get licenses => _licenses;
  bool get isLoading => _isLoading;
  String? get error => _error;
  String get statusFilter => _statusFilter;

  Future<void> load({String? status}) async {
    if (status != null) _statusFilter = status;
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _licenses = await _api.getLicenses(status: _statusFilter);
    } catch (e) {
      _error = '加载 License 列表失败';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> suspendLicense(int id) async {
    await _api.suspendLicense(id);
    await load();
  }

  Future<void> revokeLicense(int id, {String? reason}) async {
    await _api.revokeLicense(id, reason: reason);
    await load();
  }
}

/// 通知状态管理
class NotificationProvider extends ChangeNotifier {
  final ApiService _api;

  List<Notification> _notifications = [];
  int _unreadCount = 0;
  bool _isLoading = false;

  NotificationProvider(this._api);

  List<Notification> get notifications => _notifications;
  int get unreadCount => _unreadCount;
  bool get isLoading => _isLoading;

  Future<void> load() async {
    _isLoading = true;
    notifyListeners();
    try {
      _notifications = await _api.getNotifications();
      _unreadCount = await _api.getUnreadCount();
    } catch (_) {}
    _isLoading = false;
    notifyListeners();
  }

  Future<void> markAsRead(int id) async {
    await _api.markAsRead(id);
    await load();
  }

  Future<void> markAllAsRead() async {
    await _api.markAllAsRead();
    await load();
  }
}

/// 激活审批状态管理
class ApprovalProvider extends ChangeNotifier {
  final ApiService _api;

  List<Map<String, dynamic>> _approvals = [];
  bool _isLoading = false;
  String? _error;

  ApprovalProvider(this._api);

  List<Map<String, dynamic>> get approvals => _approvals;
  bool get isLoading => _isLoading;
  String? get error => _error;
  int get pendingCount => _approvals.length;

  Future<void> load() async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      _approvals = await _api.getPendingApprovals();
    } catch (e) {
      _error = '加载待审批列表失败';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> approve(int approvalId) async {
    await _api.approveActivation(approvalId);
    await load();
  }

  Future<void> reject(int approvalId, {String? reason}) async {
    await _api.rejectActivation(approvalId, reason: reason);
    await load();
  }
}

/// 设备管理状态管理
class DeviceProvider extends ChangeNotifier {
  final ApiService _api;

  List<Device> _devices = [];
  bool _isLoading = false;
  String? _error;

  DeviceProvider(this._api);

  List<Device> get devices => _devices;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> load() async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      _devices = await _api.getDevices();
    } catch (e) {
      _error = '加载设备列表失败';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> removeDevice(int id) async {
    await _api.removeDevice(id);
    await load();
  }
}
