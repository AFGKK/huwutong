/// 推送通知配置
class PushConfig {
  /// 是否启用推送通知
  static bool pushEnabled = true;

  /// 是否启用告警推送
  static bool alertPush = true;

  /// 是否启用过期提醒
  static bool expiryReminder = true;

  /// 是否启用系统通知
  static bool systemNotification = true;

  /// Firebase Cloud Messaging 主题
  static const String fcmTopic = 'hwt_license';

  /// 本地通知频道配置
  static const String alertChannelId = 'hwt_alerts';
  static const String alertChannelName = '安全告警';
  static const String reminderChannelId = 'hwt_reminders';
  static const String reminderChannelName = '过期提醒';
  static const String generalChannelId = 'hwt_general';
  static const String generalChannelName = '一般通知';
}
