<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('配置名称');
            $table->string('description')->nullable()->comment('描述');
            $table->boolean('is_active')->default(true)->comment('是否启用');

            // 重试策略
            $table->integer('max_attempts')->default(5)->comment('最大重试次数');
            $table->json('retry_intervals_days')->comment('各次重试间隔（天）')->nullable();
            $table->integer('downgrade_after_attempt')->default(3)->comment('第N次失败后降级');
            $table->integer('escalate_after_attempt')->default(4)->comment('第N次失败后人工介入');

            // 通知策略
            $table->json('notification_channels')->comment('提醒渠道')->nullable();
            $table->integer('reminder_days_before')->default(7)->comment('提前N天开始提醒');
            $table->json('reminder_schedule')->comment('提醒节奏（过期前天数数组）')->nullable();

            // 挽留策略
            $table->boolean('retention_coupon_enabled')->default(false)->comment('启用挽留优惠券');
            $table->decimal('retention_coupon_discount_percent', 5, 2)->default(10)->comment('挽留优惠券折扣百分比');
            $table->integer('retention_coupon_max_uses')->default(1)->comment('挽留优惠券最大使用次数');
            $table->integer('retention_coupon_valid_days')->default(30)->comment('挽留优惠券有效期天数');
            $table->decimal('retention_coupon_max_discount', 10, 2)->nullable()->comment('挽留优惠券最大减免金额');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_configs');
    }
};
