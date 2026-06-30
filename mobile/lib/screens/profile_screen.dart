import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:local_auth/local_auth.dart';
import '../config/theme.dart';
import '../providers/auth_provider.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('我的')),
      body: Consumer<AuthProvider>(
        builder: (context, auth, _) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            // User Card
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 28,
                      backgroundColor: AppTheme.primary,
                      child: const Icon(Icons.person, color: Colors.white, size: 32),
                    ),
                    const SizedBox(width: 16),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(auth.userName ?? '用户', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
                        const SizedBox(height: 4),
                        Text(auth.userEmail ?? '未登录', style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            // Menu
            _menuItem(context, Icons.fingerprint, '生物识别登录',
                subtitle: auth.biometricEnabled ? '已启用' : '使用指纹或面容快速登录',
                trailing: Switch(
                  value: auth.biometricEnabled,
                  onChanged: (v) {
                    if (v) {
                      _setupBiometric(context);
                    } else {
                      auth.disableBiometric();
                    }
                  },
                ),
                onTap: () => auth.biometricEnabled ? auth.disableBiometric() : _setupBiometric(context)),
          _menuItem(context, Icons.dark_mode_outlined, '深色模式', trailing: Switch(value: false, onChanged: (_) {})),
          _menuItem(context, Icons.notifications_outlined, '推送通知', trailing: Switch(value: true, onChanged: (_) {})),
          const Divider(height: 32),
          _menuItem(context, Icons.info_outline, '关于', subtitle: 'v1.0.0'),
          _menuItem(context, Icons.description_outlined, '开源许可'),

          const SizedBox(height: 32),
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () => _logout(context),
              icon: const Icon(Icons.logout, color: AppTheme.danger),
              label: const Text('退出登录', style: TextStyle(color: AppTheme.danger)),
              style: OutlinedButton.styleFrom(side: const BorderSide(color: AppTheme.danger)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _menuItem(BuildContext context, IconData icon, String title, {String? subtitle, Widget? trailing, VoidCallback? onTap}) => Card(
        margin: const EdgeInsets.only(bottom: 4),
        child: ListTile(
          leading: Icon(icon, color: AppTheme.textSecondary),
          title: Text(title),
          subtitle: subtitle != null ? Text(subtitle, style: const TextStyle(fontSize: 12)) : null,
          trailing: trailing ?? const Icon(Icons.chevron_right, color: AppTheme.textSecondary),
          onTap: onTap,
        ),
      );

  Future<void> _setupBiometric(BuildContext context) async {
    final auth = LocalAuthentication();
    final canCheck = await auth.canCheckBiometrics;
    if (!canCheck) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('设备不支持生物识别')));
      return;
    }
    final authenticated = await auth.authenticate(localizedReason: '验证身份以启用生物识别登录');
    if (authenticated && context.mounted) {
      await context.read<AuthProvider>().enableBiometric(
        context.read<AuthProvider>().userEmail ?? '',
        '',
      );
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('生物识别登录已启用'), backgroundColor: AppTheme.success));
      }
    }
  }

  void _logout(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('确认退出'),
        content: const Text('退出后需要重新登录'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('取消')),
          FilledButton(onPressed: () { Navigator.pop(ctx); context.read<AuthProvider>().logout(); }, child: const Text('退出')),
        ],
      ),
    );
  }
}
