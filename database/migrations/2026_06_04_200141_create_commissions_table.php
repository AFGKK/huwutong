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
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('earnings_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->comment('关联订单');
            $table->decimal('amount', 12, 2)->comment('佣金金额');
            $table->decimal('rate', 5, 2)->comment('佣金比例(%)');
            $table->string('status')->default('pending_settlement')->comment('状态: pending_settlement/settled/chargeback/voided');
            $table->timestamp('settled_at')->nullable()->comment('结算日期');
            $table->timestamp('frozen_until')->nullable()->comment('冻结到期日(T+30)');
            $table->timestamps();

            $table->index(['earnings_account_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
