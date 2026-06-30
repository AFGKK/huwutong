<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 客户健康度评分记录
        Schema::create('health_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2)->comment('综合健康分 0.00 ~ 100.00');
            $table->string('grade', 10)->comment('等级: healthy/warning/critical');
            $table->decimal('activation_score', 5, 2)->default(0)->comment('激活活跃度得分');
            $table->decimal('renewal_score', 5, 2)->default(0)->comment('续费健康度得分');
            $table->decimal('ticket_score', 5, 2)->default(0)->comment('工单体验得分');
            $table->decimal('device_score', 5, 2)->default(0)->comment('设备安全得分');
            $table->decimal('payment_score', 5, 2)->default(0)->comment('支付健康度得分');
            $table->json('factors')->nullable()->comment('评分因子明细（各维度原始数据快照）');
            $table->json('warnings')->nullable()->comment('风险预警项列表');
            $table->json('suggestions')->nullable()->comment('主动干预建议');
            $table->timestamp('calculated_at')->index()->comment('评分计算时间');
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id', 'calculated_at']);
            $table->index(['tenant_id', 'score']);
            $table->index(['tenant_id', 'grade']);
        });

        // 健康度变化趋势（用于报表/趋势图）
        Schema::create('health_score_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 5, 2);
            $table->string('grade', 10);
            $table->json('factors')->nullable();
            $table->timestamp('calculated_at')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id', 'calculated_at']);
        });

        // 流失预警记录
        // 注意：此表在 2026_06_07_000011_create_crm_tables 中被更完整的版本覆盖，此处跳过避免冲突
        if (!Schema::hasTable('churn_predictions')) {
            Schema::create('churn_predictions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->decimal('churn_probability', 5, 4)->comment('流失概率 0.0000 ~ 1.0000');
                $table->string('risk_level', 20)->comment('low / medium / high / critical');
                $table->json('top_signals')->nullable()->comment('主要流失信号');
                $table->json('recommendations')->nullable()->comment('干预建议');
                $table->timestamp('predicted_at')->index();
                $table->timestamps();

                $table->index(['tenant_id', 'customer_id', 'predicted_at']);
                $table->index(['tenant_id', 'risk_level']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('churn_predictions');
        Schema::dropIfExists('health_score_history');
        Schema::dropIfExists('health_scores');
    }
};
