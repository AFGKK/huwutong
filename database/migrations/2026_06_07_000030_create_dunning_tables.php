<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 表可能已通过其他方式创建，跳过已存在的表
        if (Schema::hasTable('dunning_strategies')) {
            return;
        }

        // 催缴策略定义
        Schema::create('dunning_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('stages')->comment('催缴阶段定义：[{day, action, channel, message_template}]');
            $table->integer('max_attempts')->default(5);
            $table->boolean('is_active')->default(true);
            $table->string('applicable_plans')->nullable()->comment('JSON array of plan slugs, null = all');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 催缴队列 — 每个待催缴的发票
        Schema::create('dunning_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dunning_strategy_id')->nullable()->constrained()->nullOnDelete();

            $table->integer('attempt_count')->default(0);
            $table->integer('current_stage')->default(0);
            $table->string('status')->default('pending'); // pending, in_progress, paid, failed, resolved, expired

            $table->decimal('amount_due', 12, 2)->default(0);
            $table->string('currency', 3)->default('CNY');
            $table->timestamp('next_action_at')->nullable()->index();
            $table->timestamp('last_action_at')->nullable();
            $table->timestamp('enqueued_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_action_at']);
            $table->index(['subscription_id', 'status']);
        });

        // 催缴日志
        Schema::create('dunning_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dunning_queue_id')->constrained('dunning_queue')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->cascadeOnDelete();

            $table->integer('attempt_number');
            $table->string('action_taken'); // send_reminder, send_warning, retry_payment, downgrade, suspend, escalate, resolve
            $table->string('channel')->nullable(); // email, sms, in_app, none
            $table->boolean('success')->default(false);

            $table->text('request_data')->nullable();
            $table->text('response_data')->nullable();
            $table->text('error_message')->nullable();

            $table->string('next_stage_planned')->nullable();
            $table->timestamp('next_action_planned_at')->nullable();
            $table->timestamp('actioned_at')->useCurrent();

            $table->timestamps();

            $table->index(['dunning_queue_id', 'attempt_number']);
            $table->index(['subscription_id', 'actioned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dunning_logs');
        Schema::dropIfExists('dunning_queue');
        Schema::dropIfExists('dunning_strategies');
    }
};
