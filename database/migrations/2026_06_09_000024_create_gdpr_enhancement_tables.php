<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. DPIA — 数据保护影响评估
        if (!Schema::hasTable('dpia_records')) {
            Schema::create('dpia_records', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('reference')->unique()->comment('唯一编号 e.g. DPIA-2026-001');
                $table->string('status', 30)->default('draft')->comment('draft/in_review/approved/rejected');
                $table->string('processing_type', 50)->comment('数据处理类型: customer_data/usage_analytics/payment/fraud_detection/...');
                $table->text('description')->nullable()->comment('处理活动描述');
                $table->json('data_categories')->nullable()->comment('涉及的数据类别');
                $table->json('data_subjects')->nullable()->comment('数据主体类型');
                $table->json('processing_purposes')->nullable()->comment('处理目的');
                $table->text('necessity_assessment')->nullable()->comment('必要性评估');
                $table->text('proportionality_assessment')->nullable()->comment('相称性评估');
                $table->json('risks')->nullable()->comment('[{risk, likelihood, severity, mitigations}]');
                $table->json('risk_level')->nullable()->comment('{overall, likelihood, severity}');
                $table->text('mitigation_measures')->nullable()->comment('缓解措施说明');
                $table->string('controller_dpo', 100)->nullable()->comment('DPO 联系人');
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users');
                $table->text('review_notes')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. 数据泄露通知
        if (!Schema::hasTable('data_breach_notifications')) {
            Schema::create('data_breach_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique()->comment('唯一编号 e.g. BR-2026-001');
                $table->string('status', 30)->default('detected')->comment('detected/assessing/reported/sa_resolved/closed');
                $table->string('severity', 20)->default('medium')->comment('critical/high/medium/low');
                $table->timestamp('detected_at');
                $table->timestamp('contained_at')->nullable();
                $table->text('description')->comment('泄露描述');
                $table->text('root_cause')->nullable()->comment('根本原因');
                $table->text('impact_assessment')->nullable()->comment('影响评估');
                $table->json('affected_data_categories')->nullable();
                $table->integer('affected_users_count')->nullable();
                $table->text('containment_actions')->nullable()->comment('遏制措施');
                $table->boolean('notified_supervisory_authority')->default(false);
                $table->timestamp('authority_notified_at')->nullable();
                $table->text('authority_response')->nullable();
                $table->boolean('notified_affected_users')->default(false);
                $table->timestamp('users_notified_at')->nullable();
                $table->text('remediation_plan')->nullable()->comment('整改计划');
                $table->timestamp('remediated_at')->nullable();
                $table->json('evidence_refs')->nullable()->comment('证据引用');
                $table->foreignId('reported_by')->nullable()->constrained('users');
                $table->foreignId('assigned_to')->nullable()->constrained('users');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 3. 处理活动记录 (ROPA)
        if (!Schema::hasTable('processing_activity_records')) {
            Schema::create('processing_activity_records', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique()->comment('唯一编号 e.g. ROPA-2026-001');
                $table->string('status', 20)->default('active')->comment('active/archived');
                $table->string('controller_name')->comment('数据控制者名称');
                $table->string('controller_contact')->nullable();
                $table->string('controller_dpo')->nullable();
                $table->string('processing_type', 50)->comment('处理类型');
                $table->text('processing_description')->comment('处理活动描述');
                $table->json('processing_purposes')->comment('处理目的');
                $table->json('data_categories')->comment('个人数据类别');
                $table->json('data_subjects')->comment('数据主体');
                $table->json('recipients')->nullable()->comment('接收者类别');
                $table->json('transfers')->nullable()->comment('第三国传输: [{country, safeguard, mechanism}]');
                $table->text('retention_period')->nullable()->comment('保留期限说明');
                $table->json('technical_measures')->nullable()->comment('技术安全措施');
                $table->json('organizational_measures')->nullable()->comment('组织安全措施');
                $table->boolean('has_dpia')->default(false);
                $table->foreignId('dpia_id')->nullable()->constrained('dpia_records');
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 4. 子处理商评估
        if (!Schema::hasTable('sub_processor_assessments')) {
            Schema::create('sub_processor_assessments', function (Blueprint $table) {
                $table->id();
                $table->string('name')->comment('子处理商名称');
                $table->string('contact_email')->nullable();
                $table->string('jurisdiction', 100)->comment('所在司法管辖区');
                $table->text('processing_description')->comment('处理活动描述');
                $table->json('data_categories')->nullable()->comment('可访问的数据类别');
                $table->string('status', 20)->default('pending')->comment('pending/approved/rejected/terminated');
                $table->text('security_assessment')->nullable()->comment('安全评估结果');
                $table->string('certification', 100)->nullable()->comment('认证 e.g. SOC2/ISO27001');
                $table->boolean('has_dpa_signed')->default(false);
                $table->timestamp('dpa_signed_at')->nullable();
                $table->json('safeguards')->nullable()->comment('传输保障措施');
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 5. 自动决策/画像记录 (Art.22)
        if (!Schema::hasTable('automated_decision_records')) {
            Schema::create('automated_decision_records', function (Blueprint $table) {
                $table->id();
                $table->string('name')->comment('决策/画像名称');
                $table->string('type', 30)->comment('automated_decision/profiling');
                $table->text('description')->comment('逻辑描述');
                $table->json('input_data_categories')->comment('输入数据');
                $table->json('output_decision')->comment('输出决策');
                $table->text('logic_explanation')->nullable()->comment('逻辑解释 (可解释性)');
                $table->text('significance')->nullable()->comment('对数据主体的影响');
                $table->boolean('human_intervention_possible')->default(false);
                $table->string('intervention_method', 100)->nullable()->comment('人工干预方式');
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_reviewed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automated_decision_records');
        Schema::dropIfExists('sub_processor_assessments');
        Schema::dropIfExists('processing_activity_records');
        Schema::dropIfExists('data_breach_notifications');
        Schema::dropIfExists('dpia_records');
    }
};
