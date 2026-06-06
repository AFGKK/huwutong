<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 工单分类
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 工单
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('提交人');
            $table->foreignId('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->comment('分配给');
            $table->string('subject', 300);
            $table->longText('description');
            $table->string('priority', 20)->default('medium')->comment('low/medium/high/urgent');
            $table->string('status', 30)->default('open')->comment('open/pending/replied/resolved/closed');
            $table->string('source', 30)->default('portal')->comment('portal/email/chat/api/admin');
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable()->comment('关联对话上下文、浏览器信息等');
            $table->integer('sla_minutes')->nullable()->comment('SLA 响应时限（分钟）');
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['priority', 'status']);
        });

        // 工单回复
        Schema::create('ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('content');
            $table->boolean('is_internal')->default(false)->comment('内部备注，不展示给客户');
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        // 工单 SLA 事件
        Schema::create('ticket_sla_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50)->comment('sla_breach/sla_warning/response_deadline');
            $table->timestamp('triggered_at');
            $table->boolean('notified')->default(false);
            $table->timestamps();
        });

        // 满意度评价
        Schema::create('ticket_satisfactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('score')->comment('1-5');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_satisfactions');
        Schema::dropIfExists('ticket_sla_events');
        Schema::dropIfExists('ticket_replies');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_categories');
    }
};
