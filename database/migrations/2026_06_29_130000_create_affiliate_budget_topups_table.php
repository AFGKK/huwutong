<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_budget_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('affiliate_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 14, 2)->comment('充值金额');
            $table->string('status', 20)->default('pending')->comment('pending/completed/failed');
            $table->string('payment_method', 50)->nullable()->comment('支付方式: mock/wechat/alipay');
            $table->string('transaction_id', 100)->nullable()->comment('支付流水号');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_budget_topups');
    }
};
