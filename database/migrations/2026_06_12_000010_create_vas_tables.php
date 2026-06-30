<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 增值服务产品目录
        Schema::create('vas_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80)->unique()->comment('唯一编码，如 sso_audit');
            $table->string('name', 200)->comment('服务名称');
            $table->text('description')->nullable();
            $table->string('category', 50)->default('feature')->comment('feature/support/storage/api/ai');

            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency', 10)->default('CNY');

            $table->string('billing_mode', 30)->default('flat')->comment('flat/usage/tiered');
            $table->json('metered_config')->nullable()->comment('用量计费配置');
            $table->json('features')->nullable()->comment('功能清单');
            $table->json('limits')->nullable()->comment('服务限额');

            $table->boolean('is_public')->default(true)->comment('是否在市场中显示');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 增值服务开通记录
        Schema::create('vas_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vas_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete()->comment('关联主订阅');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 20)->default('active')->comment('active/suspended/cancelled/expired');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('billing_period', 20)->default('monthly')->comment('monthly/yearly/one_time');

            $table->decimal('price', 10, 2)->default(0)->comment('成交单价');
            $table->string('currency', 10)->default('CNY');
            $table->json('applied_features')->nullable()->comment('已启用的功能');
            $table->json('usage_limits')->nullable()->comment('已应用的限额覆盖');

            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['vas_service_id', 'tenant_id', 'subscription_id'], 'vas_sub_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vas_subscriptions');
        Schema::dropIfExists('vas_services');
    }
};
