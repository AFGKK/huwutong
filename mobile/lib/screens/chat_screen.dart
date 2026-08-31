import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../config/theme.dart';
import '../models/im_models.dart';
import '../providers/im_providers.dart';
import '../providers/auth_provider.dart';

/// D-30: 聊天页面
class ChatScreen extends StatefulWidget {
  final Conversation conversation;

  const ChatScreen({super.key, required this.conversation});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _textCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  late ChatMessagesProvider _messagesProvider;
  Timer? _typingTimer;
  bool _isSending = false;

  @override
  void initState() {
    super.initState();
    final api = context.read<ImApiService>();
    _messagesProvider = ChatMessagesProvider(api);
    final auth = context.read<AuthProvider>();
    _messagesProvider.enter(
      widget.conversation.id,
      currentUserId: null, // 可以从 auth 中获取
    );

    // 滚动到最新消息
    WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToBottom());
  }

  @override
  void dispose() {
    _textCtrl.dispose();
    _scrollCtrl.dispose();
    _typingTimer?.cancel();
    _messagesProvider.leave();
    super.dispose();
  }

  void _scrollToBottom() {
    if (_scrollCtrl.hasClients) {
      _scrollCtrl.animateTo(
        _scrollCtrl.position.maxScrollExtent,
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeOut,
      );
    }
  }

  Future<void> _sendMessage() async {
    final content = _textCtrl.text.trim();
    if (content.isEmpty || _isSending) return;

    setState(() => _isSending = true);
    _textCtrl.clear();

    final ok = await _messagesProvider.sendMessage(content);
    setState(() => _isSending = false);

    if (ok) {
      // 标记会话已读
      context.read<ConversationsProvider>().markRead(widget.conversation.id);
      Future.microtask(_scrollToBottom);
    }
  }

  void _onTyping() {
    _typingTimer?.cancel();
    _messagesProvider.sendTyping();
    _typingTimer = Timer(const Duration(seconds: 2), () {});
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            // Avatar
            CircleAvatar(
              radius: 16,
              backgroundColor: AppTheme.primary.withOpacity(0.1),
              backgroundImage: widget.conversation.displayAvatar != null
                  ? NetworkImage(widget.conversation.displayAvatar!)
                  : null,
              child: widget.conversation.displayAvatar == null
                  ? Text(
                      widget.conversation.displayName.isNotEmpty
                          ? widget.conversation.displayName[0].toUpperCase()
                          : '?',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.primary),
                    )
                  : null,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.conversation.displayName,
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w500),
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (widget.conversation.type == 'private')
                    Text(
                      widget.conversation.otherOnline ? '在线' : '离线',
                      style: TextStyle(
                        fontSize: 11,
                        color: widget.conversation.otherOnline ? AppTheme.success : AppTheme.textSecondary,
                      ),
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
      body: Column(
        children: [
          // 消息列表
          Expanded(
            child: _buildMessageList(),
          ),

          // 输入区域
          _buildInputBar(),
        ],
      ),
    );
  }

  Widget _buildMessageList() {
    return ChangeNotifierProvider.value(
      value: _messagesProvider,
      child: Consumer<ChatMessagesProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading && provider.messages.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }

          if (provider.messages.isEmpty) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.chat_outlined, size: 48, color: AppTheme.textSecondary.withOpacity(0.3)),
                  const SizedBox(height: 12),
                  const Text('暂无消息', style: TextStyle(color: AppTheme.textSecondary)),
                  const Text('发送第一条消息开始对话', style: TextStyle(fontSize: 12, color: AppTheme.textSecondary)),
                ],
              ),
            );
          }

          return GestureDetector(
            onTap: () => FocusScope.of(context).unfocus(),
            child: ListView.builder(
              controller: _scrollCtrl,
              padding: const EdgeInsets.all(12),
              itemCount: provider.messages.length,
              reverse: true,
              itemBuilder: (context, i) {
                final msg = provider.messages.reversed.toList()[i];
                return _MessageBubble(
                  message: msg,
                  isMine: false, // 实际需要 currentUserId
                );
              },
            ),
          );
        },
      ),
    );
  }

  Widget _buildInputBar() {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).scaffoldBackgroundColor,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 4,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          child: Row(
            children: [
              // 附件按钮
              IconButton(
                icon: const Icon(Icons.add_circle_outline, color: AppTheme.textSecondary),
                onPressed: () => _showAttachmentOptions(),
              ),
              // 输入框
              Expanded(
                child: TextField(
                  controller: _textCtrl,
                  minLines: 1,
                  maxLines: 4,
                  textInputAction: TextInputAction.send,
                  decoration: InputDecoration(
                    hintText: '输入消息...',
                    filled: true,
                    fillColor: Colors.grey[100],
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(20),
                      borderSide: BorderSide.none,
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  ),
                  onChanged: (_) => _onTyping(),
                  onSubmitted: (_) => _sendMessage(),
                ),
              ),
              const SizedBox(width: 4),
              // 发送按钮
              IconButton(
                icon: _isSending
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.send_rounded, color: AppTheme.primary),
                onPressed: _sendMessage,
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showAttachmentOptions() {
    showModalBottomSheet(
      context: context,
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
              _AttachmentButton(Icons.image_outlined, '图片', () {
                Navigator.pop(ctx);
                // 实现图片选择
              }),
              _AttachmentButton(Icons.description_outlined, '文件', () {
                Navigator.pop(ctx);
                // 实现文件选择
              }),
            ],
          ),
        ),
      ),
    );
  }
}

/// 消息气泡
class _MessageBubble extends StatelessWidget {
  final ConversationMessage message;
  final bool isMine;

  const _MessageBubble({required this.message, required this.isMine});

  @override
  Widget build(BuildContext context) {
    if (message.isSystem) {
      return Center(
        child: Container(
          margin: const EdgeInsets.symmetric(vertical: 8),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          decoration: BoxDecoration(
            color: Colors.grey[200],
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(
            message.content,
            style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
          ),
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: isMine ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          // 对方头像
          if (!isMine && message.senderAvatar != null)
            Padding(
              padding: const EdgeInsets.only(right: 6),
              child: CircleAvatar(
                radius: 12,
                backgroundImage: NetworkImage(message.senderAvatar!),
              ),
            ),
          // 气泡
          Flexible(
            child: Container(
              constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.72),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: isMine ? AppTheme.primary : Colors.grey[100],
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(16),
                  topRight: const Radius.circular(16),
                  bottomLeft: Radius.circular(isMine ? 16 : 4),
                  bottomRight: Radius.circular(isMine ? 4 : 16),
                ),
              ),
              child: Column(
                crossAxisAlignment: isMine ? CrossAxisAlignment.end : CrossAxisAlignment.start,
                children: [
                  // 文本
                  Text(
                    message.content,
                    style: TextStyle(
                      color: isMine ? Colors.white : AppTheme.textPrimary,
                      fontSize: 15,
                    ),
                  ),
                  const SizedBox(height: 2),
                  // 时间
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (message.createdAt != null)
                        Text(
                          _formatTime(message.createdAt!),
                          style: TextStyle(
                            fontSize: 10,
                            color: isMine ? Colors.white70 : AppTheme.textSecondary,
                          ),
                        ),
                      if (isMine) ...[
                        const SizedBox(width: 4),
                        Icon(
                          Icons.check,
                          size: 14,
                          color: Colors.white70,
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
          ),
          // 自己头像
          if (isMine && message.senderAvatar != null)
            Padding(
              padding: const EdgeInsets.only(left: 6),
              child: CircleAvatar(
                radius: 12,
                backgroundImage: NetworkImage(message.senderAvatar!),
              ),
            ),
        ],
      ),
    );
  }

  String _formatTime(DateTime dt) {
    final now = DateTime.now();
    final diff = now.difference(dt);
    if (diff.inMinutes < 1) return '刚刚';
    if (diff.inHours < 1) return '${diff.inMinutes}分钟前';
    return '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
  }
}

/// 附件按钮
class _AttachmentButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _AttachmentButton(this.icon, this.label, this.onTap);

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppTheme.primary.withOpacity(0.08),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(icon, color: AppTheme.primary, size: 28),
          ),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(fontSize: 12)),
        ],
      ),
    );
  }
}
