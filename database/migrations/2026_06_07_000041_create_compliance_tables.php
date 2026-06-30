<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 合规报告框架
        Schema::create('compliance_frameworks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('框架编码: SOC2, GDPR, HIPAA, PCI_DSS, ISO27001');
            $table->string('name', 200)->comment('框架名称');
            $table->text('description')->nullable()->comment('框架描述');
            $table->json('control_domains')->nullable()->comment('控制域列表');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 合规报告
        Schema::create('compliance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('framework_id')->constrained('compliance_frameworks')->cascadeOnDelete();
            $table->string('title', 200)->comment('报告标题');
            $table->string('type', 50)->default('scheduled')->comment('类型: scheduled/on_demand/continuous');
            $table->string('status', 50)->default('draft')->comment('draft/generated/failed/archived');
            $table->date('period_start')->comment('报告期开始');
            $table->date('period_end')->comment('报告期结束');
            $table->json('controls_assessed')->nullable()->comment('评估的控制项');
            $table->json('findings')->nullable()->comment('发现项');
            $table->json('evidence_refs')->nullable()->comment('证据引用');
            $table->text('summary')->nullable()->comment('合规摘要');
            $table->string('risk_level', 20)->nullable()->comment('风险等级: low/medium/high/critical');
            $table->integer('passed_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('na_count')->default(0);
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        // 审计日志标签
        Schema::create('audit_log_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('标签名称');
            $table->string('color', 20)->default('#409EFF')->comment('标签颜色');
            $table->timestamps();
        });

        // 审计日志与标签的多对多关系
        Schema::create('audit_log_tag_log', function (Blueprint $table) {
            $table->foreignId('log_id')->constrained('logs')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('audit_log_tags')->cascadeOnDelete();
            $table->primary(['log_id', 'tag_id']);
        });

        // 审计日志备注/注释
        Schema::create('audit_log_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_id')->constrained('logs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content')->comment('备注内容');
            $table->timestamps();
        });

        // 审计日志批量操作记录
        Schema::create('audit_batch_operations', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type', 50)->comment('操作类型: export/archive/delete/tag/annotate');
            $table->json('log_ids')->comment('操作的日志ID列表');
            $table->json('params')->nullable()->comment('操作参数');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->comment('pending/processing/completed/failed');
            $table->text('result_message')->nullable();
            $table->timestamps();
        });

        // 数据保留审计记录
        Schema::create('data_retention_audits', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->comment('日志类型: audit/security/error/system');
            $table->integer('retention_days')->comment('配置保留天数');
            $table->integer('total_logs_before')->comment('清理前日志数');
            $table->integer('pruned_count')->default(0)->comment('清理数量');
            $table->integer('total_logs_after')->comment('清理后日志数');
            $table->string('status', 20)->default('completed')->comment('completed/partial/failed');
            $table->text('notes')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_audits');
        Schema::dropIfExists('audit_batch_operations');
        Schema::dropIfExists('audit_log_annotations');
        Schema::dropIfExists('audit_log_tag_log');
        Schema::dropIfExists('audit_log_tags');
        Schema::dropIfExists('compliance_reports');
        Schema::dropIfExists('compliance_frameworks');
    }
};
