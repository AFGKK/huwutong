<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('oem_subscriptions')) {
            return;
        }
        Schema::create('oem_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('tier', 50)->default('basic')->comment('OEM套餐: basic/business/enterprise');
            $table->string('billing_period', 20)->default('monthly')->comment('monthly/yearly');
            $table->decimal('price', 10, 2)->default(0)->comment('当前价格');
            $table->json('active_features')->nullable()->comment('实际启用的功能列表');
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_trial')->default(false);
            $table->string('status', 30)->default('active')->comment('active/cancelled/expired/suspended');
            $table->unsignedSmallInteger('max_domains')->default(0)->comment('允许的域名数');
            $table->unsignedSmallInteger('max_themes')->default(1)->comment('允许的主题数');
            $table->json('metadata')->nullable()->comment('扩展元数据');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['expires_at']);
        });

        // ─── OEM 套餐变更日志 ───
        Schema::create('oem_subscription_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oem_subscription_id')->constrained('oem_subscriptions')->cascadeOnDelete();
            $table->string('change_type', 30)->comment('upgrade/downgrade/renew/cancel/reactivate');
            $table->string('from_tier', 50)->nullable();
            $table->string('to_tier', 50)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('operated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oem_subscription_changes');
        Schema::dropIfExists('oem_subscriptions');
    }
};
