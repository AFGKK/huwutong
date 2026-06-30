<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 审计分析汇总表（预聚合） ───
        Schema::create('audit_analysis_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('summary_date');
            $table->string('period', 30)->default('daily')->comment('daily/weekly/monthly');
            $table->string('type', 30)->comment('audit/security/error/system');
            $table->string('action', 100)->nullable()->comment('特定操作');
            $table->unsignedInteger('count')->default(0);
            $table->unsignedInteger('unique_users')->default(0);
            $table->unsignedInteger('unique_ips')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'summary_date', 'period', 'type', 'action'], 'audit_summary_unique');
            $table->index(['tenant_id', 'summary_date']);
            $table->index(['tenant_id', 'type', 'summary_date']);
        });

        // ─── 审计异常检测结果 ───
        Schema::create('audit_anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('anomaly_type', 50)->comment('spike/drop/pattern_change/unusual_hours/geo_anomaly');
            $table->string('severity', 20)->default('warning')->comment('info/warning/critical');
            $table->string('metric', 80)->comment('审计指标名称');
            $table->decimal('baseline_value', 14, 4)->comment('基线值');
            $table->decimal('actual_value', 14, 4)->comment('实际值');
            $table->decimal('deviation', 10, 2)->comment('偏差百分比');
            $table->text('description');
            $table->json('context')->nullable();
            $table->string('status', 20)->default('open')->comment('open/acknowledged/resolved/dismissed');
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'anomaly_type']);
            $table->index(['tenant_id', 'severity', 'status']);
            $table->index('detected_at');
        });

        // ─── 审计仪表板配置 ───
        Schema::create('audit_report_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('report_type', 40)->comment('trend/distribution/top_list/anomaly/custom');
            $table->json('config')->comment('报表配置 {metrics, filters, timeRange, groupBy, chartType}');
            $table->boolean('is_shared')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });

        // ─── 审计操作分类字典 ───
        Schema::create('audit_action_dict', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100)->unique();
            $table->string('category', 50)->comment('license/customer/user/system/auth/device/product/security');
            $table->string('label', 200)->comment('操作显示名');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_action_dict');
        Schema::dropIfExists('audit_report_configs');
        Schema::dropIfExists('audit_anomalies');
        Schema::dropIfExists('audit_analysis_summaries');
    }
};
