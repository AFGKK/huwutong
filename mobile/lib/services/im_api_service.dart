import 'package:dio/dio.dart';
import '../models/im_models.dart';

/// D-30: IM API 服务层
///
/// 封装 UserChatController 的所有 IM 相关 API 调用。
/// 所有方法都需要用户在已登录状态调用。
class ImApiService {
  final Dio _dio;

  ImApiService(this._dio);

  // ─── 会话 ───

  /// 获取我的会话列表
  Future<List<Conversation>> getConversations() async {
    final res = await _dio.get('/conversations');
    final data = res.data['data'] as List? ?? [];
    return data.map((e) => Conversation.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// 获取未读会话列表
  Future<List<Conversation>> getUnreadConversations() async {
    final res = await _dio.get('/conversations/unread');
    final data = res.data['data'] as List? ?? [];
    return data.map((e) => Conversation.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// 创建或获取单聊会话
  Future<Conversation> createConversation(List<int> participantIds) async {
    final res = await _dio.post('/conversations', data: {
      'participant_ids': participantIds,
    });
    return Conversation.fromJson(res.data['data'] as Map<String, dynamic>);
  }

  /// 标记会话已读
  Future<void> markConversationRead(int conversationId) async {
    await _dio.post('/conversations/$conversationId/read');
  }

  /// 删除/隐藏会话
  Future<void> deleteConversation(int conversationId) async {
    await _dio.delete('/conversations/$conversationId');
  }

  /// 置顶/取消置顶
  Future<void> togglePin(int conversationId) async {
    await _dio.post('/conversations/$conversationId/pin');
  }

  /// 静音/取消静音
  Future<void> toggleMute(int conversationId) async {
    await _dio.post('/conversations/$conversationId/mute');
  }

  // ─── 消息 ───

  /// 获取消息列表（分页）
  Future<List<ConversationMessage>> getMessages(int conversationId, {int? beforeId}) async {
    final params = <String, dynamic>{'per_page': 30};
    if (beforeId != null) params['before_id'] = beforeId;
    final res = await _dio.get('/conversations/$conversationId/messages', queryParameters: params);
    final data = res.data['data'] as List? ?? [];
    return data.map((e) => ConversationMessage.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// 发送消息
  Future<ConversationMessage> sendMessage(int conversationId, String content,
      {String messageType = 'text', int? replyToId}) async {
    final data = <String, dynamic>{
      'content': content,
      'message_type': messageType,
    };
    if (replyToId != null) data['reply_to_id'] = replyToId;

    final res = await _dio.post('/conversations/$conversationId/messages', data: data);
    return ConversationMessage.fromJson(res.data['data'] as Map<String, dynamic>);
  }

  /// 发送输入状态
  Future<void> sendTyping(int conversationId) async {
    try {
      await _dio.post('/conversations/$conversationId/typing');
    } catch (_) {
      // typing 通知失败不阻塞用户操作
    }
  }

  // ─── 消息请求 ───

  /// 获取消息请求列表（非好友发来的消息）
  Future<List<Conversation>> getMessageRequests() async {
    final res = await _dio.get('/message-requests');
    final data = res.data['data'] as List? ?? [];
    return data.map((e) => Conversation.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// 接受消息请求
  Future<void> acceptMessageRequest(int conversationId) async {
    await _dio.post('/message-requests/$conversationId/accept');
  }

  /// 拒绝消息请求
  Future<void> rejectMessageRequest(int conversationId,
      {bool block = false}) async {
    await _dio.post('/message-requests/$conversationId/reject',
        data: {'block': block});
  }

  // ─── 好友信息 ───

  /// 搜索用户（发起私信用）
  Future<List<UserProfile>> searchUsers(String keyword) async {
    final res = await _dio.get('/users/search', queryParameters: {'q': keyword});
    final data = res.data['data'] as List? ?? [];
    return data.map((e) => UserProfile.fromJson(e as Map<String, dynamic>)).toList();
  }
}
