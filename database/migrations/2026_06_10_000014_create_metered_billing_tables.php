<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 用量计费价格阶梯表
        if (!Schema::hasTable('metered_prices')) {
            Schema::create('metered_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('metric_key', 100)->comment('计量指标，如 api_call.validate');
                $table->string('name', 200)->comment('显示名称');
                $table->string('unit', 50)->comment('计价单位，如 count/bytes/tokens');
                $table->string('billing_period', 30)->default('monthly')
                    ->comment('结算周期：monthly|quarterly|yearly');
                $table->json('tiers')->comment('价格阶梯：[{from:0, to:1000, unit_price:0.01}, {from:1001, to:null, unit_price:0.005}]');
                $table->decimal('base_fee', 12, 2)->default(0)->comment('固定基础费');
                $table->decimal('included_quantity', 12, 2)->default(0)->comment('套餐内包含用量');
                $table->decimal('max_quantity', 16, 2)->nullable()->comment('用量上限(null=无限制)');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'metric_key', 'billing_period']);
                $table->index('is_active');
            });
        }

        // 账单行项目表（用量计费明细）
        if (!Schema::hasTable('invoice_line_items')) {
            Schema::create('invoice_line_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('type', 30)->default('metered_usage')
                    ->comment('subscription|metered_usage|one_time|credit');
                $table->string('description', 500);
                $table->string('metric_key', 100)->nullable()->comment('关联计量指标');
                $table->decimal('quantity', 16, 4)->default(1)->comment('数量');
                $table->decimal('unit_price', 12, 4)->default(0)->comment('单价');
                $table->decimal('amount', 14, 2)->comment('金额 = quantity * unit_price');
                $table->string('currency', 10)->default('CNY');
                $table->json('breakdown')->nullable()->comment('费用明细JSON');
                $table->timestamp('period_start')->nullable()->comment('计费周期开始');
                $table->timestamp('period_end')->nullable()->comment('计费周期结束');
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['invoice_id', 'type']);
                $table->index(['tenant_id', 'metric_key', 'period_start']);
            });
        }

        // Subscription 扩展：用量计费配置
        if (Schema::hasTable('subscriptions')) {
            if (!Schema::hasColumn('subscriptions', 'metered_config')) {
                Schema::table('subscriptions', function (Blueprint $table) {
                    $table->json('metered_config')->nullable()
                        ->after('metadata')
                        ->comment('用量计费配置：{enabled: true, billing_period: monthly, cap_type: hard|soft, monthly_cap: 10000}');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions')) {
            if (Schema::hasColumn('subscriptions', 'metered_config')) {
                Schema::table('subscriptions', function (Blueprint $table) {
                    $table->dropColumn('metered_config');
                });
            }
        }
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('metered_prices');
    }
};
