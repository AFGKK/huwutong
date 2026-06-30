import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../config/theme.dart';
import '../models/models.dart';
import '../providers/app_providers.dart';

class LicensesScreen extends StatefulWidget {
  const LicensesScreen({super.key});

  @override
  State<LicensesScreen> createState() => _LicensesScreenState();
}

class _LicensesScreenState extends State<LicensesScreen> {
  final _statuses = ['all', 'active', 'expired', 'pending', 'suspended'];
  final _labels = ['全部', '活跃', '已过期', '待激活', '已暂停'];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<LicenseProvider>().load();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('License 管理')),
      body: Column(
        children: [
          // Filter chips
          Container(
            height: 48,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Consumer<LicenseProvider>(
              builder: (context, provider, _) => ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: _statuses.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, i) => FilterChip(
                  label: Text(_labels[i]),
                  selected: provider.statusFilter == _statuses[i],
                  onSelected: (_) => provider.load(status: _statuses[i]),
                  selectedColor: AppTheme.primary.withOpacity(0.12),
                  checkmarkColor: AppTheme.primary,
                ),
              ),
            ),
          ),
          const Divider(height: 1),

          // List
          Expanded(
            child: Consumer<LicenseProvider>(
              builder: (context, provider, _) {
                if (provider.isLoading) return const Center(child: CircularProgressIndicator());
                if (provider.licenses.isEmpty) return const Center(child: Text('暂无数据', style: TextStyle(color: AppTheme.textSecondary)));
                return RefreshIndicator(
                  onRefresh: () => provider.load(),
                  child: ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: provider.licenses.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 8),
                    itemBuilder: (context, i) => _LicenseCard(license: provider.licenses[i]),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _LicenseCard extends StatelessWidget {
  final License license;
  const _LicenseCard({required this.license});

  @override
  Widget build(BuildContext context) => Card(
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () => _showDetail(context),
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
                        color: licenseStatusColor(license.status).withOpacity(0.12),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(
                        licenseStatusLabel(license.status),
                        style: TextStyle(fontSize: 11, color: licenseStatusColor(license.status), fontWeight: FontWeight.w500),
                      ),
                    ),
                    const Spacer(),
                    Text(license.licenseKey, style: const TextStyle(fontFamily: 'monospace', fontSize: 12, color: AppTheme.textSecondary)),
                  ],
                ),
                const SizedBox(height: 8),
                if (license.productName != null)
                  Text(license.productName!, style: const TextStyle(fontWeight: FontWeight.w500)),
                const SizedBox(height: 4),
                if (license.expiresAt != null)
                  Text('到期: ${_fmt(license.expiresAt!)}', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
              ],
            ),
          ),
        ),
      );

  String _fmt(DateTime d) => '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  void _showDetail(BuildContext context) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (_) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(license.licenseKey, style: const TextStyle(fontFamily: 'monospace', fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 16),
            _row('状态', licenseStatusLabel(license.status)),
            _row('产品', license.productName ?? '-'),
            _row('客户', license.customerName ?? '-'),
            _row('席位', '${license.usedSeats}/${license.seats}'),
            _row('过期时间', license.expiresAt?.toString().substring(0, 10) ?? '永久'),
            const SizedBox(height: 24),
            Row(
              children: [
                if (license.isActive) ...[
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        Navigator.pop(context);
                        _suspendLicense(context);
                      },
                      icon: const Icon(Icons.pause, size: 18),
                      label: const Text('暂停'),
                      style: OutlinedButton.styleFrom(foregroundColor: AppTheme.warning, side: const BorderSide(color: AppTheme.warning)),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        Navigator.pop(context);
                        _revokeLicense(context);
                      },
                      icon: const Icon(Icons.block, size: 18),
                      label: const Text('吊销'),
                      style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger, side: const BorderSide(color: AppTheme.danger)),
                    ),
                  ),
                ] else ...[
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close),
                      label: const Text('关闭'),
                    ),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _suspendLicense(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('暂停 License'),
        content: Text('确定要暂停 License ${license.licenseKey} 吗？'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('取消')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('确认暂停')),
        ],
      ),
    );
    if (confirmed == true && context.mounted) {
      try {
        await context.read<LicenseProvider>().suspendLicense(license.id);
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('License 已暂停'), backgroundColor: AppTheme.success));
        }
      } catch (e) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('操作失败'), backgroundColor: AppTheme.danger));
        }
      }
    }
  }

  Future<void> _revokeLicense(BuildContext context) async {
    final reason = await showDialog<String>(
      context: context,
      builder: (ctx) {
        final ctrl = TextEditingController();
        return AlertDialog(
          title: const Text('吊销 License'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text('此操作不可撤销，确定要继续吗？'),
              const SizedBox(height: 12),
              TextField(controller: ctrl, decoration: const InputDecoration(hintText: '吊销原因（可选）'), maxLines: 2),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('取消')),
            FilledButton(onPressed: () => Navigator.pop(ctx, ctrl.text), style: FilledButton.styleFrom(backgroundColor: AppTheme.danger), child: const Text('确认吊销')),
          ],
        );
      },
    );
    if (reason != null && context.mounted) {
      try {
        await context.read<LicenseProvider>().revokeLicense(license.id, reason: reason.isEmpty ? null : reason);
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('License 已吊销'), backgroundColor: AppTheme.danger));
        }
      } catch (e) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('操作失败'), backgroundColor: AppTheme.danger));
        }
      }
    }
  }

  Widget _row(String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [Text(label, style: const TextStyle(color: AppTheme.textSecondary)), Text(value)],
        ),
      );
}
