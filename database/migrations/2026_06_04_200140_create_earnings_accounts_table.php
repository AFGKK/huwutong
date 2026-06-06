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
        Schema::create('earnings_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('agent')->comment('类型: agent/affiliate');
            $table->decimal('pending_balance', 12, 2)->default(0)->comment('待结算余额(退款保护期)');
            $table->decimal('available_balance', 12, 2)->default(0)->comment('可提现余额');
            $table->decimal('total_withdrawn', 12, 2)->default(0)->comment('已提现总额');
            $table->decimal('frozen_amount', 12, 2)->default(0)->comment('冻结金额');
            $table->string('status')->default('active')->comment('状态: active/frozen/closed');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('earnings_accounts');
    }
};
