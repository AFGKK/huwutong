<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 内部笔记（可关联到 License/Customer/Ticket 等任意实体）
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->morphs('notable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->json('mentions')->nullable()->comment('@提及的用户ID列表');
            $table->json('attachments')->nullable()->comment('附件列表 [{name, url, size}]');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_internal')->default(true)->comment('内部笔记/客户可见');
            $table->timestamps();
            $table->softDeletes();
        });

        // 实体变更日志（License/Customer/Ticket 等状态变更）
        Schema::create('change_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('changelogable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 100)->comment('变更事件: created/updated/status_changed/transferred');
            $table->string('field', 100)->nullable()->comment('变更字段');
            $table->text('old_value')->nullable()->comment('旧值');
            $table->text('new_value')->nullable()->comment('新值');
            $table->text('description')->nullable()->comment('变更描述');
            $table->json('context')->nullable()->comment('额外上下文');
            $table->timestamps();
            $table->index('event');
            $table->index('created_at');
        });

        // 协作活动流（统一的团队活动记录）
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 50)->comment('活动类型: note_created/note_updated/comment_added/status_changed/license_transferred/etc');
            $table->text('description')->comment('活动描述');
            $table->morphs('subject');
            $table->json('metadata')->nullable()->comment('额外元数据');
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('type');
            $table->index(['tenant_id', 'created_at']);
        });

        // 用户协作预设（快捷回复模板）
        Schema::create('canned_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('content');
            $table->string('category', 50)->nullable()->comment('分类: general/license/ticket/customer');
            $table->boolean('is_shared')->default(false)->comment('是否团队共享');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'category']);
            $table->index(['tenant_id', 'is_shared']);
        });

        // 团队协作通知偏好
        Schema::create('collaboration_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('notify_on_mention')->default(true);
            $table->boolean('notify_on_note_reply')->default(true);
            $table->boolean('notify_on_status_change')->default(true);
            $table->boolean('daily_digest')->default(false)->comment('每日摘要');
            $table->string('digest_time', 5)->default('09:00')->comment('摘要发送时间');
            $table->timestamps();

            $table->unique('user_id');
        });

        // 活动订阅（用户关注某实体的变更） - watchlist 避免与 subscriptions 冲突
        Schema::create('watchlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('watchable');
            $table->string('reason', 50)->nullable()->comment('订阅原因: mentioned/owner/assigned/manual');
            $table->timestamps();

            $table->unique(['user_id', 'watchable_type', 'watchable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canned_replies');
        Schema::dropIfExists('collaboration_preferences');
        Schema::dropIfExists('watchlist');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('change_logs');
        Schema::dropIfExists('notes');
    }
};
