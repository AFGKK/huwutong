import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../config/theme.dart';
import '../providers/app_providers.dart';

/// 激活审批页面
class ApprovalsScreen extends StatelessWidget {
  const ApprovalsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('激活审批'),
        actions: [
          Consumer<ApprovalProvider>(
            builder: (context, provider, _) => provider.pendingCount > 0
                ? Padding(
                    padding: const EdgeInsets.only(right: 12),
                    child: Chip(
                      label: Text('${provider.pendingCount} 待处理'),
                      backgroundColor: AppTheme.warning.withOpacity(0.15),
                      labelStyle: const TextStyle(fontSize: 12, color: AppTheme.warning),
                      visualDensity: VisualDensity.compact,
                    ),
                  )
                : const SizedBox(),
          ),
        ],
      ),
      body: Consumer<ApprovalProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading) return const Center(child: CircularProgressIndicator());
          if (provider.error != null) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.error_outline, size: 48, color: AppTheme.danger),
                  const SizedBox(height: 16),
                  Text(provider.error!, style: const TextStyle(color: AppTheme.textSecondary)),
                  const SizedBox(height: 16),
                  FilledButton.tonal(onPressed: provider.load, child: const Text('重试')),
                ],
              ),
            );
          }
          if (provider.approvals.isEmpty) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.check_circle_outline, size: 64, color: Colors.grey[400]),
                  const SizedBox(height: 16),
                  const Text('暂无待审批请求', style: TextStyle(color: AppTheme.textSecondary, fontSize: 16)),
                ],
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: provider.load,
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: provider.approvals.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) => _ApprovalCard(approval: provider.approvals[i]),
            ),
          );
        },
      ),
    );
  }
}

class _ApprovalCard extends StatelessWidget {
  final Map<String, dynamic> approval;
  const _ApprovalCard({required this.approval});

  @override
  Widget build(BuildContext context) {
    final licenseKey = approval['license_key'] as String? ?? '-';
    final deviceName = approval['device_name'] as String? ?? '-';
    final deviceFingerprint = approval['device_fingerprint'] as String? ?? '-';
    final ip = approval['ip'] as String? ?? '-';
    final createdAt = approval['created_at'] as String? ?? '-';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: AppTheme.warning.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: const Text('待审批', style: TextStyle(fontSize: 11, color: AppTheme.warning, fontWeight: FontWeight.w500)),
                ),
                const Spacer(),
                Text(licenseKey, style: const TextStyle(fontFamily: 'monospace', fontSize: 12, color: AppTheme.textSecondary)),
              ],
            ),
            const SizedBox(height: 12),
            _infoRow(Icons.devices, '设备', deviceName),
            const SizedBox(height: 4),
            _infoRow(Icons.fingerprint, '指纹', deviceFingerprint),
            const SizedBox(height: 4),
            _infoRow(Icons.language, 'IP', ip),
            const SizedBox(height: 4),
            _infoRow(Icons.access_time, '时间', createdAt),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _handleReject(context),
                    icon: const Icon(Icons.close, size: 18),
                    label: const Text('拒绝'),
                    style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger, side: const BorderSide(color: AppTheme.danger)),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: () => _handleApprove(context),
                    icon: const Icon(Icons.check, size: 18),
                    label: const Text('批准'),
                    style: FilledButton.styleFrom(backgroundColor: AppTheme.success),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _infoRow(IconData icon, String label, String value) => Row(
        children: [
          Icon(icon, size: 14, color: AppTheme.textSecondary),
          const SizedBox(width: 6),
          Text('$label: ', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
          Expanded(child: Text(value, style: const TextStyle(fontSize: 12), overflow: TextOverflow.ellipsis)),
        ],
      );

  void _handleApprove(BuildContext context) async {
    final id = approval['id'] as int;
    await context.read<ApprovalProvider>().approve(id);
    if (context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('已批准'), backgroundColor: AppTheme.success));
    }
  }

  void _handleReject(BuildContext context) async {
    final id = approval['id'] as int;
    final reason = await showDialog<String>(
      context: context,
      builder: (ctx) {
        final ctrl = TextEditingController();
        return AlertDialog(
          title: const Text('拒绝理由'),
          content: TextField(controller: ctrl, decoration: const InputDecoration(hintText: '请输入拒绝理由（可选）'), maxLines: 2),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('取消')),
            FilledButton(onPressed: () => Navigator.pop(ctx, ctrl.text), child: const Text('确认拒绝')),
          ],
        );
      },
    );
    if (reason != null && context.mounted) {
      await context.read<ApprovalProvider>().reject(id, reason: reason.isEmpty ? null : reason);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('已拒绝'), backgroundColor: AppTheme.danger));
      }
    }
  }
}
