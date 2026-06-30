import 'package:flutter/material.dart';

class AppTheme {
  // Brand colors
  static const Color primary = Color(0xFF1A73E8);
  static const Color primaryDark = Color(0xFF1557B0);
  static const Color primaryLight = Color(0xFF4A90D9);

  static const Color success = Color(0xFF34A853);
  static const Color warning = Color(0xFFFBBC04);
  static const Color danger = Color(0xFFEA4335);
  static const Color info = Color(0xFF9AA0A6);

  static const Color bgLight = Color(0xFFF8F9FA);
  static const Color bgDark = Color(0xFF1F1F1F);
  static const Color surfaceDark = Color(0xFF2D2D2D);
  static const Color textPrimary = Color(0xFF202124);
  static const Color textSecondary = Color(0xFF5F6368);
  static const Color divider = Color(0xFFDADCE0);

  static ThemeData get lightTheme => ThemeData(
        useMaterial3: true,
        brightness: Brightness.light,
        colorSchemeSeed: primary,
        scaffoldBackgroundColor: bgLight,
        appBarTheme: const AppBarTheme(
          centerTitle: true,
          elevation: 0,
          backgroundColor: Colors.white,
          foregroundColor: textPrimary,
        ),
        cardTheme: CardTheme(
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: const BorderSide(color: divider, width: 0.5),
          ),
        ),
        navigationBarTheme: NavigationBarThemeData(
          elevation: 0,
          indicatorColor: primary.withOpacity(0.12),
          labelBehavior: NavigationDestinationLabelBehavior.onlyShowSelected,
        ),
        segmentedButtonTheme: SegmentedButtonThemeData(
          style: ButtonStyle(
            shape: WidgetStatePropertyAll(
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
          ),
        ),
      );

  static ThemeData get darkTheme => ThemeData(
        useMaterial3: true,
        brightness: Brightness.dark,
        colorSchemeSeed: primary,
        scaffoldBackgroundColor: bgDark,
        appBarTheme: const AppBarTheme(
          centerTitle: true,
          elevation: 0,
          backgroundColor: surfaceDark,
        ),
        cardTheme: CardTheme(
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      );
}

// ─── License Status Colors ───
Color licenseStatusColor(String status) => switch (status) {
      'active' => AppTheme.success,
      'pending' => AppTheme.warning,
      'suspended' => AppTheme.warning,
      'frozen' => AppTheme.info,
      'expired' => AppTheme.danger,
      'revoked' => AppTheme.danger,
      'refunded' => AppTheme.danger,
      'blacklisted' => Colors.black,
      _ => AppTheme.info,
    };

String licenseStatusLabel(String status) => switch (status) {
      'active' => '活跃',
      'pending' => '待激活',
      'suspended' => '已暂停',
      'frozen' => '已冻结',
      'expired' => '已过期',
      'revoked' => '已吊销',
      'refunded' => '已退款',
      'blacklisted' => '黑名单',
      _ => status,
    };
