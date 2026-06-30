import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../config/api_config.dart';
import '../models/models.dart';

/// HWT License API 客户端
class ApiService {
  late final Dio _dio;
  final _storage = const FlutterSecureStorage();

  static const _tokenKey = 'auth_token';

  ApiService() {
    _dio = Dio(BaseOptions(
      baseUrl: ApiConfig.baseUrl,
      connectTimeout: ApiConfig.connectTimeout,
      receiveTimeout: ApiConfig.timeout,
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
    ));

    // 请求拦截器 - 注入 Token
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _storage.read(key: _tokenKey);
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (error, handler) {
        if (error.response?.statusCode == 401) {
          // Token 过期，触发重新登录
          _onUnauthorized();
        }
        handler.next(error);
      },
    ));
  }

  void _onUnauthorized() {
    // 通过全局事件通知登录页面
    _storage.delete(key: _tokenKey);
  }

  // ─── 认证 ───

  Future<String> login(String email, String password) async {
    final res = await _dio.post('/login', data: {
      'email': email,
      'password': password,
    });
    final token = res.data['data']['token'] as String;
    await _storage.write(key: _tokenKey, value: token);
    return token;
  }

  Future<void> logout() async {
    try {
      await _dio.post('/logout');
    } catch (_) {}
    await _storage.delete(key: _tokenKey);
  }

  Future<bool> isLoggedIn() async {
    final token = await _storage.read(key: _tokenKey);
    return token != null;
  }

  Future<Map<String, dynamic>> getProfile() async {
    final res = await _dio.get('/me');
    return res.data['data'] as Map<String, dynamic>? ?? {};
  }

  // ─── Dashboard ───

  Future<DashboardStats> getDashboardStats() async {
    final res = await _dio.get('/licenses/stats');
    return DashboardStats.fromJson(res.data['data'] ?? {});
  }

  // ─── License ───

  Future<List<License>> getLicenses({int page = 1, String? status}) async {
    final params = <String, dynamic>{'page': page, 'per_page': 20};
    if (status != null && status != 'all') params['filter[status]'] = status;
    final res = await _dio.get('/licenses', queryParameters: params);
    final list = (res.data['data'] as List?) ?? [];
    return list.map((e) => License.fromJson(e)).toList();
  }

  Future<License> getLicenseDetail(int id) async {
    final res = await _dio.get('/licenses/$id');
    return License.fromJson(res.data['data']);
  }

  Future<void> revokeLicense(int id, {String? reason}) async {
    await _dio.post('/licenses/$id/revoke', data: {'reason': reason});
  }

  Future<void> suspendLicense(int id) async {
    await _dio.post('/licenses/$id/suspend');
  }

  Future<void> activateLicense(String licenseKey, String deviceFingerprint) async {
    await _dio.post('/license/activate', data: {
      'license_key': licenseKey,
      'device_fingerprint': deviceFingerprint,
      'device_name': 'Mobile App',
      'platform': 'mobile',
    });
  }

  // ─── Device ───

  Future<List<Device>> getDevices({int page = 1}) async {
    final res = await _dio.get('/devices', queryParameters: {'page': page, 'per_page': 20});
    final list = (res.data['data'] as List?) ?? [];
    return list.map((e) => Device.fromJson(e)).toList();
  }

  Future<void> removeDevice(int id) async {
    await _dio.delete('/devices/$id');
  }

  // ─── Notifications ───

  Future<List<Notification>> getNotifications({int page = 1}) async {
    final res = await _dio.get('/notifications', queryParameters: {'page': page, 'per_page': 20});
    final list = (res.data['data'] as List?) ?? [];
    return list.map((e) => Notification.fromJson(e)).toList();
  }

  Future<int> getUnreadCount() async {
    final res = await _dio.get('/notifications/unread-count');
    return res.data['data']['count'] as int? ?? 0;
  }

  Future<void> markAsRead(int id) async {
    await _dio.post('/notifications/$id/read');
  }

  Future<void> markAllAsRead() async {
    await _dio.post('/notifications/read-all');
  }

  // ─── Approval ───

  Future<List<Map<String, dynamic>>> getPendingApprovals() async {
    final res = await _dio.get('/approvals/pending');
    return (res.data['data'] as List?)?.cast<Map<String, dynamic>>() ?? [];
  }

  Future<void> approveActivation(int approvalId) async {
    await _dio.post('/approvals/$approvalId/approve');
  }

  Future<void> rejectActivation(int approvalId, {String? reason}) async {
    await _dio.post('/approvals/$approvalId/reject', data: {'reason': reason});
  }
}
