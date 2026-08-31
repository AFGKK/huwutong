import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:firebase_core/firebase_core.dart';
import 'config/theme.dart';
import 'providers/auth_provider.dart';
import 'providers/app_providers.dart';
import 'providers/im_providers.dart';
import 'services/api_service.dart';
import 'services/im_websocket_service.dart';
import 'services/push_service.dart';
import 'screens/login_screen.dart';
import 'screens/home_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  try {
    await Firebase.initializeApp();
  } catch (_) {
    // Firebase 初始化失败（非强制，推送不可用）
  }
  final api = ApiService();

  // D-30: IM WebSocket 服务（单例）
  final ws = ImWebSocketService();

  // 初始化推送通知
  final pushService = PushService();
  try {
    await pushService.init();
  } catch (_) {
    // 推送初始化失败（非强制）
  }

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider(api)),
        ChangeNotifierProvider(create: (_) => DashboardProvider(api)),
        ChangeNotifierProvider(create: (_) => LicenseProvider(api)),
        ChangeNotifierProvider(create: (_) => NotificationProvider(api)),
        ChangeNotifierProvider(create: (_) => ApprovalProvider(api)),
        ChangeNotifierProvider(create: (_) => DeviceProvider(api)),
        // D-30: IM Provider
        ChangeNotifierProvider(create: (_) => ConversationsProvider(ImApiService(api.dio), ws)),
        Provider<ImApiService>(create: (_) => ImApiService(api.dio)),
      ],
      child: const HwtApp(),
    ),
  );
}

class HwtApp extends StatelessWidget {
  const HwtApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'HWT License',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      home: const AuthGate(),
    );
  }
}

class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  @override
  void initState() {
    super.initState();
    context.read<AuthProvider>().checkLoginStatus();
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        if (auth.isLoading) {
          return const Scaffold(
            body: Center(child: CircularProgressIndicator()),
          );
        }
        return auth.isLoggedIn ? const HomeScreen() : const LoginScreen();
      },
    );
  }
}
