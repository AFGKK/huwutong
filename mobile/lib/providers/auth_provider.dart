import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';
import '../services/api_service.dart';
import '../services/push_service.dart';

/// 认证状态管理
class AuthProvider extends ChangeNotifier {
  final ApiService _api;
  final _storage = const FlutterSecureStorage();

  static const _biometricKey = 'biometric_enabled';

  bool _isLoading = false;
  bool _isLoggedIn = false;
  String? _userName;
  String? _userEmail;
  String? _error;
  bool _biometricEnabled = false;
  bool _biometricAvailable = false;

  AuthProvider(this._api);

  bool get isLoading => _isLoading;
  bool get isLoggedIn => _isLoggedIn;
  String? get userName => _userName;
  String? get userEmail => _userEmail;
  String? get error => _error;
  bool get biometricEnabled => _biometricEnabled;
  bool get biometricAvailable => _biometricAvailable;

  Future<void> checkLoginStatus() async {
    _bindFcmTokenRefresh();
    _isLoggedIn = await _api.isLoggedIn();
    await _checkBiometricStatus();
    if (_isLoggedIn) {
      await _loadProfile();
      await _registerFcmToken();
    }
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      await _api.login(email, password);
      _isLoggedIn = true;
      _userEmail = email;
      await _loadProfile();

      // 登录成功后注册 FCM Token
      await _registerFcmToken();

      return true;
    } catch (e) {
      _error = '登录失败，请检查邮箱和密码';
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> loginWithBiometric() async {
    final localAuth = LocalAuthentication();
    final canCheck = await localAuth.canCheckBiometrics;
    if (!canCheck) return false;

    final authenticated = await localAuth.authenticate(
      localizedReason: '使用生物识别快速登录',
      options: const AuthenticationOptions(biometricOnly: true, stickyAuth: true),
    );

    if (!authenticated) return false;

    _isLoading = true;
    notifyListeners();

    try {
      final savedEmail = await _storage.read(key: 'last_email');
      final savedPass = await _storage.read(key: 'last_password');
      if (savedEmail == null || savedPass == null) {
        _error = '未保存登录信息，请先使用密码登录';
        return false;
      }
      await _api.login(savedEmail, savedPass);
      _isLoggedIn = true;
      _userEmail = savedEmail;
      await _loadProfile();
      await _registerFcmToken();
      return true;
    } catch (e) {
      _error = '生物识别登录失败';
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> enableBiometric(String email, String password) async {
    await _storage.write(key: _biometricKey, value: 'true');
    await _storage.write(key: 'last_email', value: email);
    await _storage.write(key: 'last_password', value: password);
    _biometricEnabled = true;
    notifyListeners();
  }

  Future<void> disableBiometric() async {
    await _storage.delete(key: _biometricKey);
    await _storage.delete(key: 'last_email');
    await _storage.delete(key: 'last_password');
    _biometricEnabled = false;
    notifyListeners();
  }

  Future<void> logout() async {
    await _api.removeFcmToken();
    await _api.logout();
    _isLoggedIn = false;
    _userName = null;
    _userEmail = null;
    notifyListeners();
  }

  Future<void> _checkBiometricStatus() async {
    final localAuth = LocalAuthentication();
    _biometricAvailable = await localAuth.canCheckBiometrics;
    final enabled = await _storage.read(key: _biometricKey);
    _biometricEnabled = enabled == 'true';
  }

  Future<void> _loadProfile() async {
    try {
      final profile = await _api.getProfile();
      _userName = profile['name'] as String?;
      _userEmail = profile['email'] as String?;
    } catch (_) {}
  }

  void _bindFcmTokenRefresh() {
    PushService().onTokenRefresh = (token) async {
      if (!_isLoggedIn) return;
      await _api.registerFcmToken(
        token,
        platform: PushService.platformName,
        deviceName: await _deviceName(),
      );
    };
  }

  Future<String> _deviceName() async {
    try {
      final info = DeviceInfoPlugin();
      if (defaultTargetPlatform == TargetPlatform.android) {
        final android = await info.androidInfo;
        return '${android.manufacturer} ${android.model}'.trim();
      }
      if (defaultTargetPlatform == TargetPlatform.iOS) {
        final ios = await info.iosInfo;
        return ios.name.isNotEmpty ? ios.name : 'iOS Device';
      }
    } catch (_) {}
    return 'Flutter App';
  }

  Future<void> _registerFcmToken() async {
    try {
      final push = PushService();
      if (push.fcmToken != null) {
        await _api.registerFcmToken(
          push.fcmToken!,
          platform: PushService.platformName,
          deviceName: await _deviceName(),
        );
      }
    } catch (_) {
      // FCM 注册失败不阻塞登录
    }
  }
}
