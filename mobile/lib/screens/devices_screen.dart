import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../config/theme.dart';
import '../models/models.dart';
import '../providers/app_providers.dart';

/// 设备管理页面
class DevicesScreen extends StatefulWidget {
  const DevicesScreen({super.key});

  @override
  State<DevicesScreen> createState() => _DevicesScreenState();
}

class _DevicesScreenState extends State<DevicesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<DeviceProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('设备管理'),
        actions: [
          Consumer<DeviceProvider>(
            builder: (context, provider, _) => Padding(
              padding: const EdgeInsets.only(right: 8),
              child: Text('共 ${provider.devices.length} 台',
                  style: const TextStyle(fontSize: 13, color: AppTheme.textSecondary)),
            ),
          ),
        ],
      ),
      body: Consumer<DeviceProvider>(
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
          if (provider.devices.isEmpty) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.devices_other, size: 64, color: Colors.grey[400]),
                  const SizedBox(height: 16),
                  const Text('暂无设备记录', style: TextStyle(color: AppTheme.textSecondary, fontSize: 16)),
                ],
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: provider.load,
            child: ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: provider.devices.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) => _DeviceCard(
                device: provider.devices[i],
                onRemove: () => _removeDevice(context, provider.devices[i]),
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _removeDevice(BuildContext context, Device device) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('移除设备'),
        content: Text('确定要移除设备 "${device.name ?? device.fingerprint}" 吗？'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('取消')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: AppTheme.danger),
            child: const Text('确认移除'),
          ),
        ],
      ),
    );
    if (confirmed == true && context.mounted) {
      try {
        await context.read<DeviceProvider>().removeDevice(device.id);
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('设备已移除'), backgroundColor: AppTheme.success),
          );
        }
      } catch (e) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('操作失败'), backgroundColor: AppTheme.danger),
          );
        }
      }
    }
  }
}

class _DeviceCard extends StatelessWidget {
  final Device device;
  final VoidCallback onRemove;

  const _DeviceCard({required this.device, required this.onRemove});

  @override
  Widget build(BuildContext context) => Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: _platformColor().withOpacity(0.1),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(_platformIcon(), color: _platformColor(), size: 22),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          device.name ?? '未知设备',
                          style: const TextStyle(fontWeight: FontWeight.w500),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          device.platform ?? '未知平台',
                          style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
                        ),
                      ],
                    ),
                  ),
                  if (device.isTrusted)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppTheme.success.withOpacity(0.12),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: const Text('信任', style: TextStyle(fontSize: 10, color: AppTheme.success)),
                    ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  _infoChip(Icons.fingerprint, device.fingerprint.length > 16
                      ? '${device.fingerprint.substring(0, 16)}...'
                      : device.fingerprint),
                  const Spacer(),
                  if (device.trustScore > 0)
                    Text('可信度 ${device.trustScore}%',
                        style: TextStyle(fontSize: 11, color: _trustScoreColor(device.trustScore))),
                ],
              ),
              if (device.lastSeenAt != null) ...[
                const SizedBox(height: 4),
                Text('最后活跃: ${_fmt(device.lastSeenAt!)}',
                    style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary)),
              ],
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: onRemove,
                  icon: const Icon(Icons.delete_outline, size: 16),
                  label: const Text('移除设备'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppTheme.danger,
                    side: const BorderSide(color: AppTheme.danger),
                  ),
                ),
              ),
            ],
          ),
        ),
      );

  Widget _infoChip(IconData icon, String text) => Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: AppTheme.textSecondary),
          const SizedBox(width: 4),
          Text(text, style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary, fontFamily: 'monospace')),
        ],
      );

  IconData _platformIcon() => switch (device.platform?.toLowerCase()) {
        'ios' => Icons.phone_iphone,
        'android' => Icons.android,
        'windows' => Icons.laptop_windows,
        'macos' => Icons.laptop_mac,
        'linux' => Icons.laptop_chromebook,
        _ => Icons.devices,
      };

  Color _platformColor() => switch (device.platform?.toLowerCase()) {
        'ios' => Colors.black,
        'android' => const Color(0xFF34A853),
        'windows' => const Color(0xFF0078D4),
        'macos' => Colors.grey,
        'linux' => const Color(0xFFFCC624),
        _ => AppTheme.info,
      };

  Color _trustScoreColor(int score) => score >= 80
      ? AppTheme.success
      : score >= 50
          ? AppTheme.warning
          : AppTheme.danger;

  String _fmt(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')} '
      '${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
}
