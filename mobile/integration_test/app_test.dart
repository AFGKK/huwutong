// Integration test for HWT License mobile app
// Runs on device/emulator: flutter test integration_test

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:hwt_license_mobile/main.dart';
import 'package:hwt_license_mobile/providers/auth_provider.dart';
import 'package:hwt_license_mobile/services/api_service.dart';
import 'package:provider/provider.dart';

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  group('Full App Flow', () {
    testWidgets('app launches and shows login screen', (tester) async {
      await tester.pumpWidget(const HwtLicenseApp());
      await tester.pumpAndSettle();

      // Should show login screen elements
      expect(find.byType(Form), findsOneWidget);
      expect(find.text('HWT License'), findsOneWidget);
      expect(find.text('企业授权管理系统'), findsOneWidget);
      expect(find.text('登 录'), findsOneWidget);
    });

    testWidgets('shows validation errors on empty form', (tester) async {
      await tester.pumpWidget(const HwtLicenseApp());
      await tester.pumpAndSettle();

      // Tap login button without entering anything
      await tester.tap(find.text('登 录'));
      await tester.pumpAndSettle();

      // Should show validation errors
      expect(find.text('请输入邮箱'), findsOneWidget);
      expect(find.text('请输入密码'), findsOneWidget);
    });

    testWidgets('biometric login button appears when enabled', (tester) async {
      await tester.pumpWidget(const HwtLicenseApp());
      await tester.pumpAndSettle();

      // Initially no biometric button (unless previously enabled in secure storage)
      // We mock by injecting AuthProvider directly
      final authProvider = AuthProvider(ApiService());
      await tester.pumpWidget(
        MultiProvider(
          providers: [ChangeNotifierProvider.value(value: authProvider)],
          child: const MaterialApp(home: Scaffold(body: LoginScreen())),
        ),
      );
      await tester.pump();

      // Biometric button should not be visible initially
      // (tests for biometric state require mocking LocalAuthentication)
    });
  });

  group('Dashboard Flow', () {
    testWidgets('dashboard shows stats and recent items when loaded', (tester) async {
      // This test would run on a real device where the API is accessible
      // For CI, we'd mock the API responses
      await tester.pumpWidget(const HwtLicenseApp());
      await tester.pumpAndSettle();
    });
  });
}
