<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('license_listings')) {
            return;
        }
        // 挂牌记录
        Schema::create('license_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2)->comment('挂牌价格');
            $table->decimal('commission', 12, 2)->default(0)->comment('平台抽成');
            $table->string('status', 30)->default('pending')->comment('pending/approved/rejected/sold/cancelled/expired');
            $table->text('notes')->nullable()->comment('卖家备注');
            $table->text('review_notes')->nullable()->comment('审核备注');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['seller_customer_id', 'status']);
        });

        // 交易记录
        Schema::create('license_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained('license_listings')->cascadeOnDelete();
            $table->foreignId('buyer_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->decimal('commission', 12, 2);
            $table->decimal('seller_payout', 12, 2)->comment('卖家实收');
            $table->string('status', 30)->default('completed')->comment('completed/refunded/disputed');
            $table->string('transaction_id', 100)->unique();
            $table->json('snapshot')->nullable()->comment('交易快照');
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['buyer_customer_id']);
        });

        // 纠纷仲裁
        Schema::create('license_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('license_transactions')->cascadeOnDelete();
            $table->foreignId('raised_by')->constrained('customers')->cascadeOnDelete();
            $table->string('type', 50)->comment('license_not_valid/misrepresentation/non_delivery/other');
            $table->text('description');
            $table->json('evidence')->nullable()->comment('证据文件');
            $table->string('status', 30)->default('open')->comment('open/investigation/resolved/rejected');
            $table->string('resolution', 50)->nullable()->comment('refund_buyer/partial_refund/uphold_seller/compromise');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('auto_resolve_at')->nullable();
            $table->timestamps();
        });

        // 卖家信用评分
        Schema::create('seller_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('license_transactions')->cascadeOnDelete();
            $table->foreignId('seller_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('buyer_customer_id')->constrained('customers')->cascadeOnDelete();
            $table->tinyInteger('rating')->unsigned()->comment('1-5');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_ratings');
        Schema::dropIfExists('license_disputes');
        Schema::dropIfExists('license_transactions');
        Schema::dropIfExists('license_listings');
    }
};
