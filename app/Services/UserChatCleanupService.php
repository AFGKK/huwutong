<?php

namespace App\Services;

use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\MessageFavorite;
use App\Models\User;
use App\Models\UserDmMute;
use App\Models\UserFriend;
use App\Models\UserOnlineStatus;
use App\Models\UserReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 用户注销/匿名化时的 IM 数据清理
 */
class UserChatCleanupService
{
    public function cleanupForDeletedUser(User $user): array
    {
        $userId = $user->id;
        $results = [];

        $messagesUpdated = ConversationMessage::where('sender_id', $userId)
            ->whereNull('deleted_at')
            ->update([
                'content' => '[此用户已注销]',
                'attachments' => null,
                'metadata' => null,
                'is_recalled' => true,
            ]);
        if ($messagesUpdated > 0) {
            $results['conversation_messages'] = $messagesUpdated;
        }

        $participantsUpdated = ConversationParticipant::where('user_id', $userId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
        if ($participantsUpdated > 0) {
            $results['conversation_participants'] = $participantsUpdated;
        }

        $friendsDeleted = UserFriend::where(function ($q) use ($userId) {
            $q->where('requester_id', $userId)->orWhere('addressee_id', $userId);
        })->delete();
        if ($friendsDeleted > 0) {
            $results['user_friends'] = $friendsDeleted;
        }

        if (Schema::hasTable('user_dm_mutes')) {
            $mutesDeleted = UserDmMute::where('user_id', $userId)->delete();
            if ($mutesDeleted > 0) {
                $results['user_dm_mutes'] = $mutesDeleted;
            }
        }

        if (Schema::hasTable('message_favorites')) {
            $favoritesDeleted = MessageFavorite::where('user_id', $userId)->delete();
            if ($favoritesDeleted > 0) {
                $results['message_favorites'] = $favoritesDeleted;
            }
        }

        if (Schema::hasTable('user_reports')) {
            $reportsDeleted = UserReport::where('reporter_id', $userId)->delete();
            if ($reportsDeleted > 0) {
                $results['user_reports'] = $reportsDeleted;
            }
        }

        if (Schema::hasTable('user_online_statuses')) {
            $statusDeleted = UserOnlineStatus::where('user_id', $userId)->delete();
            if ($statusDeleted > 0) {
                $results['user_online_statuses'] = $statusDeleted;
            }
        }

        Log::info('IM 数据已清理（用户注销）', [
            'user_id' => $userId,
            'affected' => $results,
        ]);

        return $results;
    }
}
