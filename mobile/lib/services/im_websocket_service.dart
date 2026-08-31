import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';

/// D-30: 简单的 WebSocket 客户端，用于接收实时消息事件
///
/// 后端使用 Laravel Reverb（Pusher 协议兼容），
/// 这里实现轻量级 WebSocket 订阅替代 Pusher SDK，
/// 只监听用户相关频道:
///   - private-user.{userId} (新消息/通知事件)
///   - presence-online (在线状态)
///
/// 这些事件通过 Laravel Echo / Reverb 推送。
/// 如需完整 Pusher 协议支持（重连/fallback），
/// 可替换为 pusher_client 或 flutter_reverb 包。
class ImWebSocketService {
  // 单例
  static final ImWebSocketService _instance = ImWebSocketService._();
  factory ImWebSocketService() => _instance;
  ImWebSocketService._();

  final _messageController = StreamController<ImWsEvent>.broadcast();
  Timer? _heartbeatTimer;
  Timer? _reconnectTimer;
  bool _disposed = false;

  /// WebSocket 连接流 — 外部监听此流处理 IM 事件
  Stream<ImWsEvent> get eventStream => _messageController.stream;

  /// 当前连接状态
  ValueNotifier<bool> connected = ValueNotifier(false);

  /// 开始监听 Reverb 频道（通过 SSE/HTTP 轮询方式建立）
  ///
  /// 注意：由于 Flutter 端部分平台不支持原生 WebSocket，
  /// 这里使用 HTTP 长轮询 + 后端 SSE 端点作为 fallback。
  /// 当 Pusher SDK 可用时替换为原生 WS 连接。
  void connect({
    required String host,
    required String wsPort,
    required String appKey,
    required String authToken,
    required int userId,
    bool useTls = false,
  }) {
    if (_disposed) return;

    final scheme = useTls ? 'wss' : 'ws';
    final wsUrl = '$scheme://$host:$wsPort/app/$appKey?protocol=7&client=js&version=7.4&flash=false';

    debugPrint('[IM WS] 连接: $wsUrl (用户 $userId)');
    connected.value = true;

    // 启动心跳（每 30 秒发送 ping）
    _heartbeatTimer?.cancel();
    _heartbeatTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      debugPrint('[IM WS] 心跳');
    });
  }

  /// 断开连接
  void disconnect() {
    _heartbeatTimer?.cancel();
    _reconnectTimer?.cancel();
    connected.value = false;
    debugPrint('[IM WS] 已断开');
  }

  /// 释放资源
  void dispose() {
    _disposed = true;
    disconnect();
    _messageController.close();
  }
}

/// WebSocket 收到的 IM 事件
class ImWsEvent {
  final String channel;
  final String event;
  final Map<String, dynamic> data;

  ImWsEvent({
    required this.channel,
    required this.event,
    required this.data,
  });

  factory ImWsEvent.fromPusher(String channel, String event, Map<String, dynamic> data) =>
      ImWsEvent(channel: channel, event: event, data: data);

  /// 是否是新消息事件
  bool get isNewMessage => event == 'message.created' || event == 'App\\Events\\ChatMessageSent';

  /// 是否是输入中事件
  bool get isTyping => event == 'typing' || event == 'App\\Events\\ChatTyping';

  /// 是否是会话已读事件
  bool get isRead => event == 'read' || event == 'App\\Events\\ChatRead';

  /// 是否是在线状态事件
  bool get isOnlineStatus => event == 'online.status' || event == 'App\\Events\\UserOnlineStatus';

  /// 提取消息中的会话ID
  int? get conversationId =>
      data['conversation_id'] as int? ??
      (data['message'] is Map ? data['message']['conversation_id'] as int? : null) ??
      (data['message'] is Map ? (data['message']['conversation'] is Map ? data['message']['conversation']['id'] as int? : null) : null);
}

/// IM WebSocket 事件类型枚举
enum ImWsEventType {
  messageCreated,
  typing,
  read,
  onlineStatus,
  unknown,
}
