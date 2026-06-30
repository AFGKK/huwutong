<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 满减/满折促销规则表
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name')->comment('规则名称');
            $table->string('slug')->unique()->comment('唯一标识');
            $table->string('type', 30)->comment('类型: amount_off/percent_off/buy_x_get_y/fixed_price');
            $table->text('description')->nullable();

            // 条件：触发门槛
            $table->string('condition_type', 30)->default('subtotal')->comment('门槛类型: subtotal/quantity/items_count');
            $table->decimal('condition_value', 12, 2)->default(0)->comment('门槛值');

            // 折扣定义
            $table->decimal('discount_value', 12, 2)->default(0)->comment('折扣值（金额或百分比）');
            $table->decimal('max_discount', 12, 2)->nullable()->comment('最大折扣金额（百分比折扣时）');
            $table->decimal('min_order_amount', 12, 2)->default(0)->comment('最低订单金额');

            // 适用范围
            $table->json('applicable_products')->nullable()->comment('适用商品ID列表，null=全部');
            $table->json('applicable_categories')->nullable()->comment('适用分类ID列表');
            $table->json('excluded_products')->nullable()->comment('排除商品ID列表');

            // 叠加规则
            $table->boolean('stackable_with_coupon')->default(false)->comment('是否可与优惠券叠加');
            $table->boolean('stackable_with_other_rules')->default(false)->comment('是否可与其他规则叠加');
            $table->integer('priority')->default(0)->comment('优先级（数字越小越先执行）');

            // 使用限制
            $table->integer('usage_limit')->nullable()->comment('总使用次数限制');
            $table->integer('usage_limit_per_customer')->nullable()->comment('每客户使用次数');
            $table->integer('usage_count')->default(0)->comment('已使用次数');
            $table->decimal('budget', 14, 2)->nullable()->comment('预算总额');
            $table->decimal('budget_spent', 14, 2)->default(0)->comment('已使用预算');

            // 时间范围
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // 多级满减（JSON 数组）
            $table->json('tiers')->nullable()->comment('多级阶梯：[{from, to, type, value}]');

            // 买N送N
            $table->integer('buy_quantity')->nullable()->comment('买N件');
            $table->integer('free_quantity')->nullable()->comment('送N件');
            $table->json('free_products')->nullable()->comment('赠送商品列表（null=任意）');

            $table->string('status', 20)->default('draft')->comment('draft/active/paused/expired');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
        });

        // 促销使用记录
        Schema::create('promotion_rule_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('original_amount', 12, 2)->comment('原始金额');
            $table->decimal('discount_amount', 12, 2)->comment('折扣金额');
            $table->decimal('final_amount', 12, 2)->comment('最终金额');
            $table->string('currency', 10)->default('CNY');
            $table->json('tier_applied')->nullable()->comment('应用的多级阶梯信息');
            $table->json('context')->nullable()->comment('上下文信息');
            $table->timestamps();

            $table->index(['promotion_rule_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_rule_redemptions');
        Schema::dropIfExists('promotion_rules');
    }
};
