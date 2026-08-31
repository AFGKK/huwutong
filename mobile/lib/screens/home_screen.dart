import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/app_providers.dart';
import '../providers/im_providers.dart';
import '../providers/auth_provider.dart';
import 'dashboard_screen.dart';
import 'licenses_screen.dart';
import 'notifications_screen.dart';
import 'profile_screen.dart';
import 'approvals_screen.dart';
import 'conversations_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _page = 0;

  final _pages = const [
    DashboardScreen(),
    ConversationsScreen(), // D-30: 消息 tab
    LicensesScreen(),
    NotificationsScreen(),
    ProfileScreen(),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<DashboardProvider>().load();
      context.read<NotificationProvider>().load();
      context.read<ApprovalProvider>().load();
      context.read<ConversationsProvider>().load(); // D-30: 加载会话
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _page, children: _pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _page,
        onDestinationSelected: (i) {
          setState(() => _page = i);
          // 进入消息 tab 时刷新
          if (i == 1) {
            context.read<ConversationsProvider>().load();
          }
        },
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            selectedIcon: Icon(Icons.dashboard),
            label: '仪表盘',
          ),
          // D-30: 消息 tab 含未读徽章
          NavigationDestination(
            icon: Badge(
              isLabelVisible: context.watch<ConversationsProvider>().totalUnread > 0,
              label: Text('${context.watch<ConversationsProvider>().totalUnread}'),
              child: const Icon(Icons.chat_bubble_outline_rounded),
            ),
            selectedIcon: Badge(
              isLabelVisible: context.watch<ConversationsProvider>().totalUnread > 0,
              label: Text('${context.watch<ConversationsProvider>().totalUnread}'),
              child: const Icon(Icons.chat_bubble_rounded),
            ),
            label: '消息',
          ),
          const NavigationDestination(
            icon: Icon(Icons.vpn_key_outlined),
            selectedIcon: Icon(Icons.vpn_key),
            label: 'License',
          ),
          NavigationDestination(
            icon: Badge(
              isLabelVisible: context.watch<NotificationProvider>().unreadCount > 0,
              label: Text('${context.watch<NotificationProvider>().unreadCount}'),
              child: const Icon(Icons.notifications_outlined),
            ),
            selectedIcon: Badge(
              isLabelVisible: context.watch<NotificationProvider>().unreadCount > 0,
              label: Text('${context.watch<NotificationProvider>().unreadCount}'),
              child: const Icon(Icons.notifications),
            ),
            label: '通知',
          ),
          const NavigationDestination(
            icon: Icon(Icons.person_outlined),
            selectedIcon: Icon(Icons.person),
            label: '我的',
          ),
        ],
      ),
    );
  }
}
