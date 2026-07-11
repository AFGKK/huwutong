<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * 新私信 → 消息中心 + Push（受免打扰与偏好约束）
 */
class UserChatNotificationService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected NotificationPreferenceService $preferenceService,
    ) {}

    public function notifyNewMessage(ConversationMessage $message, array $participantIds): void
    {
        $message->loadMissing('sender:id,name');
        $senderId = (int) $message->sender_id;
        $senderName = $message->sender?->name ?? '用户';
        $preview = $this->buildPreview($message);

        $recipientIds = collect($participantIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $senderId)
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $mutedUserIds = ConversationParticipant::where('conversation_id', $message->conversation_id)
            ->whereIn('user_id', $recipientIds)
            ->where('is_muted', true)
            ->whereNull('deleted_at')
            ->pluck('user_id');

        foreach ($recipientIds as $recipientId) {
            if ($mutedUserIds->contains($recipientId)) {
                continue;
            }

            $user = User::find($recipientId);
            if (! $user) {
                continue;
            }

            $channels = $this->preferenceService->resolveChannels($user, 'im_message');
            if (empty($channels)) {
                continue;
            }

            if (in_array('database', $channels, true)) {
                $this->notificationService->send(
                    $recipientId,
                    'im_message',
                    "新私信 · {$senderName}",
                    $preview,
                    [
                        'conversation_id' => $message->conversation_id,
                        'message_id' => $message->id,
                        'sender_id' => $senderId,
                        'sender_name' => $senderName,
                        'message_type' => $message->message_type,
                    ],
                    $user->tenant_id,
                );
            }
        }
    }

    protected function buildPreview(ConversationMessage $message): string
    {
        if ($message->message_type === 'image') {
            return '[图片]';
        }
        if ($message->message_type === 'file') {
            return '[文件]';
        }
        if ($message->message_type === 'voice') {
            return '[语音]';
        }
        if ($message->message_type === 'card') {
            return '[卡片]';
        }
        if ($message->message_type === 'sticker') {
            return '[表情]';
        }

        $text = trim(strip_tags((string) $message->content));

        return Str::limit($text !== '' ? $text : '[消息]', 80);
    }
}
