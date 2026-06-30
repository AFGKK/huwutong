import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shimmer/shimmer.dart';
import '../config/theme.dart';
import '../providers/app_providers.dart';
import '../models/models.dart';
import 'devices_screen.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('仪表盘')),
      body: Consumer<DashboardProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading) return _buildShimmer();
          if (provider.error != null) return _buildError(context, provider);
          return RefreshIndicator(
            onRefresh: provider.load,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _buildStatsGrid(context, provider.stats),
                const SizedBox(height: 16),
                _buildPendingApprovals(context),
                const SizedBox(height: 24),
                _buildRecentLicenses(context, provider.recentLicenses),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildShimmer() => Shimmer.fromColors(
        baseColor: Colors.grey[300]!,
        highlightColor: Colors.grey[100]!,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Row(children: List.generate(2, (_) => Expanded(child: Container(height: 100, margin: const EdgeInsets.all(4), decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12))))),
            const SizedBox(height: 8),
            Row(children: List.generate(2, (_) => Expanded(child: Container(height: 100, margin: const EdgeInsets.all(4), decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12))))),
            const SizedBox(height: 24),
            Container(height: 200, decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12))),
          ],
        ),
      );

  Widget _buildError(BuildContext context, DashboardProvider provider) => Center(
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

  Widget _buildStatsGrid(BuildContext context, DashboardStats stats) {
    final cards = [
      _StatCard('全部 License', '${stats.totalLicenses}', Icons.vpn_key, AppTheme.primary),
      _StatCard('活跃中', '${stats.activeLicenses}', Icons.check_circle, AppTheme.success),
      _StatCard('即将过期', '${stats.expiringSoon}', Icons.warning_amber, AppTheme.warning),
      _StatCard('设备数', '${stats.totalDevices}', Icons.devices, AppTheme.info, onTap: () {
        Navigator.of(context).push(MaterialPageRoute(builder: (_) => const DevicesScreen()));
      }),
    ];

    return Column(
      children: [
        Row(children: cards.take(2).toList()),
        const SizedBox(height: 8),
        Row(children: cards.skip(2).toList()),
      ],
    );
  }

  Widget _buildPendingApprovals(BuildContext context) {
    final approvalProvider = context.watch<ApprovalProvider>();
    if (approvalProvider.pendingCount == 0) return const SizedBox();

    return Card(
      color: AppTheme.warning.withOpacity(0.08),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () {
          // Navigate to approvals tab (index 2)
          final scaffold = Scaffold.maybeOf(context);
          scaffold?.showBottomSheet((ctx) => const SizedBox());
        },
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppTheme.warning.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.how_to_vote, color: AppTheme.warning, size: 24),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('待处理审批', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15, color: AppTheme.warning)),
                    const SizedBox(height: 2),
                    Text('${approvalProvider.pendingCount} 个设备激活请求待审批', style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: AppTheme.warning),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildRecentLicenses(BuildContext context, List<License> licenses) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('最近 License', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600)),
          const SizedBox(height: 12),
          ...licenses.take(5).map((l) => Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  leading: Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(color: licenseStatusColor(l.status), shape: BoxShape.circle),
                  ),
                  title: Text(l.licenseKey, style: const TextStyle(fontFamily: 'monospace', fontSize: 13)),
                  subtitle: Text('${l.productName ?? '-'}  ·  ${l.customerName ?? '-'}'),
                  trailing: Text(licenseStatusLabel(l.status), style: TextStyle(color: licenseStatusColor(l.status), fontSize: 12)),
                ),
              )),
        ],
      );
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;

  const _StatCard(this.label, this.value, this.icon, this.color, {this.onTap});

  @override
  Widget build(BuildContext context) => Expanded(
        child: Card(
          margin: const EdgeInsets.all(4),
          child: InkWell(
            borderRadius: BorderRadius.circular(12),
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(icon, color: color, size: 20),
                      const Spacer(),
                      if (onTap != null)
                        const Icon(Icons.chevron_right, size: 16, color: AppTheme.textSecondary),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(value, style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold, color: color)),
                  Text(label, style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                ],
              ),
            ),
          ),
        ),
      );
}
