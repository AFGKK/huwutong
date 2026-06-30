<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 自定义报表定义
        Schema::create('custom_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200)->comment('报表名称');
            $table->string('slug', 100)->nullable()->comment('URL 标识');
            $table->text('description')->nullable();
            $table->string('category', 50)->default('custom')->comment('分类: financial/license/customer/audit/custom');
            $table->string('data_source', 50)->comment('数据源: subscriptions/invoices/licenses/customers/activations/churn');
            $table->json('metrics')->nullable()->comment('指标配置');
            $table->json('dimensions')->nullable()->comment('维度配置');
            $table->json('filters')->nullable()->comment('过滤条件');
            $table->json('sorts')->nullable()->comment('排序配置');
            $table->string('chart_type', 50)->default('table')->comment('图表类型: table/bar/line/pie/area/radar/number');
            $table->json('chart_options')->nullable()->comment('图表选项');
            $table->boolean('is_template')->default(false)->comment('是否为模板');
            $table->boolean('is_shared')->default(false)->comment('是否共享');
            $table->boolean('is_scheduled')->default(false)->comment('是否定时导出');
            $table->string('schedule_cron', 100)->nullable()->comment('Cron 表达式');
            $table->json('schedule_recipients')->nullable()->comment('定时接收人');
            $table->string('export_format', 10)->default('csv')->comment('导出格式: csv/xlsx/pdf');
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category']);
            $table->index(['tenant_id', 'is_template']);
        });

        // 报表快照（历史生成记录）
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('custom_reports')->cascadeOnDelete();
            $table->string('status', 30)->default('pending')->comment('pending/generating/completed/failed');
            $table->json('snapshot_data')->nullable()->comment('快照数据');
            $table->json('summary')->nullable()->comment('摘要统计');
            $table->integer('row_count')->default(0);
            $table->string('file_path', 500)->nullable()->comment('导出文件路径');
            $table->string('file_format', 10)->nullable();
            $table->integer('file_size')->nullable()->comment('文件大小(bytes)');
            $table->timestamp('generated_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['report_id', 'created_at']);
            $table->index(['report_id', 'status']);
        });

        // 报表仪表盘/看板
        Schema::create('report_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200)->comment('看板名称');
            $table->text('description')->nullable();
            $table->json('layout')->nullable()->comment('布局配置 {widgets: [{report_id, x, y, w, h}]}');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_dashboards');
        Schema::dropIfExists('report_snapshots');
        Schema::dropIfExists('custom_reports');
    }
};
