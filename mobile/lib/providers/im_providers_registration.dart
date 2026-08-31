import 'package:flutter/material.dart';
import '../services/api_service.dart';
import '../services/im_api_service.dart';
import '../services/im_websocket_service.dart';
import '../providers/im_providers.dart';
import '../providers/app_providers.dart';
import '../providers/auth_provider.dart';
import 'package:provider/provider.dart';

/// 注册所有 IM Provider
///
/// 调用: ImProviders.register(providers, api, ws)
class ImProviders {
  static void register(
    List<SingleChildWidget> providers,
    ApiService apiService,
    ImWebSocketService ws,
  ) {
    final dio = apiService.dio; // 使用 ApiService 的 Dio 实例
    final imApi = ImApiService(dio);

    providers.addAll([
      ChangeNotifierProvider<ConversationsProvider>(
        create: (_) => ConversationsProvider(imApi, ws),
      ),
      Provider<ImApiService>.value(value: imApi),
    ]);
  }
}
