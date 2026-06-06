<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('earnings_account_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->comment('提现金额');
            $table->string('channel')->comment('渠道: bank/alipay/wechat/paypal');
            $table->string('channel_account')->nullable()->comment('渠道账号');
            $table->string('status')->default('pending_review')->comment('状态: pending_review/processing/completed/rejected');
            $table->string('proof')->nullable()->comment('打款凭证');
            $table->text('remark')->nullable()->comment('审核备注');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['earnings_account_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
