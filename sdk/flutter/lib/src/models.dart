/// HWT SDK 数据模型
class ActivationResult {
  final bool success;
  final ActivationData? data;
  final String? message;
  final ApiError? error;

  ActivationResult({
    required this.success,
    this.data,
    this.message,
    this.error,
  });

  factory ActivationResult.fromJson(Map<String, dynamic> json) {
    return ActivationResult(
      success: json['success'] as bool? ?? false,
      data: json['data'] != null
          ? ActivationData.fromJson(json['data'] as Map<String, dynamic>)
          : null,
      message: json['message'] as String?,
      error: json['error'] != null
          ? ApiError.fromJson(json['error'] as Map<String, dynamic>)
          : null,
    );
  }
}

class ActivationData {
  final String? licenseKey;
  final String? expiresAt;
  final String? deviceId;
  final Map<String, bool>? features;

  ActivationData({this.licenseKey, this.expiresAt, this.deviceId, this.features});

  factory ActivationData.fromJson(Map<String, dynamic> json) {
    return ActivationData(
      licenseKey: json['license_key'] as String?,
      expiresAt: json['expires_at'] as String?,
      deviceId: json['device_id'] as String?,
      features: (json['features'] as Map<String, dynamic>?)
          ?.map((k, v) => MapEntry(k, v as bool)),
    );
  }
}

class ValidationResult {
  final bool success;
  final ValidationData? data;
  final ApiError? error;

  bool get isValid => success && data?.isValid == true;

  ValidationResult({required this.success, this.data, this.error});

  factory ValidationResult.fromJson(Map<String, dynamic> json) {
    return ValidationResult(
      success: json['success'] as bool? ?? false,
      data: json['data'] != null
          ? ValidationData.fromJson(json['data'] as Map<String, dynamic>)
          : null,
      error: json['error'] != null
          ? ApiError.fromJson(json['error'] as Map<String, dynamic>)
          : null,
    );
  }
}

class ValidationData {
  final bool isValid;
  final String? licenseKey;
  final String? status;
  final String? expiresAt;
  final int daysRemaining;
  final Map<String, bool>? features;

  ValidationData({
    required this.isValid,
    this.licenseKey,
    this.status,
    this.expiresAt,
    this.daysRemaining = 0,
    this.features,
  });

  factory ValidationData.fromJson(Map<String, dynamic> json) {
    return ValidationData(
      isValid: json['is_valid'] as bool? ?? false,
      licenseKey: json['license_key'] as String?,
      status: json['status'] as String?,
      expiresAt: json['expires_at'] as String?,
      daysRemaining: json['days_remaining'] as int? ?? 0,
      features: (json['features'] as Map<String, dynamic>?)
          ?.map((k, v) => MapEntry(k, v as bool)),
    );
  }
}

class ApiError {
  final String? code;
  final String? message;

  ApiError({this.code, this.message});

  factory ApiError.fromJson(Map<String, dynamic> json) {
    return ApiError(
      code: json['code'] as String?,
      message: json['message'] as String?,
    );
  }
}
