<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\ForumPost;
use App\Models\ForumReply;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * 私信 + 社区互动通知（消息中心；受免打扰与偏好约束）
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

    /**
     * 动态被点赞
     */
    public function notifyLike(int $actorId, ForumPost $post): void
    {
        $recipientId = (int) $post->user_id;
        if ($recipientId <= 0 || $recipientId === $actorId) {
            return;
        }

        $actor = User::select('id', 'name', 'avatar', 'tenant_id')->find($actorId);
        if (! $actor) {
            return;
        }

        if (! $this->canWriteInteraction($recipientId)) {
            return;
        }

        $actorPayload = $this->actorPayload($actor);
        $preview = $this->postPreview($post);
        $groupKey = 'like:post:'.$post->id.':'.now()->format('Y-m-d');

        $this->notificationService->sendAggregated(
            $recipientId,
            'interaction_like',
            $groupKey,
            function (int $count, array $actors) use ($actorPayload, $preview) {
                $first = $actors[0]['name'] ?? $actorPayload['name'];
                if ($count <= 1) {
                    return [
                        __('app.api.service_notification.interaction_like_title', ['name' => $first]),
                        $preview,
                    ];
                }

                return [
                    __('app.api.service_notification.interaction_like_title_n', [
                        'name' => $first,
                        'n' => $count,
                    ]),
                    $preview,
                ];
            },
            [
                'actor' => $actorPayload,
                'target_type' => 'moment',
                'target_id' => $post->id,
                'moment_id' => $post->id,
                'url' => '/build/plaza/'.$post->id,
            ],
            $actor->tenant_id ?? null,
        );
    }

    /**
     * 动态被评论 / 楼中楼回复
     */
    public function notifyComment(int $actorId, ForumPost $post, ForumReply $reply, ?ForumReply $parent = null): void
    {
        $actor = User::select('id', 'name', 'avatar', 'tenant_id')->find($actorId);
        if (! $actor) {
            return;
        }

        $actorPayload = $this->actorPayload($actor);
        $preview = Str::limit(trim(strip_tags((string) $reply->content)), 80);
        $recipients = [];

        // 通知帖主
        $authorId = (int) $post->user_id;
        if ($authorId > 0 && $authorId !== $actorId) {
            $recipients[$authorId] = 'interaction_comment';
        }

        // 楼中楼：通知被回复的评论作者
        if ($parent) {
            $parentAuthorId = (int) $parent->user_id;
            if ($parentAuthorId > 0 && $parentAuthorId !== $actorId) {
                $recipients[$parentAuthorId] = 'interaction_comment';
            }
        }

        foreach ($recipients as $recipientId => $type) {
            if (! $this->canWriteInteraction($recipientId)) {
                continue;
            }

            $isReply = $parent && (int) $parent->user_id === (int) $recipientId;
            $title = $isReply
                ? __('app.api.service_notification.interaction_reply_title', ['name' => $actorPayload['name']])
                : __('app.api.service_notification.interaction_comment_title', ['name' => $actorPayload['name']]);

            $this->notificationService->send(
                $recipientId,
                $type,
                $title,
                $preview !== '' ? $preview : __('app.api.service_notification.interaction_comment_fallback'),
                [
                    'actor' => $actorPayload,
                    'actors' => [$actorPayload],
                    'count' => 1,
                    'target_type' => 'moment',
                    'target_id' => $post->id,
                    'moment_id' => $post->id,
                    'reply_id' => $reply->id,
                    'parent_reply_id' => $parent?->id,
                    'url' => '/build/plaza/'.$post->id,
                ],
                $actor->tenant_id ?? null,
            );
        }

        $this->notifyMentionsInText($actor, (string) $reply->content, $post, $reply->id);
    }

    /**
     * 新增关注
     */
    public function notifyFollow(int $actorId, int $targetUserId): void
    {
        if ($targetUserId <= 0 || $targetUserId === $actorId) {
            return;
        }

        $actor = User::select('id', 'name', 'avatar', 'tenant_id')->find($actorId);
        if (! $actor) {
            return;
        }

        if (! $this->canWriteInteraction($targetUserId)) {
            return;
        }

        $actorPayload = $this->actorPayload($actor);
        $groupKey = 'follow:'.$targetUserId.':'.now()->format('Y-m-d');

        $this->notificationService->sendAggregated(
            $targetUserId,
            'interaction_follow',
            $groupKey,
            function (int $count, array $actors) use ($actorPayload) {
                $first = $actors[0]['name'] ?? $actorPayload['name'];
                if ($count <= 1) {
                    return [
                        __('app.api.service_notification.interaction_follow_title', ['name' => $first]),
                        __('app.api.service_notification.interaction_follow_body'),
                    ];
                }

                return [
                    __('app.api.service_notification.interaction_follow_title_n', [
                        'name' => $first,
                        'n' => $count,
                    ]),
                    __('app.api.service_notification.interaction_follow_body'),
                ];
            },
            [
                'actor' => $actorPayload,
                'target_type' => 'user',
                'target_id' => $actorId,
                'user_id' => $actorId,
                'url' => '/build/plaza/user/'.$actorId,
            ],
            $actor->tenant_id ?? null,
        );
    }

    /**
     * 从评论文本解析 @昵称 并通知被提及用户
     */
    public function notifyMentionsInText(User $actor, string $text, ForumPost $post, ?int $replyId = null): void
    {
        if (! preg_match_all('/@([^\s@，,。.！!？?#]+)/u', $text, $matches)) {
            return;
        }

        $names = collect($matches[1] ?? [])
            ->map(fn ($n) => trim($n))
            ->filter()
            ->unique()
            ->take(10)
            ->values();

        if ($names->isEmpty()) {
            return;
        }

        $mentioned = User::query()
            ->select('id', 'name', 'avatar', 'tenant_id')
            ->whereIn('name', $names->all())
            ->limit(10)
            ->get();

        $actorPayload = $this->actorPayload($actor);
        $preview = Str::limit(trim(strip_tags($text)), 80);

        foreach ($mentioned as $user) {
            if ((int) $user->id === (int) $actor->id) {
                continue;
            }
            if (! $this->canWriteInteraction((int) $user->id)) {
                continue;
            }

            $this->notificationService->send(
                $user->id,
                'interaction_mention',
                __('app.api.service_notification.interaction_mention_title', ['name' => $actorPayload['name']]),
                $preview !== '' ? $preview : __('app.api.service_notification.interaction_mention_fallback'),
                [
                    'actor' => $actorPayload,
                    'actors' => [$actorPayload],
                    'count' => 1,
                    'target_type' => 'moment',
                    'target_id' => $post->id,
                    'moment_id' => $post->id,
                    'reply_id' => $replyId,
                    'url' => '/build/plaza/'.$post->id,
                ],
                $actor->tenant_id ?? $user->tenant_id,
            );
        }
    }

    protected function canWriteInteraction(int $recipientId): bool
    {
        $user = User::find($recipientId);
        if (! $user) {
            return false;
        }

        $channels = $this->preferenceService->resolveChannels($user, 'interaction');

        return in_array('database', $channels, true);
    }

    protected function actorPayload(User $actor): array
    {
        $avatar = $actor->avatar
            ? (str_starts_with((string) $actor->avatar, 'http') ? $actor->avatar : url('storage/'.$actor->avatar))
            : null;

        return [
            'id' => (int) $actor->id,
            'name' => $actor->name ?: __('app.api.service_notification.interaction_user_fallback'),
            'avatar' => $avatar,
        ];
    }

    protected function postPreview(ForumPost $post): string
    {
        $text = trim(strip_tags((string) $post->content));

        return Str::limit($text !== '' ? $text : __('app.api.service_notification.interaction_post_fallback'), 80);
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
