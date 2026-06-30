<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SOP模板表 — 安全事件响应预案
        if (!Schema::hasTable('security_sop_templates')) {
            Schema::create('security_sop_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name')->comment('SOP名称');
                $table->string('slug')->unique()->comment('唯一标识');
                $table->text('description')->nullable();
                $table->string('severity', 20)->default('warning')
                    ->comment('适用等级: info|warning|critical');
                $table->json('trigger_conditions')->nullable()
                    ->comment('触发条件: {event_types:[], threshold:number, time_window_minutes:number}');
                $table->json('steps')->nullable()
                    ->comment('SOP步骤: [{order, action_type, config, description}]');
                $table->string('status', 20)->default('active')
                    ->comment('active|inactive|draft');
                $table->boolean('is_auto_execute')->default(false)
                    ->comment('是否自动执行(无需人工审批)');
                $table->integer('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'severity']);
                $table->index('slug');
            });
        }

        // SOP执行记录表 — 记录每次SOP的执行情况
        if (!Schema::hasTable('security_sop_executions')) {
            Schema::create('security_sop_executions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('sop_template_id')->constrained('security_sop_templates')->cascadeOnDelete();
                $table->unsignedBigInteger('event_id')->nullable()->comment('FK -> security_events');
                $table->string('triggered_by', 50)->comment('event|manual');
                $table->json('execution_log')->nullable()->comment('执行日志: [{step, action, status, result, executed_at}]');
                $table->string('status', 20)->default('pending')
                    ->comment('pending|in_progress|completed|failed|partially_completed');
                $table->integer('total_steps')->default(0);
                $table->integer('completed_steps')->default(0);
                $table->text('result_summary')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        // 给security_events添加SOP关联和解决字段
        if (Schema::hasTable('security_events')) {
            if (!Schema::hasColumn('security_events', 'sop_execution_id')) {
                Schema::table('security_events', function (Blueprint $table) {
                    $table->unsignedBigInteger('sop_execution_id')->nullable()->after('metadata');
                    $table->string('resolution_status', 20)->nullable()
                        ->default(null)->after('sop_execution_id')
                        ->comment('open|in_progress|resolved|false_positive');
                    $table->timestamp('resolved_at')->nullable()->after('resolution_status');
                    $table->text('resolution_notes')->nullable()->after('resolved_at');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('security_events')) {
            Schema::table('security_events', function (Blueprint $table) {
                $table->dropForeign(['sop_execution_id']);
                $table->dropColumn(['sop_execution_id', 'resolution_status', 'resolved_at', 'resolution_notes']);
            });
        }
        Schema::dropIfExists('security_sop_executions');
        Schema::dropIfExists('security_sop_templates');
    }
};
