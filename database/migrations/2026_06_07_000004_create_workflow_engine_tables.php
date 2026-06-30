<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 工作流定义
        if (!Schema::hasTable('workflow_definitions')) {
            Schema::create('workflow_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique()->comment('工作流名称 (如: renewal_pipeline)');
                $table->string('description')->nullable();
                $table->json('steps_definition')->comment('步骤定义数组 [{name,class,timeout,retry_max,retry_delay,compensate_class}]');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 工作流实例
        if (!Schema::hasTable('workflow_instances')) {
            Schema::create('workflow_instances', function (Blueprint $table) {
                $table->id();
                $table->string('workflow_name', 100)->index();
                $table->morphs('workflowable');
                $table->string('status', 30)->default('running')->comment('running/completed/failed/cancelled/compensating');
                $table->string('current_step', 100)->nullable();
                $table->json('context')->comment('工作流上下文数据');
                $table->json('result')->nullable()->comment('最终结果');
                $table->string('error_message')->nullable();
                $table->integer('retry_count')->default(0);
                $table->integer('max_retries')->default(3);
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('next_retry_at')->nullable();
                $table->timestamps();

                $table->index(['workflow_name', 'status']);
                $table->index(['workflowable_type', 'workflowable_id', 'status'], 'wfi_workflowable_status_idx');
            });
        } else {
            // Fix index name if it was created with wrong name
            try {
                Schema::table('workflow_instances', function (Blueprint $table) {
                    $table->dropIndex(['workflowable_type', 'workflowable_id', 'status']);
                });
            } catch (\Throwable $e) {
                // Index may not exist or have different name
            }
        }

        // 工作流步骤执行记录
        if (!Schema::hasTable('workflow_step_executions')) {
            Schema::create('workflow_step_executions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
                $table->string('step_name', 100);
                $table->string('status', 30)->default('pending')->comment('pending/running/completed/failed/compensated/skipped');
                $table->json('input')->nullable();
                $table->json('output')->nullable();
                $table->string('error_message')->nullable();
                $table->integer('attempt')->default(1);
                $table->integer('max_attempts')->default(1);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['workflow_instance_id', 'step_name']);
            });
        }

        // 工作流定时器
        if (!Schema::hasTable('workflow_timers')) {
            Schema::create('workflow_timers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
                $table->string('timer_type', 50)->comment('retry/timeout/schedule/reminder');
                $table->timestamp('fire_at');
                $table->json('payload')->nullable();
                $table->boolean('fired')->default(false);
                $table->timestamps();

                $table->index(['fire_at', 'fired']);
                $table->index(['workflow_instance_id', 'timer_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_timers');
        Schema::dropIfExists('workflow_step_executions');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_definitions');
    }
};
