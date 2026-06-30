import 'dart:convert';
import 'dart:math';
import 'package:crypto/crypto.dart';
import 'package:http/http.dart' as http;
import 'hwt_exceptions.dart';
import 'models.dart';

/// HWT License Client — Flutter/Dart SDK (M2-21)
///
/// 基于统一错误码标准 M2-34
class HwtClient {
  final String apiKey;
  final String host;
  final String? secretKey;
  final Duration timeout;

  HwtClient({
    required this.apiKey,
    this.host = 'https://api.huwutong.com',
    this.secretKey,
    this.timeout = const Duration(seconds: 30),
  });

  // ── Public API ──

  /// 激活 License
  Future<ActivationResult> activate(String licenseKey, Map<String, String> deviceInfo) async {
    final payload = {
      'license_key': licenseKey,
      'device_info': deviceInfo,
      'timestamp': (DateTime.now().millisecondsSinceEpoch / 1000).round(),
    };
    return _post<ActivationResult>(
      '/api/license/activate',
      payload,
      (json) => ActivationResult.fromJson(json),
    );
  }

  /// 验证 License
  Future<ValidationResult> validate(String licenseKey, Map<String, String> deviceInfo) async {
    final payload = {
      'license_key': licenseKey,
      'device_info': deviceInfo,
      'timestamp': (DateTime.now().millisecondsSinceEpoch / 1000).round(),
    };
    return _post<ValidationResult>(
      '/api/license/validate',
      payload,
      (json) => ValidationResult.fromJson(json),
    );
  }

  /// 停用 License
  Future<Map<String, dynamic>> deactivate(String licenseKey, Map<String, String> deviceInfo) async {
    final payload = {
      'license_key': licenseKey,
      'device_info': deviceInfo,
      'timestamp': (DateTime.now().millisecondsSinceEpoch / 1000).round(),
    };
    return _post<Map<String, dynamic>>(
      '/api/license/deactivate',
      payload,
      (json) => json,
    );
  }

  /// 检查 Feature Flag
  Future<Map<String, dynamic>> checkFeature(String licenseKey, String featureKey) async {
    final payload = {
      'license_key': licenseKey,
      'feature': featureKey,
      'timestamp': (DateTime.now().millisecondsSinceEpoch / 1000).round(),
    };
    return _post<Map<String, dynamic>>(
      '/api/license/check-feature',
      payload,
      (json) => json,
    );
  }

  /// 获取离线 License
  Future<Map<String, dynamic>> getOfflineLicense(String licenseKey) async {
    final payload = {
      'license_key': licenseKey,
      'timestamp': (DateTime.now().millisecondsSinceEpoch / 1000).round(),
    };
    return _post<Map<String, dynamic>>(
      '/api/offline/generate',
      payload,
      (json) => json,
    );
  }

  /// 验证离线 License
  Future<Map<String, dynamic>> verifyOffline(String licenseData) async {
    final payload = {
      'license_data': licenseData,
      'timestamp': (DateTime.now().millisecondsSinceEpoch / 1000).round(),
    };
    return _post<Map<String, dynamic>>(
      '/api/offline/verify',
      payload,
      (json) => json,
    );
  }

  // ── Internal ──

  Future<T> _post<T>(
    String path,
    Map<String, dynamic> payload,
    T Function(Map<String, dynamic> json) fromJson,
  ) async {
    final url = Uri.parse('$host$path');
    final body = jsonEncode(payload);

    final headers = <String, String>{
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': 'Bearer $apiKey',
    };

    if (secretKey != null && secretKey!.isNotEmpty) {
      final nonce = _generateNonce();
      final timestamp = (DateTime.now().millisecondsSinceEpoch / 1000).round().toString();
      final signature = _hmac(body, nonce, timestamp);
      headers['X-Nonce'] = nonce;
      headers['X-Timestamp'] = timestamp;
      headers['X-Signature'] = signature;
    }

    try {
      final response = await http
          .post(url, headers: headers, body: body)
          .timeout(timeout);

      final result = jsonDecode(response.body) as Map<String, dynamic>;

      if (result.containsKey('error') && result['error'] != null) {
        final error = ApiError.fromJson(result['error'] as Map<String, dynamic>);
        throw HwtApiException(
          error.code ?? 'UNKNOWN',
          error.message ?? 'Unknown error',
        );
      }

      return fromJson(result);
    } on HwtApiException {
      rethrow;
    } on http.ClientException catch (e) {
      throw HwtNetworkException(e.message);
    } on Exception catch (e) {
      throw HwtNetworkException(e.toString());
    }
  }

  String _generateNonce() {
    final rand = Random.secure();
    final bytes = List<int>.generate(16, (_) => rand.nextInt(256));
    return bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
  }

  String _hmac(String body, String nonce, String timestamp) {
    final data = '$body$nonce$timestamp';
    final hmacSha256 = Hmac(sha256, utf8.encode(secretKey!));
    final digest = hmacSha256.convert(utf8.encode(data));
    return digest.toString();
  }
}
