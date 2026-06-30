<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_system_registries')) {
            return;
        }
        // 1. AI 系统清单
        Schema::create('ai_system_registries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('系统/模型名称');
            $table->string('version', 50)->comment('版本号');
            $table->text('purpose')->comment('用途说明');
            $table->string('provider', 200)->nullable()->comment('供应商/模型来源');
            $table->string('deployment_status', 20)->default('development')->comment('development/staging/production/retired');
            $table->string('risk_level', 20)->default('low')->comment('low/medium/high/critical');
            $table->text('description')->nullable()->comment('详细描述');
            $table->string('owner_department', 100)->nullable()->comment('负责部门');
            $table->string('owner_email', 200)->nullable()->comment('责任人邮箱');
            $table->json('capabilities')->nullable()->comment('能力列表');
            $table->json('limitations')->nullable()->comment('已知限制');
            $table->json('tags')->nullable()->comment('标签');
            $table->timestamp('last_reviewed_at')->nullable()->comment('上次评审时间');
            $table->timestamp('next_review_at')->nullable()->comment('下次评审截止');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. AI 风险影响评估
        Schema::create('ai_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_system_id')->constrained('ai_system_registries')->cascadeOnDelete();
            $table->string('assessor_name', 100)->comment('评估人');
            $table->string('severity', 20)->comment('negligible/minor/moderate/major/critical');
            $table->decimal('likelihood_score', 4, 2)->default(0)->comment('可能性评分 0-1');
            $table->decimal('impact_score', 4, 2)->default(0)->comment('影响评分 0-1');
            $table->decimal('risk_score', 6, 2)->default(0)->comment('风险值 = 可能性 × 影响');
            $table->json('impact_analysis')->nullable()->comment('各维度影响分析');
            $table->text('mitigation_measures')->nullable()->comment('缓解措施');
            $table->text('residual_risk')->nullable()->comment('残余风险说明');
            $table->string('status', 20)->default('draft')->comment('draft/completed/superseded');
            $table->json('attachments')->nullable()->comment('附件');
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();
        });

        // 3. AI 决策审计日志 (独立存储)
        Schema::create('ai_decision_logs', function (Blueprint $table) {
            $table->id();
            $table->string('decision_id', 64)->unique()->comment('决策唯一ID');
            $table->foreignId('ai_system_id')->nullable()->constrained('ai_system_registries')->nullOnDelete();
            $table->string('model_name', 200)->nullable()->comment('使用的AI模型');
            $table->string('decision_type', 100)->comment('决策类型');
            $table->text('input_summary')->nullable()->comment('输入摘要');
            $table->text('output_summary')->nullable()->comment('输出摘要');
            $table->json('full_input')->nullable()->comment('完整输入(加密)');
            $table->json('full_output')->nullable()->comment('完整输出(加密)');
            $table->decimal('confidence_score', 5, 2)->nullable()->comment('置信度');
            $table->boolean('was_overridden')->default(false)->comment('是否被人工覆盖');
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('overridden_at')->nullable();
            $table->string('override_reason')->nullable()->comment('覆盖原因');
            $table->string('customer_id', 64)->nullable()->comment('受影响的客户ID');
            $table->string('tenant_id', 64)->nullable()->comment('租户ID');
            $table->string('result', 20)->default('approved')->comment('approved/rejected/flagged');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('disclosure_shown')->default(false)->comment('是否展示了透明度披露');
            $table->timestamp('occurred_at')->index()->comment('决策时间');
            $table->timestamps();

            $table->index(['ai_system_id', 'occurred_at']);
            $table->index('customer_id');
        });

        // 4. AI 偏见检测
        Schema::create('ai_bias_detections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_system_id')->constrained('ai_system_registries')->cascadeOnDelete();
            $table->string('metric', 50)->comment('偏见指标');
            $table->decimal('score', 6, 4)->comment('指标值 0-1');
            $table->decimal('threshold', 6, 4)->default(0.1)->comment('阈值');
            $table->boolean('flagged')->default(false)->comment('是否标记异常');
            $table->string('severity', 20)->nullable()->comment('warning/critical');
            $table->text('description')->nullable()->comment('检测描述');
            $table->json('segment_data')->nullable()->comment('分群数据');
            $table->text('mitigation_action')->nullable()->comment('缓解措施');
            $table->string('status', 20)->default('open')->comment('open/mitigated/resolved');
            $table->timestamp('detected_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // 5. AI 训练数据来源记录
        Schema::create('ai_training_data_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_system_id')->constrained('ai_system_registries')->cascadeOnDelete();
            $table->string('source_name', 200)->comment('数据来源名称');
            $table->string('source_type', 50)->comment('public/internal/third_party/synthetic');
            $table->text('description')->nullable()->comment('来源描述');
            $table->string('collection_method', 100)->nullable()->comment('收集方式');
            $table->boolean('has_pii')->default(false)->comment('是否含个人可识别信息');
            $table->boolean('has_sensitive_data')->default(false)->comment('是否含敏感数据');
            $table->string('license', 200)->nullable()->comment('数据许可协议');
            $table->bigInteger('record_count')->nullable()->comment('记录数');
            $table->string('date_range_start', 20)->nullable()->comment('数据起始时间');
            $table->string('date_range_end', 20)->nullable()->comment('数据结束时间');
            $table->text('preprocessing')->nullable()->comment('预处理说明');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 6. 人工申诉/Override
        Schema::create('ai_override_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 64)->unique()->comment('申诉编号');
            $table->foreignId('ai_decision_log_id')->nullable()->constrained('ai_decision_logs')->nullOnDelete();
            $table->string('customer_identifier', 200)->comment('客户标识');
            $table->string('customer_email', 200)->nullable()->comment('客户邮箱');
            $table->text('reason')->comment('申诉理由');
            $table->string('status', 20)->default('pending')->comment('pending/in_review/resolved/rejected');
            $table->string('assigned_to', 100)->nullable()->comment('处理人');
            $table->string('escalation_level', 30)->default('first_line');
            $table->text('resolution_notes')->nullable()->comment('处理备注');
            $table->string('final_decision', 20)->nullable()->comment('override/uphold/partially');
            $table->timestamp('submitted_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // 7. 透明度披露记录
        Schema::create('ai_transparency_disclosures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_system_id')->constrained('ai_system_registries')->cascadeOnDelete();
            $table->string('locale', 10)->default('zh_CN')->comment('语言');
            $table->text('disclosure_text')->comment('披露文本');
            $table->string('disclosure_type', 30)->default('decision')->comment('decision/batch/general');
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_transparency_disclosures');
        Schema::dropIfExists('ai_override_requests');
        Schema::dropIfExists('ai_training_data_sources');
        Schema::dropIfExists('ai_bias_detections');
        Schema::dropIfExists('ai_decision_logs');
        Schema::dropIfExists('ai_risk_assessments');
        Schema::dropIfExists('ai_system_registries');
    }
};
