import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../config/theme.dart';
import '../models/im_models.dart';
import '../providers/im_providers.dart';
import 'chat_screen.dart';

/// D-30: 会话列表页（私信列表）
class ConversationsScreen extends StatefulWidget {
  const ConversationsScreen({super.key});

  @override
  State<ConversationsScreen> createState() => _ConversationsScreenState();
}

class _ConversationsScreenState extends State<ConversationsScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ConversationsProvider>().load();
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('消息'),
        actions: [
          IconButton(
            icon: const Icon(Icons.edit_outlined),
            onPressed: () => _showNewConversation(context),
            tooltip: '新对话',
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          tabs: [
            Tab(text: '对话'),
            Consumer<ConversationsProvider>(
              builder: (context, provider, _) => Tab(
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text('消息请求'),
                    if (provider.pendingRequestCount > 0) ...[
                      const SizedBox(width: 4),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppTheme.danger,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          '${provider.pendingRequestCount}',
                          style: const TextStyle(fontSize: 10, color: Colors.white),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _ConversationTab(),
          _MessageRequestTab(),
        ],
      ),
    );
  }

  void _showNewConversation(BuildContext context) {
    showSearch(context: context, delegate: _UserSearchDelegate());
  }
}

/// 会话列表 Tab
class _ConversationTab extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Consumer<ConversationsProvider>(
      builder: (context, provider, _) {
        if (provider.isLoading && provider.conversations.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        if (provider.conversations.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.chat_bubble_outline, size: 64, color: AppTheme.textSecondary.withOpacity(0.4)),
                const SizedBox(height: 16),
                const Text('暂无对话', style: TextStyle(color: AppTheme.textSecondary)),
                const SizedBox(height: 8),
                const Text('点击右上角 ✏️ 发起新对话', style: TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: provider.load,
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(vertical: 4),
            itemCount: provider.conversations.length,
            separatorBuilder: (_, __) => const Divider(height: 1, indent: 72),
            itemBuilder: (context, i) {
              final conv = provider.conversations[i];
              return _ConversationTile(
                conversation: conv,
                onTap: () => _openConversation(context, conv),
                onLongPress: () => _showActions(context, conv, provider),
              );
            },
          ),
        );
      },
    );
  }

  void _openConversation(BuildContext context, Conversation conv) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ChatScreen(conversation: conv),
      ),
    );
  }

  void _showActions(BuildContext context, Conversation conv, ConversationsProvider provider) {
    showModalBottomSheet(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: Icon(conv.isPinned ? Icons.push_pin : Icons.push_pin_outlined),
              title: Text(conv.isPinned ? '取消置顶' : '置顶对话'),
              onTap: () {
                Navigator.pop(ctx);
                provider.togglePin(conv.id);
              },
            ),
            ListTile(
              leading: Icon(conv.isMuted ? Icons.notifications_off : Icons.notifications_outlined),
              title: Text(conv.isMuted ? '取消静音' : '静音通知'),
              onTap: () {
                Navigator.pop(ctx);
                provider.toggleMute(conv.id);
              },
            ),
            ListTile(
              leading: const Icon(Icons.delete_outline, color: AppTheme.danger),
              title: const Text('删除对话', style: TextStyle(color: AppTheme.danger)),
              onTap: () {
                Navigator.pop(ctx);
                _confirmDelete(context, conv, provider);
              },
            ),
          ],
        ),
      ),
    );
  }

  void _confirmDelete(BuildContext context, Conversation conv, ConversationsProvider provider) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('删除对话'),
        content: Text('确定要删除与"${conv.displayName}"的对话吗？'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('取消')),
          FilledButton(
            onPressed: () {
              Navigator.pop(ctx);
              provider.deleteConversation(conv.id);
            },
            style: FilledButton.styleFrom(backgroundColor: AppTheme.danger),
            child: const Text('删除'),
          ),
        ],
      ),
    );
  }
}

/// 消息请求 Tab
class _MessageRequestTab extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Consumer<ConversationsProvider>(
      builder: (context, provider, _) {
        if (provider.isLoading && provider.messageRequests.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        if (provider.messageRequests.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.person_add_outlined, size: 64, color: AppTheme.textSecondary.withOpacity(0.4)),
                const SizedBox(height: 16),
                const Text('暂无消息请求', style: TextStyle(color: AppTheme.textSecondary)),
              ],
            ),
          );
        }

        return ListView.separated(
          padding: const EdgeInsets.symmetric(vertical: 4),
          itemCount: provider.messageRequests.length,
          separatorBuilder: (_, __) => const Divider(height: 1, indent: 72),
          itemBuilder: (context, i) {
            final req = provider.messageRequests[i];
            return ListTile(
              leading: CircleAvatar(
                backgroundColor: AppTheme.warning.withOpacity(0.15),
                child: const Icon(Icons.person_outline, color: AppTheme.warning),
              ),
              title: Text(req.displayName),
              subtitle: Text('想给您发送消息', style: TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
              trailing: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    icon: const Icon(Icons.check_circle_outline, color: AppTheme.success),
                    onPressed: () => provider.acceptRequest(req.id),
                  ),
                  IconButton(
                    icon: const Icon(Icons.cancel_outlined, color: AppTheme.textSecondary),
                    onPressed: () => provider.rejectRequest(req.id),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}

/// 会话列表项
class _ConversationTile extends StatelessWidget {
  final Conversation conversation;
  final VoidCallback onTap;
  final VoidCallback onLongPress;

  const _ConversationTile({
    required this.conversation,
    required this.onTap,
    required this.onLongPress,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      onLongPress: onLongPress,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(
          children: [
            // Avatar
            Stack(
              children: [
                CircleAvatar(
                  radius: 24,
                  backgroundColor: AppTheme.primary.withOpacity(0.1),
                  backgroundImage: conversation.displayAvatar != null
                      ? NetworkImage(conversation.displayAvatar!)
                      : null,
                  child: conversation.displayAvatar == null
                      ? Text(
                          conversation.displayName.isNotEmpty
                              ? conversation.displayName[0].toUpperCase()
                              : '?',
                          style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary),
                        )
                      : null,
                ),
                // Online indicator
                if (conversation.type == 'private' && conversation.otherOnline)
                  Positioned(
                    right: 0,
                    bottom: 0,
                    child: Container(
                      width: 12,
                      height: 12,
                      decoration: BoxDecoration(
                        color: AppTheme.success,
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.white, width: 2),
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(width: 12),
            // Content
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          conversation.displayName,
                          style: TextStyle(
                            fontWeight: conversation.unreadCount > 0 ? FontWeight.w600 : FontWeight.normal,
                            fontSize: 15,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (conversation.lastMessageAt != null)
                        Text(
                          _formatTime(conversation.lastMessageAt!),
                          style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary),
                        ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          conversation.lastMessage?.content ?? conversation.draft ?? '',
                          style: TextStyle(
                            fontSize: 13,
                            color: conversation.unreadCount > 0 ? AppTheme.textPrimary : AppTheme.textSecondary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (conversation.unreadCount > 0)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                          decoration: BoxDecoration(
                            color: AppTheme.danger,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Text(
                            '${conversation.unreadCount}',
                            style: const TextStyle(fontSize: 10, color: Colors.white),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _formatTime(DateTime dt) {
    final now = DateTime.now();
    final diff = now.difference(dt);
    if (diff.inMinutes < 1) return '刚刚';
    if (diff.inHours < 1) return '${diff.inMinutes}分钟前';
    if (diff.inDays < 1) return '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    if (diff.inDays < 7) return '${diff.inDays}天前';
    return '${dt.month}/${dt.day}';
  }
}

/// 用户搜索（发起新对话）
class _UserSearchDelegate extends SearchDelegate<void> {
  @override
  String get searchFieldLabel => '搜索用户...';

  @override
  List<Widget>? buildActions(BuildContext context) => [
        IconButton(
          icon: const Icon(Icons.clear),
          onPressed: () => query = '',
        ),
      ];

  @override
  Widget? buildLeading(BuildContext context) => IconButton(
        icon: const Icon(Icons.arrow_back),
        onPressed: () => close(context, null),
      );

  @override
  Widget buildResults(BuildContext context) => const SizedBox();

  @override
  Widget buildSuggestions(BuildContext context) {
    if (query.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.search, size: 48, color: AppTheme.textSecondary.withOpacity(0.3)),
            const SizedBox(height: 8),
            const Text('输入用户邮箱或昵称搜索', style: TextStyle(color: AppTheme.textSecondary)),
          ],
        ),
      );
    }

    // 实际会调用 _api.searchUsers()
    // 这里展示搜索 UI 骨架
    return ListTile(
      leading: const CircleAvatar(child: Icon(Icons.person)),
      title: Text('搜索 "$query"'),
      subtitle: const Text('点击发起对话'),
      onTap: () {
        close(context, null);
        // 实际会调用 provider.createConversation([userId])
      },
    );
  }
}
