<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 定价方案表 — 注意：已在 2026_06_06_000012 中创建，此处跳过
        if (!Schema::hasTable('pricing_plans')) {
            Schema::create('pricing_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('slug')->unique()->comment('方案标识: basic, pro, enterprise');
                $table->string('name')->comment('方案名称');
                $table->text('description')->nullable();
                $table->string('currency', 3)->default('CNY');
                $table->decimal('price_monthly', 12, 2)->nullable()->comment('月付价格');
                $table->decimal('price_quarterly', 12, 2)->nullable()->comment('季付价格');
                $table->decimal('price_semi_annually', 12, 2)->nullable()->comment('半年付价格');
                $table->decimal('price_yearly', 12, 2)->nullable()->comment('年付价格');
                $table->json('features')->nullable()->comment('方案功能列表');
                $table->json('limits')->nullable()->comment('方案限制: max_devices, max_activations, api_rate_limit');
                $table->integer('trial_days')->default(0)->comment('试用天数');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_public')->default(true)->comment('是否公开显示');
                $table->boolean('is_active')->default(true);
                $table->string('badge')->nullable()->comment('角标: popular/best_value');
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
                $table->index('slug');
            });
        }

        // 方案价格历史（审计追踪价格变更）
        Schema::create('pricing_plan_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_price_monthly', 12, 2)->nullable();
            $table->decimal('new_price_monthly', 12, 2)->nullable();
            $table->decimal('old_price_yearly', 12, 2)->nullable();
            $table->decimal('new_price_yearly', 12, 2)->nullable();
            $table->string('changed_by', 100)->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamps();
        });

        // 优惠券表
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique()->comment('优惠码');
            $table->string('name')->comment('优惠名称');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'free_trial', 'custom'])->comment('优惠类型');
            $table->decimal('value', 12, 2)->comment('折扣值（百分比或固定金额）');
            $table->string('currency', 3)->nullable()->default('CNY')->comment('固定折扣的币种');
            $table->decimal('minimum_order_amount', 12, 2)->nullable()->comment('最低订单金额');
            $table->decimal('maximum_discount', 12, 2)->nullable()->comment('最高折扣金额');
            $table->unsignedInteger('usage_limit')->nullable()->comment('总使用次数限制');
            $table->unsignedInteger('usage_limit_per_user')->nullable()->comment('每用户使用次数');
            $table->unsignedInteger('usage_count')->default(0);
            $table->json('applicable_plans')->nullable()->comment('适用方案 slug 列表，null=全部');
            $table->json('applicable_products')->nullable()->comment('适用产品 ID 列表');
            $table->json('applicable_billing_periods')->nullable()->comment('适用周期: monthly, yearly 等');
            $table->boolean('is_redeemable_with_other_coupons')->default(false);
            $table->string('status')->default('active')->comment('active/expired/disabled');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('first_redeemed_at')->nullable();
            $table->timestamp('last_redeemed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'expires_at']);
            $table->index('code');
        });

        // 优惠券使用记录
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 12, 2)->comment('实际折扣金额');
            $table->string('currency', 3)->default('CNY');
            $table->decimal('original_amount', 12, 2)->comment('原始金额');
            $table->decimal('final_amount', 12, 2)->comment('折扣后金额');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['coupon_id', 'created_at']);
            $table->index('customer_id');
        });

        // 客户支付方式表
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway', 30)->comment('stripe/alipay/wxpay');
            $table->string('method_type', 30)->comment('credit_card/alipay/wechat');
            $table->string('gateway_method_id')->comment('在网关中的方法ID');
            $table->string('last_four', 4)->nullable()->comment('卡号后四位');
            $table->string('card_brand', 30)->nullable()->comment('Visa/Mastercard');
            $table->string('cardholder_name')->nullable();
            $table->unsignedSmallInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->string('billing_zip')->nullable();
            $table->string('billing_country', 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
            $table->index(['tenant_id', 'gateway']);
        });

        // invoices 增加行项目
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->nullable()->after('amount')->comment('税前小计');
            }
            if (! Schema::hasColumn('invoices', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('subtotal');
                $table->string('coupon_code')->nullable()->after('discount_amount');
                $table->unsignedBigInteger('coupon_id')->nullable()->after('coupon_code');
            }
            if (! Schema::hasColumn('invoices', 'paid')) {
                $table->boolean('paid')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'coupon_code', 'coupon_id', 'paid']);
            // subtotal already exists from tax migration, don't drop
        });
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('pricing_plan_histories');
        Schema::dropIfExists('pricing_plans');
    }
};
