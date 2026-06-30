<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 退款风控规则表
        if (!Schema::hasTable('refund_risk_rules')) {
            Schema::create('refund_risk_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('name')->comment('规则名称');
                $table->string('rule_type', 30)->comment('time_window|amount_threshold|frequency|customer_tier|license_age');
                $table->json('conditions')->comment('条件配置');
                $table->json('actions')->comment('动作: auto_approve|auto_reject|require_review|partial_refund');
                $table->integer('priority')->default(100)->comment('优先级，数字越小越优先');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'rule_type']);
                $table->index('is_active');
            });
        }

        // 退款风控评估记录表
        if (!Schema::hasTable('refund_risk_assessments')) {
            Schema::create('refund_risk_assessments', function (Blueprint $table) {
                $table->id();
                $table->string('assessable_type', 100);
                $table->unsignedBigInteger('assessable_id');
                $table->integer('risk_score')->default(0)->comment('风险评分 0-100');
                $table->string('risk_level', 20)->default('low')->comment('low|medium|high|critical');
                $table->json('factors')->nullable()->comment('风险因素明细');
                $table->json('matched_rules')->nullable()->comment('触发的规则');
                $table->string('decision', 30)->nullable()->comment('auto_approve|auto_reject|require_review|partial_refund');
                $table->string('review_status', 20)->default('pending')->comment('pending|approved|rejected');
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_note')->nullable();
                $table->timestamps();

                $table->index(['assessable_type', 'assessable_id']);
                $table->index('risk_level');
                $table->index('review_status');
            });
        }

        // 给 refunds 表增加风控相关字段
        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', function (Blueprint $table) {
                if (!Schema::hasColumn('refunds', 'refund_type')) {
                    $table->string('refund_type', 30)->default('full')->after('reason')
                        ->comment('full|partial');
                }
                if (!Schema::hasColumn('refunds', 'risk_assessment_id')) {
                    $table->foreignId('risk_assessment_id')->nullable()->after('payment_method')
                        ->constrained('refund_risk_assessments')->nullOnDelete();
                }
                if (!Schema::hasColumn('refunds', 'auto_decision')) {
                    $table->string('auto_decision', 30)->nullable()->after('risk_assessment_id')
                        ->comment('系统自动决策: auto_approve|auto_reject|require_review');
                }
                if (!Schema::hasColumn('refunds', 'approved_by')) {
                    $table->foreignId('approved_by')->nullable()->after('auto_decision')
                        ->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('refunds', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }
                if (!Schema::hasColumn('refunds', 'failure_reason')) {
                    $table->string('failure_reason', 500)->nullable()->after('completed_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('refunds')) {
            Schema::table('refunds', function (Blueprint $table) {
                $columns = ['refund_type', 'risk_assessment_id', 'auto_decision', 'approved_by', 'approved_at', 'failure_reason'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('refunds', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        Schema::dropIfExists('refund_risk_assessments');
        Schema::dropIfExists('refund_risk_rules');
    }
};
