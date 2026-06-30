<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAppeal;
use App\Notifications\AppealStatusChanged;
use Illuminate\Support\Facades\DB;

class AccountAppealService
{
    /**
     * 提交申诉
     */
    public function submitAppeal(int $userId, array $data): UserAppeal
    {
        $user = User::findOrFail($userId);

        // 检查是否为 active 状态 - active 用户不能申诉
        if ($user->status === 'active') {
            throw new \RuntimeException('账号状态正常，无需申诉');
        }

        // 检查是否有待处理的申诉
        $pending = UserAppeal::where('user_id', $userId)
            ->whereIn('status', ['pending', 'reviewing'])
            ->exists();

        if ($pending) {
            throw new \RuntimeException('已有待处理的申诉，请耐心等待');
        }

        return DB::transaction(function () use ($userId, $data) {
            $appeal = UserAppeal::create([
                'user_id' => $userId,
                'status' => 'pending',
                'reason' => $data['reason'],
                'explanation' => $data['explanation'] ?? null,
                'attachments' => $data['attachments'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'appealed_at' => now(),
            ]);

            // 通知管理员（通过 NotificationService）
            try {
                $notificationService = app(NotificationService::class);
                $admins = User::where('user_type', 'admin')->orWhere('user_type', 'super-admin')->pluck('id')->toArray();
                if (!empty($admins)) {
                    $notificationService->send(
                        userIds: $admins,
                        type: 'appeal_submitted',
                        title: '新的账号申诉',
                        content: "用户 #{$userId} 提交了账号申诉，原因：{$data['reason']}",
                        payload: ['appeal_id' => $appeal->id, 'user_id' => $userId],
                    );
                }
            } catch (\Exception $e) {
                // 通知失败不影响申诉提交
            }

            return $appeal;
        });
    }

    /**
     * 审核申诉
     */
    public function reviewAppeal(int $appealId, int $reviewerId, string $action, ?string $comment = null): UserAppeal
    {
        $appeal = UserAppeal::findOrFail($appealId);

        if (!in_array($appeal->status, ['pending', 'reviewing'])) {
            throw new \RuntimeException('申诉已被处理，无法重复审核');
        }

        if (!in_array($action, ['approve', 'reject'])) {
            throw new \RuntimeException('无效的审核操作');
        }

        return DB::transaction(function () use ($appeal, $reviewerId, $action, $comment) {
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';

            $appeal->update([
                'status' => $newStatus,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'review_comment' => $comment,
            ]);

            // 如果申诉通过，恢复账号
            if ($action === 'approve') {
                $appeal->user->update([
                    'status' => 'active',
                    'banned_at' => null,
                    'banned_reason' => null,
                    'banned_by' => null,
                ]);
            }

            // 通知申诉用户
            try {
                $appeal->user->notify(new AppealStatusChanged($appeal));
            } catch (\Exception $e) {
                // 通知失败不影响审核
            }

            return $appeal->fresh()->load(['user:id,name,email,status', 'reviewer:id,name']);
        });
    }

    /**
     * 封禁用户（管理员操作）
     */
    public function banUser(int $targetUserId, int $adminId, string $reason): User
    {
        $user = User::findOrFail($targetUserId);

        if ($user->status === 'inactive' && $user->banned_at) {
            throw new \RuntimeException('该账号已被封禁');
        }

        $user->update([
            'status' => 'inactive',
            'banned_at' => now(),
            'banned_reason' => $reason,
            'banned_by' => $adminId,
        ]);

        // 撤销所有 token
        $user->tokens()->delete();

        return $user->fresh();
    }

    /**
     * 解封用户（管理员操作）
     */
    public function unbanUser(int $targetUserId, int $adminId): User
    {
        $user = User::findOrFail($targetUserId);

        $user->update([
            'status' => 'active',
            'banned_at' => null,
            'banned_reason' => null,
            'banned_by' => null,
        ]);

        return $user->fresh();
    }
}
