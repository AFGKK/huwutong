<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 审计导出任务 ───
        Schema::create('audit_export_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200)->comment('导出任务名称');
            $table->string('format', 10)->default('csv')->comment('csv/json/xlsx/pdf');
            $table->json('filters')->nullable()->comment('筛选条件快照');
            $table->string('status', 30)->default('pending')
                ->comment('pending/processing/completed/failed/cancelled');
            $table->unsignedInteger('total_records')->default(0)->comment('总记录数');
            $table->unsignedInteger('exported_records')->default(0)->comment('已导出记录数');
            $table->unsignedInteger('file_size_bytes')->default(0)->comment('文件大小');
            $table->string('file_path', 1000)->nullable()->comment('存储路径');
            $table->string('file_name', 500)->nullable()->comment('原始文件名');
            $table->string('disk', 50)->default('local')->comment('存储磁盘');
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->comment('文件过期时间');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });

        // ─── 定时导出计划 ───
        Schema::create('audit_export_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200)->comment('计划名称');
            $table->string('cron_expression', 100)->comment('Cron 表达式');
            $table->string('format', 10)->default('csv');
            $table->json('filters')->nullable()->comment('筛选条件');
            $table->json('notification_emails')->nullable()->comment('通知邮箱列表');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('max_records')->default(50000)->comment('最大导出记录数');
            $table->string('compression', 20)->default('none')->comment('none/gzip/zip');
            $table->text('description')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('next_run_at');
        });

        // ─── 归档策略 ───
        Schema::create('audit_archive_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('策略名称');
            $table->string('type', 30)->unique()->comment('audit/security/error/system');
            $table->unsignedInteger('archive_after_days')->default(90)->comment('超过此天数自动归档');
            $table->unsignedInteger('delete_after_days')->default(365)->comment('超过此天数自动删除');
            $table->string('archive_disk', 50)->default('local')->comment('归档存储磁盘');
            $table->boolean('compress_archive')->default(true)->comment('是否压缩归档');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->unsignedInteger('execution_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamps();
        });

        // ─── 归档记录 ───
        Schema::create('audit_archive_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('audit_archive_policies')->cascadeOnDelete();
            $table->string('type', 30)->comment('归档类型');
            $table->string('status', 30)->default('pending')
                ->comment('pending/processing/completed/failed');
            $table->unsignedInteger('total_logs')->default(0);
            $table->unsignedInteger('archived_logs')->default(0);
            $table->unsignedInteger('deleted_logs')->default(0);
            $table->string('archive_file', 1000)->nullable()->comment('归档文件路径');
            $table->unsignedInteger('file_size_bytes')->default(0);
            $table->date('archive_date_from')->nullable()->comment('归档数据起始日期');
            $table->date('archive_date_to')->nullable()->comment('归档数据结束日期');
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['policy_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_archive_records');
        Schema::dropIfExists('audit_archive_policies');
        Schema::dropIfExists('audit_export_schedules');
        Schema::dropIfExists('audit_export_tasks');
    }
};
