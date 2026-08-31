import 'package:flutter_test/flutter_test.dart';
import 'package:hwt_license_mobile/models/im_models.dart';

void main() {
  group('Conversation', () {
    test('fromJson parses private conversation', () {
      final json = {
        'id': 1,
        'type': 'private',
        'created_by': 1,
        'unread_count': 3,
        'is_pinned': true,
        'last_message_at': '2026-07-17T10:00:00Z',
        'created_at': '2026-07-01T00:00:00Z',
        'updated_at': '2026-07-17T10:00:00Z',
        'participants': [
          {
            'id': 1,
            'conversation_id': 1,
            'user_id': 1,
            'role': 'member',
            'user': {'id': 1, 'name': '当前用户', 'is_online': true},
            'is_me': true,
          },
          {
            'id': 2,
            'conversation_id': 1,
            'user_id': 2,
            'role': 'member',
            'user': {'id': 2, 'name': '测试用户', 'avatar': 'https://example.com/avatar.png', 'is_online': true},
            'is_me': false,
          },
        ],
        'last_message': {
          'id': 100,
          'conversation_id': 1,
          'sender_id': 2,
          'message_type': 'text',
          'content': '你好，收到License了吗？',
          'created_at': '2026-07-17T10:00:00Z',
          'sender': {'name': '测试用户', 'avatar': 'https://example.com/avatar.png'},
        },
      };

      final conv = Conversation.fromJson(json);

      expect(conv.id, 1);
      expect(conv.type, 'private');
      expect(conv.unreadCount, 3);
      expect(conv.isPinned, isTrue);
      expect(conv.participants.length, 2);
      expect(conv.displayName, '测试用户');
      expect(conv.displayAvatar, 'https://example.com/avatar.png');
      expect(conv.otherOnline, isTrue);
      expect(conv.lastMessage, isNotNull);
      expect(conv.lastMessage!.content, '你好，收到License了吗？');
    });

    test('fromJson handles group conversation', () {
      final json = {
        'id': 2,
        'type': 'group',
        'name': '技术讨论群',
        'created_by': 1,
        'unread_count': 0,
        'created_at': '2026-07-01T00:00:00Z',
        'updated_at': '2026-07-17T10:00:00Z',
        'participants': [
          {'id': 1, 'conversation_id': 2, 'user_id': 1, 'role': 'creator', 'user': {'id': 1, 'name': '管理员'}},
          {'id': 2, 'conversation_id': 2, 'user_id': 2, 'role': 'member', 'user': {'id': 2, 'name': '成员A'}},
        ],
      };

      final conv = Conversation.fromJson(json);
      expect(conv.type, 'group');
      expect(conv.name, '技术讨论群');
      expect(conv.displayName, '技术讨论群');
      expect(conv.otherParticipant, isNull);
    });

    test('fromJson handles minimal data', () {
      final conv = Conversation.fromJson({
        'id': 1,
        'type': 'private',
        'created_by': 1,
        'created_at': '2026-07-01T00:00:00Z',
        'updated_at': '2026-07-01T00:00:00Z',
      });

      expect(conv.displayName, '未知用户');
      expect(conv.unreadCount, 0);
      expect(conv.participants, isEmpty);
    });
  });

  group('ConversationMessage', () {
    test('fromJson parses text message', () {
      final json = {
        'id': 100,
        'conversation_id': 1,
        'sender_id': 1,
        'message_type': 'text',
        'content': 'Hello!',
        'is_edited': false,
        'created_at': '2026-07-17T10:00:00Z',
        'sender': {'name': 'User A'},
      };

      final msg = ConversationMessage.fromJson(json);

      expect(msg.id, 100);
      expect(msg.content, 'Hello!');
      expect(msg.messageType, 'text');
      expect(msg.senderName, 'User A');
      expect(msg.isSystem, isFalse);
    });

    test('isSystem returns true for system messages', () {
      final msg = ConversationMessage.fromJson({
        'id': 1,
        'conversation_id': 1,
        'sender_id': 0,
        'message_type': 'system',
        'content': '用户加入了群聊',
        'created_at': '2026-07-17T10:00:00Z',
      });

      expect(msg.isSystem, isTrue);
    });

    test('isMine compares with currentUserId', () {
      final msg = ConversationMessage.fromJson({
        'id': 1,
        'conversation_id': 1,
        'sender_id': 5,
        'message_type': 'text',
        'content': 'hi',
        'created_at': '2026-07-17T10:00:00Z',
      });

      expect(msg.isMine(5), isTrue);
      expect(msg.isMine(3), isFalse);
    });
  });

  group('UserProfile', () {
    test('fromJson parses correctly', () {
      final profile = UserProfile.fromJson({
        'id': 1,
        'name': '张三',
        'avatar': 'https://example.com/avatar.png',
        'is_online': true,
      });

      expect(profile.id, 1);
      expect(profile.name, '张三');
      expect(profile.isOnline, isTrue);
    });
  });

  group('MessageReaction', () {
    test('fromJson parses correctly', () {
      final reaction = MessageReaction.fromJson({
        'id': 1,
        'message_id': 100,
        'user_id': 2,
        'reaction': '👍',
        'user_name': 'User B',
      });

      expect(reaction.reaction, '👍');
      expect(reaction.userName, 'User B');
    });
  });

  group('UserOnlineEvent', () {
    test('fromJson parses correctly', () {
      final event = UserOnlineEvent.fromJson({
        'user_id': 1,
        'is_online': false,
        'timestamp': '2026-07-17T10:00:00Z',
      });

      expect(event.userId, 1);
      expect(event.isOnline, isFalse);
    });
  });

  group('ImWsEvent', () {
    test('isNewMessage detects message.created event', () {
      final event = ImWsEvent(
        channel: 'private-user.1',
        event: 'message.created',
        data: {'conversation_id': 5},
      );

      expect(event.isNewMessage, isTrue);
      expect(event.conversationId, 5);
    });

    test('isTyping detects typing event', () {
      final event = ImWsEvent(
        channel: 'private-user.1',
        event: 'typing',
        data: {'conversation_id': 5},
      );

      expect(event.isTyping, isTrue);
    });

    test('ConversationMessage isMine', () {
      final msg = ConversationMessage(
        id: 1,
        conversationId: 1,
        senderId: 10,
        content: 'hello',
      );
      expect(msg.isMine(10), isTrue);
      expect(msg.isMine(5), isFalse);
    });
  });
}
