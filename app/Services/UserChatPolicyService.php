<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Product;
use App\Models\UserDmMute;
use App\Models\UserFriend;
use App\Models\UserPrivacySetting;
use Illuminate\Support\Facades\DB;

class UserChatPolicyService
{
    public const DM_EVERYONE = 'everyone';
    public const DM_FOLLOWERS_ONLY = 'followers_only';
    public const DM_CLOSED = 'closed';

    public function isGloballyMuted(int $userId): bool
    {
        $mute = UserDmMute::where('user_id', $userId)->first();

        return $mute && $mute->isActive();
    }

    public function globalMuteMessage(int $userId): ?string
    {
        $mute = UserDmMute::where('user_id', $userId)->first();
        if (! $mute || ! $mute->isActive()) {
            return null;
        }

        $hours = max(1, (int) now()->diffInHours($mute->muted_until, false));

        return "因向陌生人发送过多未回复消息，您已被限制私信 {$hours} 小时";
    }

    public function isBlocked(int $userA, int $userB): bool
    {
        return UserFriend::where('status', 'blocked')
            ->where(function ($q) use ($userA, $userB) {
                $q->where(['requester_id' => $userA, 'addressee_id' => $userB])
                    ->orWhere(['requester_id' => $userB, 'addressee_id' => $userA]);
            })->exists();
    }

    public function areFriends(int $userA, int $userB): bool
    {
        return UserFriend::where('status', 'accepted')
            ->where(function ($q) use ($userA, $userB) {
                $q->where(['requester_id' => $userA, 'addressee_id' => $userB])
                    ->orWhere(['requester_id' => $userB, 'addressee_id' => $userA]);
            })->exists();
    }

    public function isFollowing(int $followerId, int $targetUserId): bool
    {
        if ($followerId === $targetUserId) {
            return false;
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('forum_follows')) {
            return DB::table('forum_follows')
                ->where('user_id', $followerId)
                ->where('target_user_id', $targetUserId)
                ->exists();
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('follows')) {
            return DB::table('follows')
                ->where('user_id', $followerId)
                ->where('followable_type', 'App\\Models\\User')
                ->where('followable_id', $targetUserId)
                ->exists();
        }

        return false;
    }

    public function getDmPolicy(int $userId): string
    {
        $settings = UserPrivacySetting::defaultFor($userId);
        $policy = $settings->dm_policy ?? null;

        if (in_array($policy, [self::DM_EVERYONE, self::DM_FOLLOWERS_ONLY, self::DM_CLOSED], true)) {
            return $policy;
        }

        return ($settings->allow_stranger_message ?? false) ? self::DM_EVERYONE : self::DM_FOLLOWERS_ONLY;
    }

    /**
     * @return array{allowed: bool, reason?: string, requires_request?: bool, seller_inquiry?: bool}
     */
    public function evaluatePrivateMessage(int $senderId, int $recipientId, ?int $productId = null): array
    {
        if ($senderId === $recipientId) {
            return ['allowed' => false, 'reason' => '不能给自己发私信'];
        }

        if ($this->isGloballyMuted($senderId)) {
            return ['allowed' => false, 'reason' => $this->globalMuteMessage($senderId)];
        }

        if ($this->isBlocked($senderId, $recipientId)) {
            return ['allowed' => false, 'reason' => '无法向已拉黑的用户发送消息'];
        }

        if ($this->areFriends($senderId, $recipientId)) {
            return ['allowed' => true, 'requires_request' => false];
        }

        if ($productId) {
            $product = Product::find($productId);
            if ($product && (int) $product->user_id === $recipientId) {
                return ['allowed' => true, 'requires_request' => false, 'seller_inquiry' => true];
            }
        }

        $policy = $this->getDmPolicy($recipientId);

        if ($policy === self::DM_CLOSED) {
            return ['allowed' => false, 'reason' => '对方已关闭私信'];
        }

        if ($policy === self::DM_FOLLOWERS_ONLY && ! $this->isFollowing($senderId, $recipientId)) {
            return ['allowed' => false, 'reason' => '对方仅接受关注的人私信'];
        }

        return ['allowed' => true, 'requires_request' => true];
    }

    public function markRecipientRequestPending(int $conversationId, int $recipientId): void
    {
        $participant = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $recipientId)
            ->whereNull('deleted_at')
            ->first();

        if (! $participant || $participant->request_status === 'rejected') {
            return;
        }

        if (! in_array($participant->request_status, ['pending', 'accepted'], true)) {
            $participant->update(['request_status' => 'pending']);
        }
    }

    public function acceptMessageRequest(int $conversationId, int $recipientId): bool
    {
        $updated = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $recipientId)
            ->where('request_status', 'pending')
            ->update([
                'request_status' => 'accepted',
                'request_responded_at' => now(),
            ]);

        return $updated > 0;
    }

    public function rejectMessageRequest(int $conversationId, int $recipientId): bool
    {
        $updated = ConversationParticipant::where('conversation_id', $conversationId)
            ->where('user_id', $recipientId)
            ->where('request_status', 'pending')
            ->update([
                'request_status' => 'rejected',
                'request_responded_at' => now(),
            ]);

        return $updated > 0;
    }

    public function applyHarassmentCheck(int $senderId, int $recipientId, int $conversationId): ?UserDmMute
    {
        if ($this->areFriends($senderId, $recipientId)) {
            return null;
        }

        $recipientReplied = ConversationMessage::where('conversation_id', $conversationId)
            ->where('sender_id', $recipientId)
            ->exists();

        if ($recipientReplied) {
            return null;
        }

        $sentCount = ConversationMessage::where('conversation_id', $conversationId)
            ->where('sender_id', $senderId)
            ->count();

        if ($sentCount < 5) {
            return null;
        }

        return UserDmMute::updateOrCreate(
            ['user_id' => $senderId],
            [
                'muted_until' => now()->addHours(24),
                'reason' => 'stranger_harassment',
            ]
        );
    }

    public function syncDmPolicyFromLegacy(UserPrivacySetting $settings): void
    {
        if (! empty($settings->dm_policy)) {
            return;
        }

        $settings->dm_policy = ($settings->allow_stranger_message ?? false)
            ? self::DM_EVERYONE
            : self::DM_FOLLOWERS_ONLY;
        $settings->save();
    }
}
