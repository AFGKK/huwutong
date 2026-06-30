<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 阶梯定价表
        if (!Schema::hasTable('pricing_tiers')) {
            Schema::create('pricing_tiers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pricing_plan_id')->constrained('pricing_plans')->cascadeOnDelete();
                $table->string('name', 100)->comment('阶梯名称');
                $table->unsignedInteger('from_quantity')->default(1)->comment('起始数量');
                $table->unsignedInteger('to_quantity')->nullable()->comment('结束数量（null=无限）');
                $table->decimal('unit_price', 12, 2)->comment('单价');
                $table->decimal('flat_fee', 12, 2)->default(0)->comment('额外固定费用');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['pricing_plan_id', 'from_quantity', 'to_quantity']);
            });
        }

        // 动态定价规则表
        if (!Schema::hasTable('dynamic_pricing_rules')) {
            Schema::create('dynamic_pricing_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name', 200)->comment('规则名称');
                $table->string('slug', 100)->unique()->comment('规则标识');
                $table->string('description')->nullable();

                // 规则类型
                $table->string('rule_type', 30)->comment('规则类型: volume/segment/time_seasonal/time_hourly/promotion/llm_optimized');

                // 适用对象
                $table->string('target_type', 30)->default('plan')->comment('plan/customer/segment/product');
                $table->unsignedBigInteger('target_id')->nullable()->comment('适用对象ID');
                $table->json('target_ids')->nullable()->comment('多对象ID列表');

                // 定价调整方式
                $table->string('adjustment_type', 20)->comment('percentage/fixed/override/formula');
                $table->decimal('adjustment_value', 12, 2)->nullable()->comment('调整值（百分比或固定金额）');
                $table->decimal('min_price', 12, 2)->nullable()->comment('最低价格限制');
                $table->decimal('max_price', 12, 2)->nullable()->comment('最高价格限制');

                // 条件
                $table->json('conditions')->nullable()->comment('触发条件（JSON）');
                $table->json('schedule')->nullable()->comment('时间排期');
                $table->string('timezone', 50)->nullable()->default('UTC');

                // 优先级与叠加
                $table->unsignedSmallInteger('priority')->default(100)->comment('优先级（数字越小越高）');
                $table->string('stack_mode', 20)->default('multiply')->comment('叠加方式: replace/add/multiply/compound');
                $table->json('allowed_stack_with')->nullable()->comment('允许叠加的规则slug列表');

                // 状态与审计
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('applied_count')->default(0)->comment('应用次数统计');
                $table->timestamp('last_applied_at')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['rule_type', 'is_active']);
                $table->index(['target_type', 'target_id']);
                $table->index('priority');
            });
        }

        // 定价规则应用日志
        if (!Schema::hasTable('pricing_rule_application_logs')) {
            Schema::create('pricing_rule_application_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('rule_id')->nullable()->index();
                $table->morphs('appliable');
                $table->string('context_type', 30)->nullable()->comment('应用场景: subscription_create/renewal/plan_change/manual');
                $table->decimal('original_price', 12, 2);
                $table->decimal('final_price', 12, 2);
                $table->decimal('discount_amount', 12, 2);
                $table->json('applied_rules')->nullable()->comment('应用的规则详情');
                $table->timestamps();
            });
        }

        // 给 subscriptions 表添加 pricing_rule_id 字段
        if (!Schema::hasColumn('subscriptions', 'applied_pricing_rules')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->json('applied_pricing_rules')->nullable()->after('metadata')
                    ->comment('应用的动态定价规则记录');
            });
        }

        // 给 pricing_plans 表添加动态定价相关字段
        if (!Schema::hasColumn('pricing_plans', 'enable_dynamic_pricing')) {
            Schema::table('pricing_plans', function (Blueprint $table) {
                $table->boolean('enable_dynamic_pricing')->default(false)
                    ->comment('启用动态定价');
                $table->string('pricing_model', 30)->default('fixed')
                    ->comment('定价模型: fixed/tiered/usage/hybrid');
            });
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('applied_pricing_rules');
        });
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->dropColumn(['enable_dynamic_pricing', 'pricing_model']);
        });
        Schema::dropIfExists('pricing_rule_application_logs');
        Schema::dropIfExists('dynamic_pricing_rules');
        Schema::dropIfExists('pricing_tiers');
    }
};
