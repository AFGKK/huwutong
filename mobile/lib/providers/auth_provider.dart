import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';
import '../services/api_service.dart';

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
    _isLoggedIn = await _api.isLoggedIn();
    await _checkBiometricStatus();
    if (_isLoggedIn) {
      await _loadProfile();
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
}
