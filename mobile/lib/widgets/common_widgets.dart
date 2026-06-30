import 'package:flutter/material.dart';
import '../config/theme.dart';

/// 状态标签组件
class StatusBadge extends StatelessWidget {
  final String status;
  const StatusBadge(this.status, {super.key});

  @override
  Widget build(BuildContext context) {
    final color = _color();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        _label(),
        style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w500),
      ),
    );
  }

  Color _color() => switch (status) {
        'active' => AppTheme.success,
        'pending' => AppTheme.warning,
        'expired' => AppTheme.danger,
        'suspended' => AppTheme.warning,
        'revoked' => AppTheme.danger,
        _ => AppTheme.info,
      };

  String _label() => switch (status) {
        'active' => '活跃',
        'pending' => '待激活',
        'expired' => '已过期',
        'suspended' => '已暂停',
        'revoked' => '已吊销',
        _ => status,
      };
}

/// 空状态
class EmptyState extends StatelessWidget {
  final IconData icon;
  final String message;
  const EmptyState({super.key, this.icon = Icons.inbox_outlined, this.message = '暂无数据'});

  @override
  Widget build(BuildContext context) => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 64, color: Colors.grey[400]),
            const SizedBox(height: 16),
            Text(message, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 16)),
          ],
        ),
      );
}

/// 加载错误
class ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback? onRetry;
  const ErrorView({super.key, required this.message, this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48, color: AppTheme.danger),
              const SizedBox(height: 16),
              Text(message, textAlign: TextAlign.center, style: const TextStyle(color: AppTheme.textSecondary)),
              if (onRetry != null) ...[
                const SizedBox(height: 16),
                FilledButton.tonal(onPressed: onRetry, child: const Text('重试')),
              ],
            ],
          ),
        ),
      );
}
