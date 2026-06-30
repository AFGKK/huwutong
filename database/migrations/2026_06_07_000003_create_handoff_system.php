<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 转接请求表：AI → 人工 转接队列
        Schema::create('handoff_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('rag_conversations')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('提交人');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->comment('接管的客服');
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete()->comment('关联工单');
            $table->string('reason', 50)->default('low_confidence')->comment('转接原因: low_confidence/user_request/sensitive_topic/error_limit');
            $table->string('status', 30)->default('queued')->comment('queued/assigned/in_progress/resolved/closed/expired');
            $table->string('priority', 20)->default('medium')->comment('low/medium/high/urgent');
            $table->integer('queue_position')->nullable()->comment('队列位置');
            $table->integer('wait_time_seconds')->nullable()->comment('等待时间');
            $table->json('conversation_context')->nullable()->comment('对话上下文快照');
            $table->json('metadata')->nullable()->comment('intent, confidence, 页面信息等');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable()->comment('客服开始处理');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'assigned_to']);
            $table->index(['tenant_id', 'priority', 'status']);
        });

        // 客服消息表（转接后的人工聊天记录）
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handoff_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('发送人（null=系统消息）');
            $table->text('content');
            $table->string('sender_type', 20)->default('agent')->comment('agent/customer/system');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['handoff_request_id', 'created_at']);
        });

        // 客服操作日志（接/拒/转交/关闭）
        Schema::create('handoff_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handoff_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50)->comment('accept/reject/transfer/close/resolve/timeout');
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['handoff_request_id', 'action']);
        });

        // Agent 在线状态追踪
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'agent_status')) {
                $table->string('agent_status', 30)->nullable()->after('email')
                    ->default('offline')->comment('online/away/busy/offline');
                $table->timestamp('agent_status_changed_at')->nullable()->after('agent_status');
                $table->integer('max_concurrent_chats')->default(3)->after('agent_status_changed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['agent_status', 'agent_status_changed_at', 'max_concurrent_chats']);
        });
        Schema::dropIfExists('handoff_actions');
        Schema::dropIfExists('agent_messages');
        Schema::dropIfExists('handoff_requests');
    }
};
