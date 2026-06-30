<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 客户分群定义
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            // 规则条件 (JSON): 例如 {"type": "enterprise", "min_subscriptions": 1, "min_total_paid": 1000}
            $table->json('rules')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_dynamic')->default(true); // true=动态计算, false=手动分配
            $table->boolean('is_active')->default(true);
            $table->integer('member_count')->default(0);
            $table->timestamps();
        });

        // 客户-分群关联
        Schema::create('customer_segment_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_segment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->unique(['customer_segment_id', 'customer_id']);
        });

        // RFM 评分
        Schema::create('rfm_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Recency: 最近一次购买距今天数
            $table->integer('recency_days')->nullable();
            $table->tinyInteger('recency_score')->nullable(); // 1-5
            // Frequency: 购买次数 (发票/订阅数)
            $table->integer('frequency_count')->default(0);
            $table->tinyInteger('frequency_score')->nullable(); // 1-5
            // Monetary: 总消费金额
            $table->decimal('monetary_total', 12, 2)->default(0);
            $table->tinyInteger('monetary_score')->nullable(); // 1-5
            // RFM 总分 (R+F+M 或加权)
            $table->tinyInteger('rfm_total')->nullable();
            $table->string('rfm_segment', 30)->nullable(); // Champions, Loyal, etc.
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique('customer_id');
        });

        // 流失预测 — 注意：已在 2026_06_06_000013 中创建，此处跳过
        if (!Schema::hasTable('churn_predictions')) {
            Schema::create('churn_predictions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->tinyInteger('churn_score')->nullable();
                $table->string('churn_risk', 20)->nullable();
                $table->date('predicted_churn_date')->nullable();
                $table->json('signals')->nullable();
                $table->text('recommended_action')->nullable();
                $table->timestamp('predicted_at')->nullable();
                $table->timestamps();

                $table->unique('customer_id');
                $table->index('churn_risk');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('churn_predictions');
        Schema::dropIfExists('rfm_scores');
        Schema::dropIfExists('customer_segment_members');
        Schema::dropIfExists('customer_segments');
    }
};
