<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 批量操作任务
        Schema::create('batch_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50)->comment('batch_activate / batch_renew / batch_export / batch_suspend / batch_revoke / batch_delete / batch_change_plan');
            $table->string('target_model', 50)->comment('licenses / subscriptions / customers / invoices / tickets');
            $table->json('filters')->nullable()->comment('筛选条件快照');
            $table->json('ids')->nullable()->comment('手动指定 ID 列表');
            $table->json('params')->nullable()->comment('操作参数（如续费天数、新状态等）');
            $table->unsignedInteger('total_count')->default(0)->comment('涉及总数');
            $table->unsignedInteger('success_count')->default(0)->comment('成功数');
            $table->unsignedInteger('fail_count')->default(0)->comment('失败数');
            $table->string('status', 20)->default('pending')->comment('pending / processing / completed / failed / cancelled');
            $table->string('error_summary')->nullable()->comment('错误摘要');
            $table->json('result_summary')->nullable()->comment('操作结果统计');
            $table->string('export_path')->nullable()->comment('导出文件路径');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['tenant_id', 'status', 'created_at']);
        });

        // 批量操作子项详情
        Schema::create('batch_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_job_id')->constrained()->cascadeOnDelete();
            $table->morphs('targetable');
            $table->string('status', 20)->default('pending')->comment('pending / success / failed / skipped');
            $table->text('error_message')->nullable();
            $table->json('result_data')->nullable()->comment('操作结果快照');
            $table->timestamps();

            $table->index(['batch_job_id', 'status']);
            $table->index(['batch_job_id', 'targetable_type', 'targetable_id']);
        });

        // 可撤销操作的快照（用于撤销回滚）
        Schema::create('batch_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_job_id')->constrained()->cascadeOnDelete();
            $table->morphs('targetable');
            $table->string('field', 100)->comment('修改的字段名');
            $table->text('old_value')->nullable()->comment('修改前的值');
            $table->text('new_value')->nullable()->comment('修改后的值');
            $table->timestamps();

            $table->index(['batch_job_id', 'targetable_type', 'targetable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_snapshots');
        Schema::dropIfExists('batch_job_items');
        Schema::dropIfExists('batch_jobs');
    }
};
