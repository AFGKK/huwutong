<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('auto_renewal_plans')) {
            return;
        }
        Schema::create('auto_renewal_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('billing_period', 30)->comment('monthly/quarterly/semi_annually/annually');
            $table->decimal('price', 10, 2);
            $table->string('currency', 10)->default('CNY');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedSmallInteger('grace_days')->default(7)->comment('续费失败宽限期');
            $table->unsignedTinyInteger('max_retries')->default(3)->comment('续费失败最大重试次数');
            $table->json('upgrade_paths')->nullable()->comment('可升级到的plan_id列表');
            $table->json('downgrade_paths')->nullable()->comment('可降级到的plan_id列表');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
            $table->unique(['product_id', 'billing_period']);
        });

        Schema::create('auto_renewal_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auto_renewal_plan_id')->constrained('auto_renewal_plans')->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('active')->comment('active/paused/cancelled/expired/failed');
            $table->timestamp('current_period_starts_at');
            $table->timestamp('current_period_ends_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('last_renewal_at')->nullable();
            $table->timestamp('next_renewal_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['next_renewal_at']);
        });

        Schema::create('auto_renewal_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auto_renewal_subscription_id')->constrained('auto_renewal_subscriptions')->cascadeOnDelete();
            $table->string('attempt_type', 30)->comment('renewal/upgrade/downgrade');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('CNY');
            $table->string('status', 30)->default('pending')->comment('pending/success/failed');
            $table->string('failure_reason')->nullable();
            $table->json('result_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_renewal_attempts');
        Schema::dropIfExists('auto_renewal_subscriptions');
        Schema::dropIfExists('auto_renewal_plans');
    }
};
