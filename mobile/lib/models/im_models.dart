/// D-30: IM 数据模型
///
/// Conversation — 用户会话（单聊/群聊）
/// ConversationMessage — 消息
/// ConversationParticipant — 会话参与者

class Conversation {
  final int id;
  final String type; // 'private' | 'group'
  final String? name;
  final int createdBy;
  final ConversationMessage? lastMessage;
  final List<ConversationParticipant> participants;
  final int unreadCount;
  final bool isPinned;
  final bool isMuted;
  final String? draft;
  final DateTime? lastMessageAt;
  final DateTime createdAt;
  final DateTime updatedAt;

  Conversation({
    required this.id,
    required this.type,
    this.name,
    required this.createdBy,
    this.lastMessage,
    this.participants = const [],
    this.unreadCount = 0,
    this.isPinned = false,
    this.isMuted = false,
    this.draft,
    this.lastMessageAt,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Conversation.fromJson(Map<String, dynamic> json) => Conversation(
        id: json['id'] as int,
        type: json['type'] as String? ?? 'private',
        name: json['name'] as String?,
        createdBy: json['created_by'] as int? ?? 0,
        lastMessage: json['last_message'] != null
            ? ConversationMessage.fromJson(json['last_message'] as Map<String, dynamic>)
            : null,
        participants: (json['participants'] as List?)
                ?.map((e) => ConversationParticipant.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [],
        unreadCount: json['unread_count'] as int? ?? 0,
        isPinned: json['is_pinned'] as bool? ?? false,
        isMuted: json['is_muted'] as bool? ?? false,
        draft: json['draft'] as String?,
        lastMessageAt: json['last_message_at'] != null
            ? DateTime.tryParse(json['last_message_at'])
            : null,
        createdAt: DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
        updatedAt: DateTime.tryParse(json['updated_at'] ?? '') ?? DateTime.now(),
      );

  /// 单聊中对方的参与者（排除自己）
  ConversationParticipant? get otherParticipant => participants.length == 2
      ? participants.where((p) => !p.isMe).firstOrNull
      : null;

  /// 显示名称（单聊用对方姓名，群聊用群名）
  String get displayName =>
      type == 'private'
          ? (otherParticipant?.user?.name ?? name ?? '未知用户')
          : (name ?? '群聊(${participants.length}人)');

  /// 显示头像URL（单聊用对方头像）
  String? get displayAvatar =>
      type == 'private' ? otherParticipant?.user?.avatar : null;

  /// 在线状态（单聊对方在线状态）
  bool get otherOnline => otherParticipant?.user?.isOnline ?? false;
}

class ConversationMessage {
  final int id;
  final int conversationId;
  final int senderId;
  final String messageType; // 'text' | 'image' | 'file' | 'system'
  final String content;
  final Map<String, dynamic>? attachments;
  final Map<String, dynamic>? metadata;
  final int? replyToId;
  final bool isEdited;
  final DateTime? editedAt;
  final List<MessageReaction>? reactions;
  final bool isPinned;
  final DateTime? createdAt;
  final String? senderName;
  final String? senderAvatar;

  ConversationMessage({
    required this.id,
    required this.conversationId,
    required this.senderId,
    this.messageType = 'text',
    this.content = '',
    this.attachments,
    this.metadata,
    this.replyToId,
    this.isEdited = false,
    this.editedAt,
    this.reactions,
    this.isPinned = false,
    this.createdAt,
    this.senderName,
    this.senderAvatar,
  });

  factory ConversationMessage.fromJson(Map<String, dynamic> json) =>
      ConversationMessage(
        id: json['id'] as int,
        conversationId: json['conversation_id'] as int? ?? 0,
        senderId: json['sender_id'] as int? ?? 0,
        messageType: json['message_type'] as String? ?? 'text',
        content: json['content'] as String? ?? '',
        attachments: json['attachments'] as Map<String, dynamic>?,
        metadata: json['metadata'] as Map<String, dynamic>?,
        replyToId: json['reply_to_id'] as int?,
        isEdited: json['is_edited'] as bool? ?? false,
        editedAt: json['edited_at'] != null
            ? DateTime.tryParse(json['edited_at'])
            : null,
        reactions: (json['reactions'] as List?)
            ?.map((e) => MessageReaction.fromJson(e as Map<String, dynamic>))
            .toList(),
        isPinned: json['is_pinned'] as bool? ?? false,
        createdAt: json['created_at'] != null
            ? DateTime.tryParse(json['created_at'])
            : null,
        senderName: json['sender'] is Map
            ? (json['sender']['name'] as String?)
            : null,
        senderAvatar: json['sender'] is Map
            ? (json['sender']['avatar'] as String?)
            : null,
      );

  /// 是否是系统消息
  bool get isSystem => messageType == 'system';

  /// 是否是自己发送的消息
  bool isMine(int currentUserId) => senderId == currentUserId;
}

class ConversationParticipant {
  final int id;
  final int conversationId;
  final int userId;
  final String role; // 'member' | 'admin' | 'creator'
  final String? requestStatus;
  final DateTime? lastReadAt;
  final UserProfile? user;
  final bool? isMe;

  ConversationParticipant({
    required this.id,
    required this.conversationId,
    required this.userId,
    this.role = 'member',
    this.requestStatus,
    this.lastReadAt,
    this.user,
    this.isMe,
  });

  factory ConversationParticipant.fromJson(Map<String, dynamic> json) =>
      ConversationParticipant(
        id: json['id'] as int,
        conversationId: json['conversation_id'] as int? ?? 0,
        userId: json['user_id'] as int? ?? 0,
        role: json['role'] as String? ?? 'member',
        requestStatus: json['request_status'] as String?,
        lastReadAt: json['last_read_at'] != null
            ? DateTime.tryParse(json['last_read_at'])
            : null,
        user: json['user'] != null
            ? UserProfile.fromJson(json['user'] as Map<String, dynamic>)
            : null,
        isMe: json['is_me'] as bool?,
      );
}

class UserProfile {
  final int id;
  final String? name;
  final String? avatar;
  final bool isOnline;

  UserProfile({
    required this.id,
    this.name,
    this.avatar,
    this.isOnline = false,
  });

  factory UserProfile.fromJson(Map<String, dynamic> json) => UserProfile(
        id: json['id'] as int,
        name: json['name'] as String?,
        avatar: json['avatar'] as String?,
        isOnline: json['is_online'] as bool? ?? false,
      );
}

class MessageReaction {
  final int id;
  final int messageId;
  final int userId;
  final String reaction;
  final String? userName;

  MessageReaction({
    required this.id,
    required this.messageId,
    required this.userId,
    required this.reaction,
    this.userName,
  });

  factory MessageReaction.fromJson(Map<String, dynamic> json) =>
      MessageReaction(
        id: json['id'] as int,
        messageId: json['message_id'] as int? ?? 0,
        userId: json['user_id'] as int? ?? 0,
        reaction: json['reaction'] as String? ?? '',
        userName: json['user_name'] as String?,
      );
}

/// 在线状态变更事件
class UserOnlineEvent {
  final int userId;
  final bool isOnline;
  final DateTime timestamp;

  UserOnlineEvent({
    required this.userId,
    required this.isOnline,
    required this.timestamp,
  });

  factory UserOnlineEvent.fromJson(Map<String, dynamic> json) =>
      UserOnlineEvent(
        userId: json['user_id'] as int,
        isOnline: json['is_online'] as bool? ?? false,
        timestamp:
            DateTime.tryParse(json['timestamp'] ?? '') ?? DateTime.now(),
      );
}
