<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_insights')) {
            return;
        }

        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();

            // 关联对话（可选）
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->unsignedBigInteger('message_id')->nullable()->index();

            // 洞察内容
            $table->string('type', 30)->index()->comment('类型: follow_up/reminder/suggestion/insight/alert');
            $table->string('title', 200);
            $table->text('content');
            $table->json('context')->nullable()->comment('上下文数据（对话摘要、相关消息等）');

            // 推送状态
            $table->string('status', 20)->default('pending')->index()
                ->comment('pending/sent/read/dismissed');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();

            // 来源
            $table->string('source', 30)->default('scan_job')->comment('触发来源: scan_job/manual/system');

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'created_at'], 'idx_insights_user_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
    }
};
