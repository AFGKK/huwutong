<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 分层定价规则 ──
        if (Schema::hasTable('metered_tiered_pricings')) { return; }
        Schema::create('metered_tiered_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('metric_key', 100);              // 计量指标，如 api_call
            $table->string('name');                         // 定价名称，如 "API 调用分层定价"
            $table->string('billing_period', 20)->default('monthly'); // monthly / yearly / one_time
            $table->string('tier_type', 20)->default('volume'); // volume(总量) / graduated(梯度)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'metric_key']);
            $table->index(['product_id']);
        });

        // ── 分层定价详情（各阶梯） ──
        if (Schema::hasTable('metered_tier_pricing_tiers')) { return; }
        Schema::create('metered_tier_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiered_pricing_id')->constrained('metered_tiered_pricings')->cascadeOnDelete();
            $table->unsignedInteger('from_unit');            // 起始单位（含）
            $table->unsignedBigInteger('to_unit')->nullable(); // 结束单位（含），null=无限
            $table->decimal('unit_price', 12, 4);            // 单价
            $table->string('price_model', 20)->default('per_unit'); // per_unit(按量) / flat(固定费)
            $table->decimal('flat_fee', 12, 2)->default(0);  // 固定费（当price_model=flat时）
            $table->timestamps();

            $table->index('tiered_pricing_id');
        });

        // ── 超额预警配置 ──
        if (Schema::hasTable('metered_billing_alerts')) { return; }
        Schema::create('metered_billing_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('metric_key', 100);               // 监控指标
            $table->string('name');                          // 预警名称
            $table->decimal('threshold_value', 14, 2);       // 阈值（数量或金额）
            $table->string('threshold_type', 20)->default('quantity'); // quantity / amount / percentage
            $table->decimal('percentage', 5, 2)->nullable();  // 百分比阈值（如达到配额80%时触发）
            $table->string('direction', 10)->default('above'); // above / below
            $table->string('window_type', 20)->default('billing_period'); // billing_period / daily / monthly
            $table->json('notify_channels')->nullable();     // email / sms / webhook
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'metric_key']);
            $table->index(['subscription_id']);
        });

        // ── 预警历史 ──
        if (Schema::hasTable('metered_alert_histories')) { return; }
        Schema::create('metered_alert_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained('metered_billing_alerts')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('metric_key', 100);
            $table->decimal('current_value', 14, 2);         // 触发时的值
            $table->decimal('threshold_value', 14, 2);       // 阈值
            $table->string('channel', 30);                   // email / sms / webhook
            $table->string('status', 20)->default('sent');   // sent / failed / read
            $table->text('message')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamps();

            $table->index(['alert_id', 'triggered_at']);
            $table->index(['tenant_id', 'triggered_at']);
        });

        // ── 自动切换套餐规则 ──
        if (Schema::hasTable('metered_auto_switch_rules')) { return; }
        Schema::create('metered_auto_switch_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');                          // 规则名称
            $table->string('metric_key', 100);               // 计量指标
            $table->string('condition_type', 20)->default('usage_consecutive'); // usage_consecutive / usage_average / spend_threshold
            $table->decimal('condition_value', 14, 2);       // 条件值
            $table->unsignedInteger('condition_days')->default(3); // 持续天数/采样周期
            $table->string('action', 20)->default('upgrade'); // upgrade / downgrade
            $table->string('target_plan_slug');               // 目标套餐 slug
            $table->boolean('require_confirmation')->default(true); // 需要管理员确认
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'metric_key']);
            $table->index(['subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metered_auto_switch_rules');
        Schema::dropIfExists('metered_alert_histories');
        Schema::dropIfExists('metered_billing_alerts');
        Schema::dropIfExists('metered_tier_pricing_tiers');
        Schema::dropIfExists('metered_tiered_pricings');
    }
};
