<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 代理商等级定义
        Schema::create('agent_tier_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('level', 30)->unique()->comment('等级标识: regular/silver/gold/platinum');
            $table->string('name')->comment('等级名称');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->decimal('default_rate', 5, 2)->default(0)->comment('默认佣金比例 %');
            $table->json('benefits')->nullable()->comment('权益配置');
            $table->string('color', 20)->nullable()->comment('显示颜色');
            $table->string('icon', 50)->nullable()->comment('图标');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 代理商等级晋升规则
        Schema::create('agent_tier_rules', function (Blueprint $table) {
            $table->id();
            $table->string('from_level', 30)->comment('当前等级');
            $table->string('to_level', 30)->comment('目标等级');
            $table->integer('min_days')->default(0)->comment('最少在册天数');
            $table->integer('min_subscriptions')->default(0)->comment('最少订阅数');
            $table->decimal('min_total_amount', 12, 2)->default(0)->comment('最少累计金额');
            $table->integer('min_referrals')->default(0)->comment('最少推荐客户数');
            $table->decimal('min_monthly_amount', 12, 2)->default(0)->comment('单月最少金额');
            $table->string('period', 20)->default('manual')->comment('auto/manual — 自动/手动晋升');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['from_level', 'to_level']);
        });

        // 代理商等级变更历史
        Schema::create('agent_tier_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('from_level', 30)->nullable();
            $table->string('to_level', 30);
            $table->string('reason', 50)->comment('promotion/demotion/manual/auto');
            $table->text('remark')->nullable();
            $table->foreignId('operated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 给 Agent 表追加字段
        Schema::table('agents', function (Blueprint $table) {
            $table->integer('tier_subscriptions_total')->default(0)->after('level')->comment('累计贡献订阅数');
            $table->decimal('tier_revenue_total', 14, 2)->default(0)->after('tier_subscriptions_total')->comment('累计贡献金额');
            $table->integer('tier_referrals_total')->default(0)->after('tier_revenue_total')->comment('累计推荐客户数');
            $table->decimal('tier_monthly_revenue', 14, 2)->default(0)->after('tier_referrals_total')->comment('本月贡献金额');
            $table->timestamp('tier_last_promoted_at')->nullable()->after('tier_monthly_revenue')->comment('上次晋升时间');
            $table->timestamp('tier_next_review_at')->nullable()->after('tier_last_promoted_at')->comment('下次等级评估时间');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'tier_subscriptions_total',
                'tier_revenue_total',
                'tier_referrals_total',
                'tier_monthly_revenue',
                'tier_last_promoted_at',
                'tier_next_review_at',
            ]);
        });
        Schema::dropIfExists('agent_tier_histories');
        Schema::dropIfExists('agent_tier_rules');
        Schema::dropIfExists('agent_tier_definitions');
    }
};
