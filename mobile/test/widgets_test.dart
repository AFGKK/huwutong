import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hwt_license_mobile/config/theme.dart';
import 'package:hwt_license_mobile/models/models.dart';
import 'package:hwt_license_mobile/providers/app_providers.dart';
import 'package:hwt_license_mobile/screens/dashboard_screen.dart';
import 'package:hwt_license_mobile/services/api_service.dart';
import 'package:provider/provider.dart';
import 'package:mockito/mockito.dart';
import 'package:mockito/annotations.dart';

@GenerateMocks([ApiService])
import 'widgets_test.mocks.dart';

void main() {
  group('DashboardScreen', () {
    late MockApiService mockApi;
    late DashboardProvider dashboardProvider;
    late ApprovalProvider approvalProvider;

    setUp(() {
      mockApi = MockApiService();
      dashboardProvider = DashboardProvider(mockApi);
      approvalProvider = ApprovalProvider(mockApi);
    });

    Widget buildTestWidget() {
      return MaterialApp(
        theme: AppTheme.lightTheme,
        home: MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: dashboardProvider),
            ChangeNotifierProvider.value(value: approvalProvider),
          ],
          child: const DashboardScreen(),
        ),
      );
    }

    testWidgets('shows shimmer when loading', (tester) async {
      dashboardProvider.load(); // triggers loading state
      await tester.pumpWidget(buildTestWidget());

      // Should show shimmer placeholder
      expect(find.byType(ShimmerPlaceholder), findsOneWidget);
      // or just check no error/retry button is present
      expect(find.text('重试'), findsNothing);
    });

    testWidgets('shows error state with retry button', (tester) async {
      when(mockApi.getDashboardStats()).thenThrow(Exception('Network error'));
      when(mockApi.getLicenses(page: 1)).thenThrow(Exception('Network error'));

      await dashboardProvider.load();
      await tester.pumpWidget(buildTestWidget());

      expect(find.text('重试'), findsOneWidget);
      expect(find.byIcon(Icons.error_outline), findsOneWidget);
    });

    testWidgets('shows stats grid and recent licenses when loaded', (tester) async {
      when(mockApi.getDashboardStats()).thenAnswer((_) async => const DashboardStats(
        totalLicenses: 100,
        activeLicenses: 80,
        expiringSoon: 5,
        totalDevices: 120,
      ));
      when(mockApi.getLicenses(page: 1)).thenAnswer((_) async => [
        License(id: 1, licenseKey: 'KEY-001', status: 'active', productName: 'HWT Pro', customerName: 'Acme', createdAt: DateTime.now()),
        License(id: 2, licenseKey: 'KEY-002', status: 'expired', productName: 'HWT Basic', customerName: 'Beta', createdAt: DateTime.now()),
      ]);
      when(mockApi.getPendingApprovals()).thenAnswer((_) async => []);

      await dashboardProvider.load();
      await tester.pumpWidget(buildTestWidget());

      // Stats cards should be displayed
      expect(find.text('全部 License'), findsOneWidget);
      expect(find.text('100'), findsOneWidget);
      expect(find.text('活跃中'), findsOneWidget);
      expect(find.text('80'), findsOneWidget);
      expect(find.text('即将过期'), findsOneWidget);
      expect(find.text('5'), findsOneWidget);
      expect(find.text('设备数'), findsOneWidget);
      expect(find.text('120'), findsOneWidget);

      // Recent licenses section
      expect(find.text('最近 License'), findsOneWidget);
      expect(find.text('KEY-001'), findsOneWidget);
      expect(find.text('KEY-002'), findsOneWidget);
    });

    testWidgets('shows pending approvals banner', (tester) async {
      when(mockApi.getDashboardStats()).thenAnswer((_) async => const DashboardStats(
        totalLicenses: 10, activeLicenses: 8, expiringSoon: 0, totalDevices: 5,
      ));
      when(mockApi.getLicenses(page: 1)).thenAnswer((_) async => []);
      when(mockApi.getPendingApprovals()).thenAnswer((_) async => [
        {'id': 1, 'license_key': 'KEY-P1'},
        {'id': 2, 'license_key': 'KEY-P2'},
        {'id': 3, 'license_key': 'KEY-P3'},
      ]);

      await approvalProvider.load();
      await dashboardProvider.load();
      await tester.pumpWidget(buildTestWidget());

      expect(find.text('待处理审批'), findsOneWidget);
      expect(find.text('3 个设备激活请求待审批'), findsOneWidget);
    });
  });

  group('_StatCard', () {
    testWidgets('renders label, value and icon', (tester) async {
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: _StatCard('全部', '42', Icons.vpn_key, Colors.blue),
        ),
      ));

      expect(find.text('全部'), findsOneWidget);
      expect(find.text('42'), findsOneWidget);
      expect(find.byIcon(Icons.vpn_key), findsOneWidget);
    });

    testWidgets('responds to tap when onTap provided', (tester) async {
      bool tapped = false;
      await tester.pumpWidget(MaterialApp(
        home: Scaffold(
          body: _StatCard('设备', '5', Icons.devices, Colors.blue, onTap: () => tapped = true),
        ),
      ));

      await tester.tap(find.text('设备'));
      expect(tapped, isTrue);
    });
  });
}

// Stub Shimmer for test — the dashboard uses Shimmer.fromColors from the package
// In test environment with shimmer, we wrap with a simple stub
class ShimmerPlaceholder extends StatelessWidget {
  const ShimmerPlaceholder({super.key});

  @override
  Widget build(BuildContext context) => Container(key: const Key('shimmer'));
}
