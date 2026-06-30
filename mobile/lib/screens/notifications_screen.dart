import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../config/theme.dart';
import '../models/models.dart';
import '../providers/app_providers.dart';

class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('通知中心'),
        actions: [
          Consumer<NotificationProvider>(
            builder: (context, provider, _) => provider.unreadCount > 0
                ? TextButton(
                    onPressed: () => provider.markAllAsRead(),
                    child: const Text('全部已读'),
                  )
                : const SizedBox(),
          ),
        ],
      ),
      body: Consumer<NotificationProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading) return const Center(child: CircularProgressIndicator());
          if (provider.notifications.isEmpty) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.notifications_none, size: 64, color: Colors.grey[400]),
                  const SizedBox(height: 16),
                  const Text('暂无通知', style: TextStyle(color: AppTheme.textSecondary, fontSize: 16)),
                ],
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: provider.load,
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: provider.notifications.length,
              separatorBuilder: (_, __) => const SizedBox(height: 4),
              itemBuilder: (context, i) => _NotificationItem(
                notification: provider.notifications[i],
                onTap: () => provider.markAsRead(provider.notifications[i].id),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _NotificationItem extends StatelessWidget {
  final Notification notification;
  final VoidCallback onTap;

  const _NotificationItem({required this.notification, required this.onTap});

  @override
  Widget build(BuildContext context) => Card(
        color: notification.isRead ? null : AppTheme.primary.withOpacity(0.03),
        child: ListTile(
          leading: CircleAvatar(
            radius: 20,
            backgroundColor: _typeColor(notification.type).withOpacity(0.12),
            child: Icon(_typeIcon(notification.type), color: _typeColor(notification.type), size: 20),
          ),
          title: Text(notification.title, style: TextStyle(fontWeight: notification.isRead ? FontWeight.normal : FontWeight.w600)),
          subtitle: notification.content != null ? Text(notification.content!, maxLines: 2, overflow: TextOverflow.ellipsis) : null,
          trailing: !notification.isRead
              ? Container(width: 8, height: 8, decoration: const BoxDecoration(color: AppTheme.primary, shape: BoxShape.circle))
              : null,
          onTap: onTap,
        ),
      );

  Color _typeColor(String type) => switch (type) {
        'alert' => AppTheme.danger,
        'warning' => AppTheme.warning,
        'success' => AppTheme.success,
        _ => AppTheme.primary,
      };

  IconData _typeIcon(String type) => switch (type) {
        'alert' => Icons.error,
        'warning' => Icons.warning,
        'success' => Icons.check_circle,
        _ => Icons.info,
      };
}
