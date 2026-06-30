/// HWT API 异常 (M2-34 标准)
class HwtApiException implements Exception {
  final String code;
  final String message;
  final int httpStatus;

  HwtApiException(this.code, this.message, {this.httpStatus = 400});

  @override
  String toString() => '[$code] $message';
}

/// HWT 网络异常
class HwtNetworkException implements Exception {
  final String message;

  HwtNetworkException(this.message);

  @override
  String toString() => 'Network error: $message';
}
