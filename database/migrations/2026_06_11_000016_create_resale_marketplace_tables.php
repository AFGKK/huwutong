<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 二级市场挂牌表
        Schema::create('resale_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('license_id');
            $table->unsignedBigInteger('seller_customer_id');
            $table->string('reference', 32)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->decimal('asking_price', 12, 2);
            $table->string('currency', 3)->default('CNY');
            $table->decimal('commission_rate', 5, 2)->default(5.00); // 平台佣金比例(%)
            $table->string('status', 20)->default('draft'); // draft, published, pending_review, active, sold, cancelled, expired
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('listed_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable(); // 额外信息（转让条款、注意事项等）
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('license_id')->references('id')->on('licenses')->cascadeOnDelete();
            $table->foreign('seller_customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['seller_customer_id', 'status']);
        });

        // 二级市场交易记录表
        Schema::create('resale_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('listing_id');
            $table->unsignedBigInteger('buyer_customer_id');
            $table->string('reference', 32)->unique();
            $table->decimal('agreed_price', 12, 2);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('seller_payout', 12, 2)->default(0);
            $table->string('currency', 3)->default('CNY');
            $table->string('status', 20)->default('pending_payment'); // pending_payment, paid, pending_transfer, completed, disputed, refunded, cancelled
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('confirmed_by_seller')->nullable();
            $table->timestamp('seller_confirmed_at')->nullable();
            $table->unsignedBigInteger('executed_by')->nullable(); // 执行转移的管理员
            $table->timestamp('executed_at')->nullable();
            $table->json('audit_log')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('listing_id')->references('id')->on('resale_listings')->cascadeOnDelete();
            $table->foreign('buyer_customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('confirmed_by_seller')->references('id')->on('users')->nullOnDelete();
            $table->foreign('executed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['tenant_id', 'status']);
            $table->index(['listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resale_transactions');
        Schema::dropIfExists('resale_listings');
    }
};
