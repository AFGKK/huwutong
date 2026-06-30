<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 报表定时调度配置
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('custom_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cron_expression', 100)->comment('Cron 表达式');
            $table->string('export_format', 10)->default('csv')->comment('导出格式: csv/json/xlsx');
            $table->json('recipients')->nullable()->comment('邮件接收人列表 [{email, name}]');
            $table->string('subject', 200)->nullable()->comment('邮件主题模板');
            $table->text('message')->nullable()->comment('邮件正文模板');
            $table->boolean('include_chart')->default(true)->comment('邮件中是否包含图表');
            $table->boolean('is_active')->default(true)->comment('是否启用');
            $table->unsignedInteger('max_retries')->default(3)->comment('最大重试次数');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
            $table->index(['report_id']);
            $table->index(['user_id']);
        });

        // 报表投递日志
        Schema::create('report_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('report_schedules')->cascadeOnDelete();
            $table->foreignId('report_id')->constrained('custom_reports')->cascadeOnDelete();
            $table->foreignId('snapshot_id')->nullable()->constrained('report_snapshots')->nullOnDelete();
            $table->string('status', 30)->default('pending')->comment('pending/processing/completed/failed');
            $table->string('export_format', 10)->nullable()->comment('导出格式');
            $table->string('file_path', 500)->nullable()->comment('导出文件路径');
            $table->unsignedInteger('file_size')->nullable()->comment('文件大小(bytes)');
            $table->json('recipients')->nullable()->comment('本次投递的接收人');
            $table->json('delivery_results')->nullable()->comment('各接收人投递结果');
            $table->unsignedInteger('attempts')->default(0)->comment('尝试次数');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['schedule_id', 'created_at']);
            $table->index(['report_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_delivery_logs');
        Schema::dropIfExists('report_schedules');
    }
};
