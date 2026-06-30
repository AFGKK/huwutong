<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduled_notifications')) {
            return;
        }
        // 定时通知表
        if (!Schema::hasTable('scheduled_notifications')) {
            Schema::create('scheduled_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('title', 200)->comment('通知标题');
                $table->string('type', 50)->comment('通知类型: maintenance/holiday/promotion/announcement/update/policy/custom');
                $table->string('channel', 30)->default('email')->comment('发送渠道: email/in_app/sms');
                $table->text('content')->comment('通知内容（支持变量替换）');
                $table->text('rich_content')->nullable()->comment('富文本内容');
                $table->string('action_url', 500)->nullable()->comment('行动链接');
                $table->string('action_text', 100)->nullable()->comment('按钮文字');
                $table->string('status', 30)->default('draft')->comment('draft/scheduled/sending/sent/partial/cancelled/failed');
                $table->timestamp('scheduled_at')->nullable()->comment('定时发送时间');
                $table->timestamp('sent_at')->nullable()->comment('实际发送时间');
                $table->unsignedInteger('total_recipients')->default(0)->comment('总接收人数');
                $table->unsignedInteger('success_count')->default(0)->comment('成功数');
                $table->unsignedInteger('failure_count')->default(0)->comment('失败数');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('filters')->nullable()->comment('接收人筛选条件');
                $table->json('metadata')->nullable()->comment('扩展数据');
                $table->boolean('is_cancelled')->default(false);
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'scheduled_at']);
                $table->index('type');
            });
        }

        // 通知投递记录表
        if (!Schema::hasTable('notification_delivery_logs')) {
            Schema::create('notification_delivery_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notification_id')->constrained('scheduled_notifications')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('email', 200)->nullable()->comment('收件邮箱');
                $table->string('phone', 30)->nullable()->comment('收件手机号');
                $table->string('status', 30)->default('pending')->comment('pending/sent/failed/cancelled');
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['notification_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('scheduled_notifications');
    }
};
