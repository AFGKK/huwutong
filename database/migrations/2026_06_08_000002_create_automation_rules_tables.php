<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 自动化规则定义 ───
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 200)->comment('规则名称');
            $table->string('slug', 100)->nullable()->unique()->comment('唯一标识');
            $table->text('description')->nullable();
            $table->string('category', 50)->default('custom')->comment('分类: license/billing/customer/security/system/custom');
            $table->string('trigger_type', 50)->comment('触发器类型: event/schedule/webhook/condition');
            $table->json('trigger_config')->nullable()->comment('触发器配置');
            $table->json('conditions')->nullable()->comment('条件列表 [{field,operator,value}]');
            $table->string('condition_logic', 10)->default('all')->comment('条件逻辑: all/any');
            $table->json('actions')->comment('动作列表 [{type,config}]');
            $table->string('action_execution', 20)->default('sequential')->comment('动作执行: sequential/parallel/first_success');
            $table->string('status', 30)->default('draft')->comment('draft/active/paused/archived');
            $table->unsignedInteger('priority')->default(0)->comment('优先级(高值优先)');
            $table->unsignedInteger('cooldown_minutes')->default(0)->comment('冷却时间(分钟)');
            $table->unsignedInteger('max_executions_per_hour')->default(0)->comment('每小时最多执行次数(0=不限)');
            $table->unsignedInteger('max_executions_per_day')->default(0)->comment('每天最多执行次数(0=不限)');
            $table->unsignedInteger('execution_count')->default(0)->comment('总执行次数');
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_template')->default(false)->comment('是否为模板');
            $table->boolean('is_system')->default(false)->comment('是否为系统规则');
            $table->timestamps();

            $table->index(['tenant_id', 'category', 'status']);
            $table->index(['tenant_id', 'trigger_type']);
        });

        // ─── 动作日志 ───
        Schema::create('automation_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->string('trigger_source', 100)->nullable()->comment('触发源描述');
            $table->json('trigger_data')->nullable()->comment('触发时数据快照');
            $table->json('conditions_result')->nullable()->comment('条件评估结果');
            $table->string('status', 30)->default('pending')->comment('pending/running/completed/failed/timeout');
            $table->unsignedInteger('action_count')->default(0)->comment('动作数');
            $table->unsignedInteger('successful_actions')->default(0);
            $table->unsignedInteger('failed_actions')->default(0);
            $table->text('error_message')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['rule_id', 'created_at']);
            $table->index(['rule_id', 'status']);
        });

        // ─── 动作执行记录（单步） ───
        Schema::create('automation_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('automation_execution_logs')->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->integer('action_index')->default(0);
            $table->string('action_type', 50)->comment('动作类型');
            $table->json('action_config')->nullable()->comment('执行时配置快照');
            $table->json('input_data')->nullable();
            $table->json('output_data')->nullable();
            $table->string('status', 30)->default('pending');
            $table->integer('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['execution_id', 'action_index']);
        });

        // ─── Webhook 端点（回调集成） ───
        Schema::create('automation_webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('url', 1000);
            $table->string('method', 10)->default('POST');
            $table->json('headers')->nullable();
            $table->json('body_template')->nullable()->comment('请求体模板');
            $table->string('auth_type', 30)->nullable()->comment('none/basic/bearer/custom');
            $table->json('auth_config')->nullable();
            $table->json('retry_config')->nullable()->comment('重试配置 {max_retries, delay_seconds}');
            $table->json('timeout_config')->nullable()->comment('超时配置 {connect, read, write}');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // 规则 - Webhook 关联表
        Schema::create('automation_rule_webhook', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->foreignId('webhook_id')->constrained('automation_webhooks')->cascadeOnDelete();
            $table->unique(['rule_id', 'webhook_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rule_webhook');
        Schema::dropIfExists('automation_webhooks');
        Schema::dropIfExists('automation_action_logs');
        Schema::dropIfExists('automation_execution_logs');
        Schema::dropIfExists('automation_rules');
    }
};
