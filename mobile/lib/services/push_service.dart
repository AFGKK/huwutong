import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import '../config/push_config.dart';

/// 推送通知服务 — FCM + 本地通知
class PushService {
  static final PushService _instance = PushService._();
  factory PushService() => _instance;
  PushService._();

  final _flutterLocalNotificationsPlugin = FlutterLocalNotificationsPlugin();
  final _firebaseMessaging = FirebaseMessaging.instance;

  /// FCM Token（用于服务端推送）
  String? fcmToken;

  /// 初始化推送通知
  Future<void> init() async {
    // ── 本地通知渠道 ──
    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosSettings = DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );
    const initSettings = InitializationSettings(
      android: androidSettings,
      iOS: iosSettings,
    );

    await _flutterLocalNotificationsPlugin.initialize(
      initSettings,
      onDidReceiveNotificationResponse: _onNotificationTap,
    );

    // ── 创建通知渠道（Android 8+） ──
    await _createNotificationChannels();

    // ── FCM ──
    await _setupFcm();
  }

  Future<void> _createNotificationChannels() async {
    const androidPlugin = _flutterLocalNotificationsPlugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();

    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        PushConfig.alertChannelId,
        PushConfig.alertChannelName,
        description: '安全告警通知',
        importance: Importance.high,
        playSound: true,
        enableVibration: true,
      ),
    );

    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        PushConfig.reminderChannelId,
        PushConfig.reminderChannelName,
        description: 'License 过期提醒',
        importance: Importance.defaultImportance,
      ),
    );

    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        PushConfig.generalChannelId,
        PushConfig.generalChannelName,
        description: '一般通知消息',
        importance: Importance.defaultImportance,
      ),
    );
  }

  Future<void> _setupFcm() async {
    // 请求通知权限（iOS）
    final notificationSettings = await _firebaseMessaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );

    if (notificationSettings.authorizationStatus == AuthorizationStatus.authorized ||
        notificationSettings.authorizationStatus == AuthorizationStatus.provisional) {
      // 获取 FCM Token
      fcmToken = await _firebaseMessaging.getToken();
    }

    // 前台消息处理
    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

    // 后台消息点击
    FirebaseMessaging.onMessageOpenedApp.listen(_handleNotificationTap);

    // 应用从终止状态启动（点击通知）
    final initialMessage = await _firebaseMessaging.getInitialMessage();
    if (initialMessage != null) {
      _handleNotificationData(initialMessage.data);
    }
  }

  void _handleForegroundMessage(RemoteMessage message) {
    final notification = message.notification;
    if (notification == null) return;

    final channelId = _resolveChannelId(message.data['type'] as String?);

    _flutterLocalNotificationsPlugin.show(
      message.hashCode,
      notification.title,
      notification.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          channelId,
          _channelName(channelId),
          importance: channelId == PushConfig.alertChannelId
              ? Importance.high
              : Importance.defaultImportance,
          priority: channelId == PushConfig.alertChannelId
              ? Priority.high
              : Priority.defaultPriority,
        ),
        iOS: const DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
        ),
      ),
      payload: _buildPayload(message.data),
    );
  }

  void _onNotificationTap(NotificationResponse response) {
    final payload = response.payload;
    if (payload != null) {
      _navigateFromPayload(payload);
    }
  }

  void _handleNotificationTap(RemoteMessage message) {
    _handleNotificationData(message.data);
  }

  void _handleNotificationData(Map<String, dynamic> data) {
    final payload = _buildPayload(data);
    _navigateFromPayload(payload);
  }

  String _resolveChannelId(String? type) => switch (type) {
        'alert' => PushConfig.alertChannelId,
        'reminder' => PushConfig.reminderChannelId,
        _ => PushConfig.generalChannelId,
      };

  String _channelName(String channelId) => switch (channelId) {
        PushConfig.alertChannelId => PushConfig.alertChannelName,
        PushConfig.reminderChannelId => PushConfig.reminderChannelName,
        _ => PushConfig.generalChannelName,
      };

  String _buildPayload(Map<String, dynamic> data) {
    // 序列化导航路由参数
    final screen = data['screen'] as String? ?? '';
    final id = data['id'] as String? ?? '';
    return '$screen|$id';
  }

  void _navigateFromPayload(String payload) {
    // 由主应用通过全局 navigatorKey 处理
    // 格式: "screen_type|id"
  }

  /// 显示本地通知（用于定时过期提醒等）
  Future<void> showLocalNotification({
    required int id,
    required String title,
    required String body,
    String? type,
    int? millisecondsSinceEpoch,
  }) async {
    final channelId = _resolveChannelId(type);

    await _flutterLocalNotificationsPlugin.show(
      id,
      title,
      body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          channelId,
          _channelName(channelId),
          importance: channelId == PushConfig.alertChannelId
              ? Importance.high
              : Importance.defaultImportance,
        ),
        iOS: const DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
        ),
      ),
    );
  }

  /// 取消所有通知
  Future<void> cancelAll() async {
    await _flutterLocalNotificationsPlugin.cancelAll();
  }
}
