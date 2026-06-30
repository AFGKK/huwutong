<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 给通知记录表增加佣金相关索引
        Schema::table('notifications', function (Blueprint $table) {
            // 复合索引：按用户+类型+已读，加速通知中心查询
            $table->index(['user_id', 'type', 'is_read'], 'idx_notifications_user_type_read');
            // 按创建时间排序加速
            $table->index(['user_id', 'created_at'], 'idx_notifications_user_created');
        });

        // 2. 给 notification_preferences 补充佣金通知类型的默认值
        // 将现有用户的偏好中的 channels 和 types 合并佣金通知类型
        $earningTypes = [
            'commission_credited' => ['database', 'mail'],
            'commission_released' => ['database', 'mail'],
            'payout_status' => ['database', 'mail'],
            'monthly_report' => ['mail'],
            'threshold_reached' => ['database', 'mail'],
            'negative_balance' => ['database', 'mail', 'sms'],
        ];

        // 为已有偏好的用户补充佣金通知配置
        DB::table('notification_preferences')->orderBy('id')->chunkById(100, function ($prefs) use ($earningTypes) {
            foreach ($prefs as $pref) {
                $types = json_decode($pref->types ?? '{}', true) ?? [];
                $updated = false;

                foreach ($earningTypes as $type => $channels) {
                    if (! isset($types[$type])) {
                        $types[$type] = $channels;
                        $updated = true;
                    }
                }

                if ($updated) {
                    DB::table('notification_preferences')
                        ->where('id', $pref->id)
                        ->update(['types' => json_encode($types)]);
                }
            }
        });

        // 为没有偏好的用户创建默认偏好（通过 User 模型插入时处理）
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_type_read');
            $table->dropIndex('idx_notifications_user_created');
        });
    }
};
