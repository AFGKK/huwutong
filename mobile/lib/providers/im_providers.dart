import 'dart:async';
import 'package:flutter/material.dart';
import '../models/im_models.dart';
import '../services/im_api_service.dart';
import '../services/im_websocket_service.dart';

/// D-30: 会话列表状态管理
class ConversationsProvider extends ChangeNotifier {
  final ImApiService _api;
  final ImWebSocketService _ws;

  List<Conversation> _conversations = [];
  List<Conversation> _messageRequests = [];
  bool _isLoading = false;
  String? _error;
  StreamSubscription? _wsSubscription;

  ConversationsProvider(this._api, this._ws) {
    _setupWsListener();
  }

  List<Conversation> get conversations => _conversations;
  List<Conversation> get messageRequests => _messageRequests;
  bool get isLoading => _isLoading;
  String? get error => _error;

  /// 未读消息总数（所有会话未读数之和）
  int get totalUnread =>
      _conversations.fold(0, (sum, c) => sum + c.unreadCount);

  /// 待处理消息请求数
  int get pendingRequestCount =>
      _messageRequests.where((c) => c.type == 'private').length;

  /// 加载会话列表 + 消息请求
  Future<void> load() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final results = await Future.wait([
        _api.getConversations(),
        _api.getMessageRequests(),
      ]);
      _conversations = results[0];
      _messageRequests = results[1];
    } catch (e) {
      _error = '加载会话列表失败';
      debugPrint('[IM] 加载会话列表失败: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// 标记会话已读
  Future<void> markRead(int conversationId) async {
    try {
      await _api.markConversationRead(conversationId);
      final idx = _conversations.indexWhere((c) => c.id == conversationId);
      if (idx != -1) {
        final old = _conversations[idx];
        _conversations[idx] = Conversation(
          id: old.id,
          type: old.type,
          name: old.name,
          createdBy: old.createdBy,
          lastMessage: old.lastMessage,
          participants: old.participants,
          unreadCount: 0,
          isPinned: old.isPinned,
          isMuted: old.isMuted,
          draft: old.draft,
          lastMessageAt: old.lastMessageAt,
          createdAt: old.createdAt,
          updatedAt: old.updatedAt,
        );
        notifyListeners();
      }
    } catch (_) {}
  }

  /// 创建新会话并返回
  Future<Conversation?> createConversation(List<int> participantIds) async {
    try {
      final conv = await _api.createConversation(participantIds);
      await load(); // 刷新列表
      return conv;
    } catch (e) {
      debugPrint('[IM] 创建会话失败: $e');
      return null;
    }
  }

  /// 删除/隐藏会话
  Future<void> deleteConversation(int conversationId) async {
    try {
      await _api.deleteConversation(conversationId);
      _conversations.removeWhere((c) => c.id == conversationId);
      notifyListeners();
    } catch (_) {}
  }

  /// 接受消息请求
  Future<void> acceptRequest(int conversationId) async {
    try {
      await _api.acceptMessageRequest(conversationId);
      await load();
    } catch (_) {}
  }

  /// 拒绝消息请求
  Future<void> rejectRequest(int conversationId, {bool block = false}) async {
    try {
      await _api.rejectMessageRequest(conversationId, block: block);
      _messageRequests.removeWhere((c) => c.id == conversationId);
      notifyListeners();
    } catch (_) {}
  }

  void _setupWsListener() {
    _wsSubscription = _ws.eventStream.listen(_handleWsEvent);
  }

  void _handleWsEvent(ImWsEvent event) {
    if (event.isNewMessage && event.conversationId != null) {
      // 收到新消息，刷新会话列表
      load();
    }
  }

  @override
  void dispose() {
    _wsSubscription?.cancel();
    super.dispose();
  }
}

/// D-30: 聊天消息状态管理
class ChatMessagesProvider extends ChangeNotifier {
  final ImApiService _api;

  int _conversationId = 0;
  List<ConversationMessage> _messages = [];
  bool _isLoading = false;
  bool _hasMore = true;
  String? _error;
  int? _currentUserId;

  ChatMessagesProvider(this._api);

  int get conversationId => _conversationId;
  List<ConversationMessage> get messages => _messages;
  bool get isLoading => _isLoading;
  bool get hasMore => _hasMore;
  String? get error => _error;
  int? get currentUserId => _currentUserId;

  /// 进入会话
  void enter(int conversationId, {int? currentUserId}) {
    _conversationId = conversationId;
    _messages = [];
    _hasMore = true;
    _currentUserId = currentUserId;
    load();
  }

  /// 离开会话（释放资源）
  void leave() {
    _conversationId = 0;
    _messages = [];
    _hasMore = true;
    _error = null;
    notifyListeners();
  }

  /// 加载消息
  Future<void> load({bool refresh = false}) async {
    if (_conversationId == 0) return;
    if (_isLoading) return;
    if (!refresh && !_hasMore) return;

    _isLoading = true;
    if (refresh) {
      _messages = [];
      _hasMore = true;
    }
    _error = null;
    notifyListeners();

    try {
      final beforeId = !refresh && _messages.isNotEmpty ? _messages.first.id : null;
      final newMessages = await _api.getMessages(_conversationId, beforeId: beforeId);

      if (newMessages.length < 30) _hasMore = false;

      _messages = [...newMessages.reversed, ..._messages];

      // 标记已读
      markAsRead();
    } catch (e) {
      _error = '加载消息失败';
      debugPrint('[IM] 加载消息失败: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// 发送消息
  Future<bool> sendMessage(String content, {String messageType = 'text', int? replyToId}) async {
    if (content.trim().isEmpty) return false;
    try {
      final msg = await _api.sendMessage(
        _conversationId,
        content,
        messageType: messageType,
        replyToId: replyToId,
      );
      _messages.add(msg);
      notifyListeners();
      return true;
    } catch (e) {
      debugPrint('[IM] 发送消息失败: $e');
      return false;
    }
  }

  /// 添加到本地消息（用于 WebSocket 收到新消息时）
  void addLocalMessage(ConversationMessage message) {
    _messages.add(message);
    notifyListeners();
  }

  /// 标记已读
  Future<void> markAsRead() async {
    try {
      await _api.markConversationRead(_conversationId);
    } catch (_) {}
  }

  /// 发送输入状态
  void sendTyping() {
    _api.sendTyping(_conversationId);
  }
}
