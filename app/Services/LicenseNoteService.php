<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseNote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * M2-55 License 内部备注服务
 *
 * 运营人员为特定 License 添加内部备注、@提及同事、
 * 备注历史时间线管理。
 */
class LicenseNoteService
{
    /**
     * 获取 License 的所有备注
     */
    public function getNotes(License $license, int $limit = 50): array
    {
        return $license->notes()
            ->with('user:id,name,email')
            ->latest()
            ->take($limit)
            ->get()
            ->map(function ($note) {
                $note->setRelation('mentioned_users', $this->resolveMentions($note->mentions ?? []));
                return $note;
            })
            ->toArray();
    }

    /**
     * 添加备注
     */
    public function addNote(License $license, User $user, string $content, array $mentions = []): LicenseNote
    {
        $maxLength = config('license-notes.limits.max_content_length', 5000);
        $maxMentions = config('license-notes.limits.max_mentions', 10);
        $maxNotes = config('license-notes.limits.max_notes_per_license', 500);

        if (mb_strlen($content) > $maxLength) {
            throw new \InvalidArgumentException("备注内容不能超过 {$maxLength} 个字符");
        }

        if (count($mentions) > $maxMentions) {
            throw new \InvalidArgumentException("每次最多 @提及 {$maxMentions} 人");
        }

        $noteCount = $license->notes()->count();
        if ($noteCount >= $maxNotes) {
            throw new \RuntimeException("每个 License 最多 {$maxNotes} 条备注");
        }

        $note = $license->notes()->create([
            'user_id' => $user->id,
            'content' => $content,
            'mentions' => $mentions,
        ]);

        $note->load('user:id,name,email');

        // 发送 @提及 通知
        if (!empty($mentions) && config('license-notes.mention.notify_on_mention', true)) {
            $this->sendMentionNotifications($note, $license, $user);
        }

        Log::info('License 内部备注已添加', [
            'license_id' => $license->id,
            'note_id' => $note->id,
            'user_id' => $user->id,
            'mentions' => $mentions,
        ]);

        return $note;
    }

    /**
     * 删除备注
     */
    public function deleteNote(License $license, LicenseNote $note, User $user): void
    {
        if ($note->license_id !== $license->id) {
            throw new \RuntimeException('备注不属于该 License');
        }

        if ($note->user_id !== $user->id && !$user->hasPermissionTo('super-admin')) {
            throw new \RuntimeException('只能删除自己的备注');
        }

        $note->delete();

        Log::info('License 内部备注已删除', [
            'license_id' => $license->id,
            'note_id' => $note->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * 搜索用户（用于 @提及）
     */
    public function searchUsers(string $query): array
    {
        $limit = config('license-notes.mention.search_limit', 20);

        return User::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('email', 'like', "%{$query}%");
        })
        ->limit($limit)
        ->get(['id', 'name', 'email'])
        ->toArray();
    }

    /**
     * 获取 License 备注统计
     */
    public function getStats(License $license): array
    {
        $notes = $license->notes();

        return [
            'total' => (clone $notes)->count(),
            'recent_7d' => (clone $notes)->where('created_at', '>=', now()->subDays(7))->count(),
            'unique_users' => (clone $notes)->distinct('user_id')->count('user_id'),
        ];
    }

    /**
     * 解析 @提及 用户列表
     */
    protected function resolveMentions(array $mentionIds): array
    {
        if (empty($mentionIds)) {
            return [];
        }

        return User::whereIn('id', $mentionIds)
            ->get(['id', 'name', 'email'])
            ->toArray();
    }

    /**
     * 发送 @提及 通知
     */
    protected function sendMentionNotifications(LicenseNote $note, License $license, User $sender): void
    {
        $template = config('license-notes.mention.notification_template', '您在 License #{license_id} 的备注中被 @提及');
        $message = str_replace('{license_id}', $license->id, $template);

        foreach ($note->mentions ?? [] as $mentionedUserId) {
            if ((int) $mentionedUserId === $sender->id) {
                continue; // 不通知自己
            }
            // 简化实现：记录日志，实际可对接通知中心
            Log::debug('发送 @提及 通知', [
                'to_user_id' => $mentionedUserId,
                'from_user_id' => $sender->id,
                'license_id' => $license->id,
                'message' => $message,
            ]);
        }
    }
}
