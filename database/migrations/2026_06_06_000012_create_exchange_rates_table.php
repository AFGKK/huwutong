<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 汇率表
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 16, 8);
            $table->string('provider', 30)->nullable();       // manual / ecb / alipay / stripe
            $table->timestamp('effective_at')->nullable();      // 生效时间
            $table->timestamp('expires_at')->nullable();        // 过期时间
            $table->timestamps();

            $table->unique(['tenant_id', 'from_currency', 'to_currency', 'effective_at'], 'exchange_rate_unique');
            $table->index(['from_currency', 'to_currency']);
        });

        // 多币种定价表（替代 Product 上单薄的 price 字段）
        // 注意：此表在 2026_06_07_000002 中被更完整的方案覆盖，此处跳过避免冲突
        if (!Schema::hasTable('pricing_plans')) {
            Schema::create('pricing_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
                $table->string('slug', 100)->unique();
                $table->string('name', 200);
                $table->text('description')->nullable();
                $table->string('billing_period', 30);         // monthly / yearly / one_time
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 定价计划 × 货币维度的价格表
        if (!Schema::hasTable('pricing_plan_prices')) {
            Schema::create('pricing_plan_prices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pricing_plan_id')->constrained()->cascadeOnDelete();
                $table->string('currency', 3);
                $table->decimal('price', 12, 2);
                $table->decimal('setup_fee', 12, 2)->default(0);
                $table->decimal('trial_price', 12, 2)->nullable();
                $table->timestamps();
                $table->unique(['pricing_plan_id', 'currency']);
            });
        }

        // 客户首选货币设置
        Schema::create('customer_currency_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('preferred_currency', 3)->default('CNY');
            $table->string('display_currency', 3)->default('CNY');
            $table->json('accepted_currencies')->nullable();    // ['CNY','USD','EUR']
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_currency_preferences');
        Schema::dropIfExists('pricing_plan_prices');
        Schema::dropIfExists('pricing_plans');
        Schema::dropIfExists('exchange_rates');
    }
};
