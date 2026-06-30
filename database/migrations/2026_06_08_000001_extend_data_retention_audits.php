<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 扩展 data_retention_audits 表：支持更多数据源类型
        Schema::table('data_retention_audits', function (Blueprint $table) {
            $table->string('data_source', 50)->default('audit_log')->after('type')
                ->comment('数据源: audit_log / apm_request / webhook_event / webhook_delivery / license / api_endpoint');
        });

        // 创建数据保留策略配置表（扩展，支持所有数据源类型）
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('data_source', 50)->unique()->comment('数据源标识');
            $table->string('display_name', 100)->comment('显示名称');
            $table->integer('retention_days')->default(365)->comment('保留天数');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false)->comment('系统预置，不可删除');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 创建清理调度配置表
        Schema::create('cleanup_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('data_source', 50)->unique()->comment('数据源');
            $table->string('frequency', 20)->default('daily')->comment('daily/weekly/monthly/manual');
            $table->string('time_of_day', 5)->default('02:00')->comment('执行时间');
            $table->string('day_of_week', 10)->default('0')->comment('每周几执行 (0=周日)');
            $table->integer('batch_size')->default(1000)->comment('每批处理条数');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();
        });

        // 创建合规报告导出记录表
        Schema::create('compliance_report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_report_id')->constrained('compliance_reports')->cascadeOnDelete();
            $table->string('format', 20)->comment('pdf/csv/json');
            $table->string('status', 20)->default('pending')->comment('pending/processing/completed/failed');
            $table->string('file_path')->nullable()->comment('导出文件路径');
            $table->integer('file_size')->nullable()->comment('文件大小(bytes)');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_report_exports');
        Schema::dropIfExists('cleanup_schedules');
        Schema::dropIfExists('retention_policies');

        Schema::table('data_retention_audits', function (Blueprint $table) {
            $table->dropColumn('data_source');
        });
    }
};
