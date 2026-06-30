/// HWT License 移动端 - API 环境配置
class ApiConfig {
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api', // Android emulator -> host
  );

  static const Duration timeout = Duration(seconds: 30);
  static const Duration connectTimeout = Duration(seconds: 10);

  // WebSocket (Reverb)
  static const String wsHost = String.fromEnvironment(
    'WS_HOST',
    defaultValue: '10.0.2.2',
  );
  static const int wsPort = 8080;

  // 重试策略
  static const int maxRetries = 3;
  static const Duration retryDelay = Duration(seconds: 2);
}
